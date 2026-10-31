@extends('layouts.admin-iot')
@section('title', 'Announcements')
@section('content')
<div class="eyebrow">Public Information</div>
<div class="page-heading">
    <div><h1>Announcements</h1><p>Create barangay updates for residents. Drafts stay staff-only; published announcements appear on the resident portal.</p></div>
</div>

@can('announcements.create')
<section class="panel announcement-create-panel" aria-labelledby="announcement-create-title">
    <div class="panel-heading"><h2 id="announcement-create-title">Create an announcement</h2><span>Optional image · up to 5 MB</span></div>
    <form class="announcement-form" method="POST" enctype="multipart/form-data" action="{{ route('staff.announcements.store') }}">
        @csrf
        <label>Title<input name="title" maxlength="180" required value="{{ old('title') }}" placeholder="Announcement title"></label>
        <label>Announcement<textarea name="body" maxlength="12000" rows="6" required placeholder="Write the details residents need to know.">{{ old('body') }}</textarea></label>
        <label>Image (optional)<input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.avif,.heic,.heif,image/jpeg,image/png,image/gif,image/bmp,image/webp,image/avif,image/heic,image/heif"><small>Images only; the original image format is kept. PDF and document files are not accepted.</small></label>
        <div class="announcement-form-actions">
            <label>Save as
                <select name="status" required>
                    <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                    @can('announcements.publish')<option value="published" @selected(old('status') === 'published')>Publish now</option>@endcan
                </select>
            </label>
            <button class="button" type="submit">Save announcement</button>
        </div>
    </form>
</section>
@endcan

<section class="panel announcement-list-panel" aria-labelledby="announcement-list-title">
    <div class="panel-heading"><h2 id="announcement-list-title">Announcement records</h2><span>{{ $announcements->total() }} total</span></div>
    <nav class="announcement-filters" aria-label="Filter announcements">
        @foreach(['all' => 'All', 'published' => 'Published', 'draft' => 'Drafts', 'archived' => 'Archived'] as $filter => $label)
            <a @class(['button', 'quiet' => $status !== $filter]) href="{{ route('staff.announcements.index', ['status' => $filter]) }}" @if($status === $filter) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @forelse($announcements as $announcement)
        <article class="announcement-record" data-announcement-record>
            <div class="announcement-record-heading">
                <div><h3>{{ $announcement->title }}</h3><p>Created {{ $announcement->created_at->format('M j, Y g:i A') }}@if($announcement->status === 'published' && $announcement->published_at) · Published {{ $announcement->published_at->format('M j, Y g:i A') }}@endif</p></div>
                <span class="pill {{ $announcement->status === 'published' ? 'success' : ($announcement->status === 'draft' ? 'warning' : 'neutral') }}">{{ ucfirst($announcement->status) }}</span>
            </div>
            @if($announcement->image_path)<img class="announcement-record-image" src="{{ $announcement->imageUrl() }}" alt="{{ $announcement->title }}" loading="lazy">@endif
            @if($announcement->status === 'archived')
                <div class="announcement-record-body">{{ $announcement->body }}</div>
                @can('announcements.archive')
                    <form method="POST" action="{{ route('staff.announcements.restore', $announcement) }}">@csrf<button class="button quiet" type="submit">Restore as draft</button></form>
                @endcan
            @else
                @can('announcements.update')
                <form class="announcement-form announcement-edit-form" method="POST" enctype="multipart/form-data" action="{{ route('staff.announcements.update', $announcement) }}">
                    @csrf
                    @method('PATCH')
                    <label>Title<input name="title" maxlength="180" required value="{{ $announcement->title }}"></label>
                    <label>Announcement<textarea name="body" maxlength="12000" rows="5" required>{{ $announcement->body }}</textarea></label>
                    @if($announcement->image_path)<img class="announcement-record-image" src="{{ $announcement->imageUrl() }}" alt="{{ $announcement->title }}" loading="lazy"><label class="announcement-remove-image"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>@endif
                    <label>Replace image (optional)<input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.avif,.heic,.heif,image/jpeg,image/png,image/gif,image/bmp,image/webp,image/avif,image/heic,image/heif"><small>Images only; the original image format is kept. PDF and document files are not accepted.</small></label>
                    <div class="announcement-form-actions">
                        <button class="button" type="submit">Save changes</button>
                    </div>
                </form>
                @else
                    <div class="announcement-record-body">{{ $announcement->body }}</div>
                @endcan
                @can('announcements.publish')<form class="announcement-record-publish" method="POST" action="{{ route('staff.announcements.publish', $announcement) }}">@csrf<button class="button quiet" type="submit">{{ $announcement->status === 'published' ? 'Unpublish' : 'Publish' }}</button></form>@endcan
                @can('announcements.archive')<button class="button danger announcement-record-archive" type="button" data-announcement-archive data-archive-url="{{ route('staff.announcements.archive', $announcement) }}" data-announcement-title="{{ $announcement->title }}">Archive</button>@endcan
            @endif
        </article>
    @empty
        <div class="empty-state"><strong>No announcements here yet</strong><p>Create an announcement above. Published posts will appear in the resident portal.</p></div>
    @endforelse
    @if($announcements->hasPages())<div class="announcement-pagination">{{ $announcements->links() }}</div>@endif
</section>
<div class="notice success announcement-feedback" data-announcement-feedback role="status" hidden></div>
<dialog class="announcement-archive-dialog" data-announcement-archive-dialog aria-labelledby="announcement-archive-title" aria-describedby="announcement-archive-description">
    <form data-announcement-archive-form>
        <h2 id="announcement-archive-title">Archive announcement</h2>
        <p id="announcement-archive-description">This removes the announcement from the resident portal while retaining its record. Confirm with the active Barangay Captain or Super Admin password.</p>
        <p class="announcement-archive-title" data-announcement-archive-title></p>
        <label for="announcement-archive-password">Password ng Barangay Captain o Super Admin</label>
        <span class="password-visibility-field"><input id="announcement-archive-password" name="super_admin_password" type="password" autocomplete="current-password" required><x-password-toggle target="announcement-archive-password" /></span>
        <p class="announcement-archive-error" data-announcement-archive-error role="alert" hidden></p>
        <div class="announcement-archive-actions"><button class="button quiet" type="button" data-announcement-archive-cancel>Cancel</button><button class="button danger" type="submit" data-announcement-archive-submit>Confirm archive</button></div>
    </form>
</dialog>
<script defer src="{{ asset('js/announcement-management.js') }}?v={{ filemtime(public_path('js/announcement-management.js')) }}"></script>
@endsection
