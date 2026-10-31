<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status', 'all')->toString();
        abort_unless(in_array($status, ['all', 'draft', 'published', 'archived'], true), 404);

        return view('admin.announcements.index', [
            'announcements' => Announcement::query()
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:12000'],
            'status' => ['required', Rule::in([Announcement::STATUS_DRAFT, Announcement::STATUS_PUBLISHED])],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp,avif,heic,heif', 'extensions:jpg,jpeg,png,gif,bmp,webp,avif,heic,heif', 'max:5120'],
        ]);

        if ($validated['status'] === Announcement::STATUS_PUBLISHED) {
            Gate::authorize('announcements.publish');
        }

        $imagePath = $request->hasFile('image') ? $this->storeImage($request->file('image')) : null;

        try {
            Announcement::query()->create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'image_path' => $imagePath,
                'status' => $validated['status'],
                'published_at' => $validated['status'] === Announcement::STATUS_PUBLISHED ? now() : null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        return back()->with('status', $validated['status'] === Announcement::STATUS_PUBLISHED
            ? 'Announcement published on the resident portal.'
            : 'Announcement saved as a draft.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:12000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp,avif,heic,heif', 'extensions:jpg,jpeg,png,gif,bmp,webp,avif,heic,heif', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);

        $previousImagePath = $announcement->image_path;
        $newImagePath = $request->hasFile('image') ? $this->storeImage($request->file('image')) : null;
        $imagePath = $newImagePath ?? ($request->boolean('remove_image') ? null : $previousImagePath);

        try {
            $announcement->fill([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'image_path' => $imagePath,
                'updated_by' => $request->user()->id,
            ])->save();
        } catch (\Throwable $exception) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($previousImagePath !== null && $previousImagePath !== $imagePath) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return back()->with('status', 'Announcement updated.');
    }

    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->status === Announcement::STATUS_ARCHIVED, 404);

        $isPublished = $announcement->status !== Announcement::STATUS_PUBLISHED;
        $announcement->forceFill([
            'status' => $isPublished ? Announcement::STATUS_PUBLISHED : Announcement::STATUS_DRAFT,
            'published_at' => $isPublished ? now() : null,
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('status', $isPublished
            ? 'Announcement published on the resident portal.'
            : 'Announcement unpublished and saved as a draft.');
    }

    public function archive(Request $request, Announcement $announcement): JsonResponse|RedirectResponse
    {
        Gate::authorize('announcements.archive');

        $announcement->forceFill([
            'status' => Announcement::STATUS_ARCHIVED,
            'updated_by' => $request->user()->id,
        ])->save();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Announcement archived and removed from the resident portal.']);
        }

        return back()->with('status', 'Announcement archived and removed from the resident portal.');
    }

    public function restore(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('announcements.archive');

        $announcement->forceFill([
            'status' => Announcement::STATUS_DRAFT,
            'published_at' => null,
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'Announcement restored as a draft.');
    }

    private function storeImage(UploadedFile $image): string
    {
        $extension = strtolower($image->getClientOriginalExtension());
        $path = 'announcements/'.Str::uuid()->toString().'.'.$extension;

        if (! Storage::disk('public')->putFileAs('announcements', $image, basename($path))) {
            throw ValidationException::withMessages(['image' => 'Hindi na-save ang image. Subukan ulit.']);
        }

        return $path;
    }
}
