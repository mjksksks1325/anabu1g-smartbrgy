<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            'statusCounts' => Announcement::query()
                ->toBase()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn (mixed $count): int => (int) $count)
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:12000'],
            'status' => ['required', Rule::in([Announcement::STATUS_DRAFT, Announcement::STATUS_PUBLISHED])],
            ...$this->imageRules(),
        ]);

        if ($validated['status'] === Announcement::STATUS_PUBLISHED) {
            Gate::authorize('announcements.publish');
        }

        $imagePaths = $this->storeImages($request, 0);

        try {
            Announcement::query()->create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'image_path' => $imagePaths[0] ?? null,
                'image_paths' => $imagePaths,
                'status' => $validated['status'],
                'published_at' => $validated['status'] === Announcement::STATUS_PUBLISHED ? now() : null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($imagePaths);

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
            ...$this->imageRules(),
            'remove_image' => ['sometimes', 'boolean'],
            'remove_images' => ['sometimes', 'array', 'max:10'],
            'remove_images.*' => ['string', 'distinct', Rule::in($announcement->imagePaths())],
        ]);

        $newImagePaths = [];
        $removedImagePaths = [];

        try {
            DB::transaction(function () use ($request, $announcement, $validated, &$newImagePaths, &$removedImagePaths): void {
                $announcement = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
                $previousImagePaths = $announcement->imagePaths();
                $removedImagePaths = array_values(array_intersect($previousImagePaths, $validated['remove_images'] ?? []));
                if ($request->boolean('remove_image') || $request->hasFile('image')) {
                    $removedImagePaths = $previousImagePaths;
                }
                $retainedImagePaths = array_values(array_diff($previousImagePaths, $removedImagePaths));
                $newImagePaths = $this->storeImages($request, count($retainedImagePaths));
                $imagePaths = array_merge($retainedImagePaths, $newImagePaths);

                $announcement->fill([
                    'title' => $validated['title'],
                    'body' => $validated['body'],
                    'image_path' => $imagePaths[0] ?? null,
                    'image_paths' => $imagePaths,
                    'updated_by' => $request->user()->id,
                ])->save();
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newImagePaths);
            throw $exception;
        }

        Storage::disk('public')->delete($removedImagePaths);

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

    /** @return array<string, array<int, string>> */
    private function imageRules(): array
    {
        $imageRules = ['file', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp,avif,heic,heif', 'extensions:jpg,jpeg,png,gif,bmp,webp,avif,heic,heif', 'max:5120'];

        return [
            'image' => ['nullable', ...$imageRules],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['required', ...$imageRules],
        ];
    }

    /** @return list<string> */
    private function storeImages(Request $request, int $retainedCount): array
    {
        $uploads = $request->file('images', []);
        if (! is_array($uploads)) {
            throw ValidationException::withMessages(['images' => 'Choose image files.']);
        }
        $images = array_values($uploads);
        $legacyImage = $request->file('image');
        if ($legacyImage instanceof UploadedFile) {
            $images[] = $legacyImage;
        }
        if ($retainedCount + count($images) > 10) {
            throw ValidationException::withMessages(['images' => 'An announcement can have up to 10 images. Remove an existing image before adding more.']);
        }

        $paths = [];
        try {
            foreach ($images as $image) {
                $paths[] = $this->storeImage($image);
            }
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }

        return $paths;
    }

    private function storeImage(UploadedFile $image): string
    {
        $extension = strtolower($image->getClientOriginalExtension());
        $path = 'announcements/'.Str::uuid()->toString().'.'.$extension;

        if (! Storage::disk('public')->putFileAs('announcements', $image, basename($path))) {
            throw ValidationException::withMessages(['images' => 'Hindi na-save ang image. Subukan ulit.']);
        }

        return $path;
    }
}
