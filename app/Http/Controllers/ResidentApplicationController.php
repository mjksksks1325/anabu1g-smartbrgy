<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResidentApplicationRequest;
use App\Models\Purok;
use App\Models\ResidentRegistrationApplication;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ResidentApplicationController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless(Schema::hasTable('resident_registration_applications'), 503);
        $request->session()->forget('new_resident_application');
        if (! $request->session()->has('errors')) {
            $request->session()->forget('_old_input');
        }

        return view('portal.new-resident-application', [
            'application' => null,
            'puroks' => Purok::where('is_active', true)->orderBy('name')->pluck('name'),
        ]);
    }

    public function status(Request $request): View|RedirectResponse
    {
        abort_unless(Schema::hasTable('resident_registration_applications'), 503);
        $applicationId = $request->session()->get('new_resident_application');
        $application = is_int($applicationId) ? ResidentRegistrationApplication::find($applicationId) : null;
        if ($application === null) {
            return redirect()->route('portal.new-resident.create')->with('status', __('Open your application in the browser used to submit it. If that session has expired, ask barangay staff for help using your application reference.'));
        }

        return view('portal.new-resident-application', [
            'application' => $application,
            'puroks' => Purok::where('is_active', true)->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreResidentApplicationRequest $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('resident_registration_applications'), 503);
        $details = $request->safe()->except(['email', 'photo']);
        if (ResidentRegistrationApplication::matchesExistingResident($details)) {
            throw ValidationException::withMessages(['first_name' => __('A matching resident record already exists. Use the existing resident activation flow or ask barangay staff for help.')]);
        }
        $photo = $request->file('photo');
        $path = $photo->store('resident-photos', 'local');
        abort_if($path === false, 500);
        $oldPath = null;
        try {
            $application = DB::transaction(function () use ($request, $details, $path, &$oldPath): ResidentRegistrationApplication {
                $applicationId = $request->session()->get('new_resident_application');
                $application = is_int($applicationId) ? ResidentRegistrationApplication::query()->lockForUpdate()->find($applicationId) : null;
                if ($application !== null && $application->status !== 'needs_correction') {
                    throw ValidationException::withMessages(['application' => __('Your application has already been submitted. Check its status below.')]);
                }
                $oldPath = $application?->photo_path;
                $application ??= new ResidentRegistrationApplication;
                $application->fill(['details' => $details, 'email' => $request->validated('email')]);
                $application->reference ??= 'REG-'.now()->format('Y').'-'.Str::upper(Str::random(12));
                $application->identity_key = ResidentRegistrationApplication::identityKey($details);
                $application->email_key = hash('sha256', $application->email);
                $application->photo_path = $path;
                $application->locale = app()->getLocale();
                $application->status = 'pending';
                $application->save();

                return $application;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['application' => __('An application with these details is already awaiting review. Ask barangay staff for help.')]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($oldPath !== null) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
        }
        $request->session()->put('new_resident_application', $application->id);

        return redirect()->route('portal.new-resident.status')->with('status', __('Application submitted for barangay review. No portal account has been created yet.'));
    }
}
