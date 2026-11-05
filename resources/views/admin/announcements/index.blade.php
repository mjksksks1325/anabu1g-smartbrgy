@extends('layouts.admin-iot')
@section('title', 'Announcements')
@section('staff-ui', 'true')
@section('content')
@php
    $creating = old('_announcement') === null;
    $allErrors = $errors;
    $noErrors = new \Illuminate\Support\ViewErrorBag;
    $imageTypes = '.jpg,.jpeg,.png,.gif,.bmp,.webp,.avif,.heic,.heif,image/jpeg,image/png,image/gif,image/bmp,image/webp,image/avif,image/heic,image/heif';
    $filters = [
        'all' => ['label' => 'All', 'hint' => 'Every record', 'icon' => 'newspaper', 'tone' => 'brand', 'count' => array_sum($statusCounts)],
        'published' => ['label' => 'Published', 'hint' => 'On the resident portal', 'icon' => 'globe-alt', 'tone' => 'success', 'count' => $statusCounts['published'] ?? 0],
        'draft' => ['label' => 'Drafts', 'hint' => 'Staff only', 'icon' => 'pencil-square', 'tone' => 'warning', 'count' => $statusCounts['draft'] ?? 0],
        'archived' => ['label' => 'Archived', 'hint' => 'Kept for the record', 'icon' => 'archive-box', 'tone' => 'neutral', 'count' => $statusCounts['archived'] ?? 0],
    ];
@endphp
<div data-ui class="text-zinc-800 dark:text-zinc-100">
    <x-ui.page-header
        eyebrow="Public Information"
        title="Announcements"
        description="Create barangay updates for residents. Drafts stay staff-only; published announcements appear on the resident portal."
    >
        @can('announcements.create')
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" data-announcement-create-open aria-haspopup="dialog">New announcement</flux:button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <nav class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Filter announcements" data-announcement-filters>
        @foreach ($filters as $filter => $card)
            <x-ui.kpi-card
                :label="$card['label']"
                :value="number_format($card['count'])"
                :hint="$card['hint']"
                :icon="$card['icon']"
                :tone="$card['tone']"
                :href="route('staff.announcements.index', ['status' => $filter])"
                :current="$status === $filter"
            />
        @endforeach
    </nav>

    <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-800" aria-labelledby="announcement-list-title">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-5 py-3.5 dark:border-zinc-700">
            <flux:heading level="2" id="announcement-list-title">Announcement records</flux:heading>
            <flux:text class="text-sm tabular-nums">{{ number_format($announcements->total()) }} total</flux:text>
        </div>

        @if ($announcements->isEmpty())
            <x-ui.empty-state title="No announcements here yet" icon="megaphone">
                @can('announcements.create')Select New announcement to write one. @endcan Published announcements appear on the resident portal.
            </x-ui.empty-state>
        @else
            <ul role="list" class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($announcements as $announcement)
                    <li class="flex flex-col gap-4 px-5 py-5 sm:flex-row" data-announcement-record>
                        @if ($announcement->image_path)
                            <img class="aspect-[4/3] w-full shrink-0 rounded-lg border border-zinc-200 bg-zinc-100 object-cover sm:w-40 dark:border-zinc-700 dark:bg-zinc-900" src="{{ $announcement->imageUrl() }}" alt="{{ $announcement->title }}" loading="lazy" data-no-admin-translate>
                        @endif
                        <article class="min-w-0 flex-1" aria-labelledby="announcement-{{ $announcement->id }}-title">
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:heading level="3" size="lg" id="announcement-{{ $announcement->id }}-title" class="break-words" data-no-admin-translate>{{ $announcement->title }}</flux:heading>
                                        <flux:badge size="sm" :color="match ($announcement->status) { 'published' => 'green', 'draft' => 'amber', default => 'zinc' }" data-announcement-status>{{ ucfirst($announcement->status) }}</flux:badge>
                                    </div>
                                    <flux:text class="mt-1 text-xs tabular-nums">Created {{ $announcement->created_at->format('M j, Y g:i A') }}@if ($announcement->status === 'published' && $announcement->published_at) · Published {{ $announcement->published_at->format('M j, Y g:i A') }}@endif</flux:text>
                                </div>
                                <div class="flex flex-wrap items-center gap-2" data-announcement-actions>
                                    @if ($announcement->status === 'archived')
                                        @can('announcements.archive')
                                            <form method="POST" action="{{ route('staff.announcements.restore', $announcement) }}">
                                                @csrf
                                                <flux:button type="submit" size="sm" variant="outline" icon="arrow-uturn-left">Restore as draft</flux:button>
                                            </form>
                                        @endcan
                                    @else
                                        @can('announcements.update')
                                            <flux:button size="sm" variant="outline" icon="pencil-square" data-announcement-edit-open="{{ $announcement->id }}" aria-haspopup="dialog">Edit<span class="sr-only">: {{ $announcement->title }}</span></flux:button>
                                        @endcan
                                        @can('announcements.publish')
                                            <form method="POST" action="{{ route('staff.announcements.publish', $announcement) }}">
                                                @csrf
                                                @if ($announcement->status === 'published')
                                                    <flux:button type="submit" size="sm" variant="outline" icon="eye-slash">Unpublish</flux:button>
                                                @else
                                                    <flux:button type="submit" size="sm" variant="primary" icon="globe-alt">Publish</flux:button>
                                                @endif
                                            </form>
                                        @endcan
                                        @can('announcements.archive')
                                            <flux:button size="sm" variant="ghost" icon="archive-box" class="text-red-700! hover:bg-red-50! dark:text-red-400! dark:hover:bg-red-400/10!" data-announcement-archive data-archive-url="{{ route('staff.announcements.archive', $announcement) }}" data-announcement-title="{{ $announcement->title }}" aria-haspopup="dialog">Archive</flux:button>
                                        @endcan
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 max-w-prose whitespace-pre-line break-words text-sm leading-relaxed text-zinc-700 dark:text-zinc-300" data-announcement-body data-no-admin-translate>{{ $announcement->body }}</div>
                        </article>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($announcements->hasPages())
            <div class="border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                <x-ui.pager :paginator="$announcements" singular="announcement" plural="announcements" />
            </div>
        @endif
    </section>

    <div class="mt-4" data-announcement-feedback role="status" hidden>
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text data-announcement-feedback-text></flux:callout.text>
        </flux:callout>
    </div>

    {{--
        Flux form controls mark a field invalid whenever the shared error bag has its name, and the create form and
        every edit form share field names. Each form therefore sees only its own errors: the create form when the
        failed request was a create, an edit form when it was that announcement's edit.
    --}}
    @can('announcements.create')
        @scopeErrors($creating ? $allErrors : $noErrors)
        {{-- Creating is an occasional, focused task, so the form lives in a dialog; it reopens with the typed values after a validation error. --}}
        <flux:modal name="announcement-create" class="w-full max-w-xl" data-announcement-create-dialog data-labelledby="announcement-create-title" :data-open-on-load="$creating && $allErrors->hasAny(['title', 'body', 'image', 'status']) ? '' : null">
            <form class="space-y-5" method="POST" enctype="multipart/form-data" action="{{ route('staff.announcements.store') }}">
                @csrf
                <div class="pe-8">
                    <flux:heading level="2" size="lg" id="announcement-create-title">New announcement</flux:heading>
                    <flux:text class="mt-1">Optional image · up to 5 MB. Drafts stay staff-only until published.</flux:text>
                </div>
                <flux:field>
                    <flux:label>Title</flux:label>
                    <flux:input name="title" maxlength="180" required :value="$creating ? old('title') : ''" placeholder="Announcement title" />
                    <flux:error name="title" />
                </flux:field>
                <flux:field>
                    <flux:label>Announcement</flux:label>
                    <flux:textarea name="body" maxlength="12000" rows="6" required placeholder="Write the details residents need to know.">{{ $creating ? old('body') : '' }}</flux:textarea>
                    <flux:error name="body" />
                </flux:field>
                <flux:field>
                    <flux:label>Image (optional)</flux:label>
                    <flux:input type="file" name="image" :accept="$imageTypes" />
                    <flux:description>Images only; the original image format is kept. PDF and document files are not accepted.</flux:description>
                    <flux:error name="image" />
                </flux:field>
                <div class="flex flex-col gap-4 border-t border-zinc-200 pt-5 sm:flex-row sm:items-end sm:justify-between dark:border-zinc-700">
                    <flux:field class="sm:w-48">
                        <flux:label>Save as</flux:label>
                        <flux:select name="status" required>
                            <flux:select.option value="draft" :selected="old('status', 'draft') === 'draft'">Draft</flux:select.option>
                            @can('announcements.publish')
                                <flux:select.option value="published" :selected="old('status') === 'published'">Publish now</flux:select.option>
                            @endcan
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>
                    <div class="flex justify-end gap-2">
                        <flux:button type="button" variant="ghost" data-announcement-create-close>Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Save announcement</flux:button>
                    </div>
                </div>
            </form>
        </flux:modal>
        @scopeErrors($allErrors)
    @endcan

    @can('announcements.update')
        @foreach ($announcements as $announcement)
            @continue($announcement->status === 'archived')
            @php($editingThis = (string) old('_announcement') === (string) $announcement->id)
            @scopeErrors($editingThis ? $allErrors : $noErrors)
            {{-- Editing opens beside the list (a flyout), so the record being changed stays in view. --}}
            <flux:modal flyout name="announcement-edit-{{ $announcement->id }}" class="w-full md:w-[34rem]" data-announcement-edit-dialog="{{ $announcement->id }}" data-labelledby="announcement-edit-{{ $announcement->id }}-title" :data-open-on-load="$editingThis && $allErrors->any() ? '' : null">
                <form class="space-y-5" method="POST" enctype="multipart/form-data" action="{{ route('staff.announcements.update', $announcement) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_announcement" value="{{ $announcement->id }}">
                    <div class="pe-8">
                        <flux:heading level="2" size="lg" id="announcement-edit-{{ $announcement->id }}-title">Edit announcement</flux:heading>
                        <flux:text class="mt-1 break-words" data-no-admin-translate>{{ $announcement->title }}</flux:text>
                    </div>
                    <flux:field>
                        <flux:label>Title</flux:label>
                        <flux:input name="title" maxlength="180" required :value="$editingThis ? old('title') : $announcement->title" />
                        <flux:error name="title" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Announcement</flux:label>
                        <flux:textarea name="body" maxlength="12000" rows="8" required>{{ $editingThis ? old('body') : $announcement->body }}</flux:textarea>
                        <flux:error name="body" />
                    </flux:field>
                    @if ($announcement->image_path)
                        <div class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <img class="size-14 shrink-0 rounded-md object-cover" src="{{ $announcement->imageUrl() }}" alt="" loading="lazy">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="checkbox" name="remove_image" value="1" class="size-4"> Remove current image
                            </label>
                        </div>
                    @endif
                    <flux:field>
                        <flux:label>Replace image (optional)</flux:label>
                        <flux:input type="file" name="image" :accept="$imageTypes" />
                        <flux:description>Images only; the original image format is kept. PDF and document files are not accepted.</flux:description>
                        <flux:error name="image" />
                    </flux:field>
                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost">Cancel</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">Save changes</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endforeach
        @scopeErrors($allErrors)
    @endcan

    <flux:modal name="announcement-archive" class="w-full max-w-md" data-announcement-archive-dialog data-labelledby="announcement-archive-title">
        <form class="space-y-4" data-announcement-archive-form aria-describedby="announcement-archive-description">
            <div class="pe-8">
                <flux:heading level="2" size="lg" id="announcement-archive-title">Archive announcement</flux:heading>
                <flux:text class="mt-2" id="announcement-archive-description">This removes the announcement from the resident portal while retaining its record. Confirm with the active Barangay Captain or Super Admin password.</flux:text>
            </div>
            <p class="rounded-lg bg-zinc-100 px-3 py-2 text-sm font-medium break-words dark:bg-white/10" data-announcement-archive-title data-no-admin-translate></p>
            <flux:field>
                <flux:label>Password of the Barangay Captain or Super Admin</flux:label>
                <flux:input id="announcement-archive-password" type="password" input:name="super_admin_password" autocomplete="current-password" required>
                    <x-slot name="iconTrailing">
                        <x-password-toggle target="announcement-archive-password" class="grid size-8 place-items-center rounded-md text-zinc-500 hover:bg-zinc-800/5 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white" />
                    </x-slot>
                </flux:input>
                {{-- Same styling as flux:error; announcement-management.js fills it from the server's reply. --}}
                <p class="text-sm font-medium text-red-600 dark:text-red-400" role="alert" data-announcement-archive-error hidden></p>
            </flux:field>
            <div class="flex justify-end gap-2 pt-1">
                <flux:button type="button" variant="ghost" data-announcement-archive-cancel>Cancel</flux:button>
                <flux:button type="submit" variant="danger" data-announcement-archive-submit>Confirm archive</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
<script defer src="{{ asset('js/announcement-management.js') }}?v={{ filemtime(public_path('js/announcement-management.js')) }}"></script>
@endsection
