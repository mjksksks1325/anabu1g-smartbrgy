<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordResidentVerificationChange;
use App\Actions\StoreResidentPhoto;
use App\Http\Controllers\Controller;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ResidentPhotoController extends Controller
{
    public function show(Resident $resident): StreamedResponse
    {
        Gate::authorize('view', $resident);
        $disk = Storage::disk('local');
        abort_unless($resident->photo_path !== null && $disk->exists($resident->photo_path), 404);

        return $disk->response($resident->photo_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function store(Request $request, Resident $resident): JsonResponse
    {
        Gate::authorize('update', $resident);
        $request->validate(['photo' => StoreResidentPhoto::rules()]);
        $path = app(StoreResidentPhoto::class)->handle($request->file('photo'));
        abort_if($path === false, 500, 'Photo could not be saved.');

        try {
            $oldPath = DB::transaction(function () use ($request, $resident, $path): ?string {
                $lockedResident = Resident::query()->lockForUpdate()->findOrFail($resident->id);
                $oldPath = $lockedResident->photo_path;
                $before = RecordResidentVerificationChange::snapshot($lockedResident);
                $lockedResident->update(['photo_path' => $path]);
                app(RecordResidentVerificationChange::class)->handle($request->user(), $lockedResident, $before);

                return $oldPath;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($oldPath !== null) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
        }

        return response()->json(['message' => 'Resident photo updated.', 'photo_url' => route('staff.residents.photo', $resident)]);
    }
}
