<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Models\ResidentRegistrationApplication;
use App\Models\User;
use App\Notifications\ResidentRegistrationReviewedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResidentApplicationReviewController extends Controller
{
    public function index(): View
    {
        Gate::authorize('records.review');
        abort_unless(Schema::hasTable('resident_registration_applications'), 503);

        return view('admin.resident-applications', ['applications' => ResidentRegistrationApplication::orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->latest()->paginate(10)]);
    }

    public function photo(ResidentRegistrationApplication $application): StreamedResponse
    {
        Gate::authorize('records.review');
        $disk = Storage::disk('local');
        abort_unless($disk->exists($application->photo_path), 404);

        return $disk->response($application->photo_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function update(Request $request, ResidentRegistrationApplication $application): RedirectResponse
    {
        Gate::authorize('records.review');
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'declined', 'needs_correction'])],
            'remarks' => ['required_unless:decision,approved', 'nullable', 'string', 'max:1000'],
            ...array_fill_keys(['reviewed_by', 'resident_id', 'status', 'role', 'is_active', 'details', 'email', 'photo_path', 'staff_permissions'], ['prohibited']),
        ]);
        DB::transaction(function () use ($request, $application, $validated): void {
            $application = ResidentRegistrationApplication::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($application->status === 'pending', 409);
            if ($validated['decision'] === 'approved') {
                if (ResidentRegistrationApplication::matchesExistingResident($application->details) || User::whereRaw('LOWER(email) = ?', [$application->email])->exists()) {
                    throw ValidationException::withMessages(['decision' => 'A matching resident or account now exists. Review the duplicate before approving.']);
                }
                $resident = new Resident($application->details);
                $resident->status = 'active';
                $resident->photo_path = $application->photo_path;
                $resident->save();
                $application->resident()->associate($resident);

            }
            $application->status = $validated['decision'];
            $application->remarks = $validated['remarks'] ?? null;
            $application->reviewer()->associate($request->user());
            $reviewedAt = now();
            $application->reviewed_at = $reviewedAt;
            if (in_array($application->status, ['approved', 'declined'], true)) {
                $application->identity_key = null;
                $application->email_key = null;
            }
            $application->save();
            DB::table('administrative_audits')->insert([
                'user_id' => $request->user()->id, 'actor' => $request->user()->name,
                'action' => 'admin.resident-applications.'.$application->status, 'type' => 'record',
                'record' => $application->reference.'; pending -> '.$application->status,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            Notification::route('mail', $application->email)->notify(
                (new ResidentRegistrationReviewedNotification(
                    $application->reference, $application->status, $application->remarks,
                    $reviewedAt->format('M d, Y h:i A'),
                ))->locale($application->locale),
            );
        });

        return redirect()->route('staff.resident-applications.index')->with('status', 'Registration review saved. The result email has been queued for the applicant.');
    }
}
