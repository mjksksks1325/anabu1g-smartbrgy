<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ResidentProfilePhotoController extends Controller
{
    public function show(Request $request): StreamedResponse
    {
        $resident = Resident::query()->findOrFail($request->user()->resident_id);
        $disk = Storage::disk('local');
        abort_unless($resident->photo_path !== null && $disk->exists($resident->photo_path), 404);

        return $disk->response($resident->photo_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000']]);
        /** @var UploadedFile $photo */
        $photo = $request->file('photo');
        $path = $photo->store('resident-photos', 'local');
        if ($path === false) {
            throw ValidationException::withMessages(['photo' => __('Hindi ma-save ang larawan. Pakisubukan ulit.')]);
        }

        try {
            $oldPath = DB::transaction(function () use ($request, $path): ?string {
                $resident = Resident::query()->lockForUpdate()->findOrFail($request->user()->resident_id);
                $oldPath = $resident->photo_path;
                $resident->update(['photo_path' => $path]);

                return $oldPath;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($oldPath !== null) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
        }

        return redirect()->route('portal.profile')->with('status', __('Na-update na ang resident photo. Ito ang gagamitin sa mga susunod na certificate o clearance.'));
    }
}
