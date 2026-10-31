@extends('layouts.portal')
@section('title', __('Barangay Announcements'))
@section('band')
<x-portal.page-band title="Barangay Announcements">
    <p><span data-portal-i18n="Official updates from Barangay Anabu I-G.">{{ __('Official updates from Barangay Anabu I-G.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<section class="portal-announcement-archive" aria-label="{{ __('Barangay announcements') }}">
    <a class="portal-announcement-back" href="{{ route('home') }}#announcements"><span data-portal-i18n="Back to home page">{{ __('Back to home page') }}</span></a>
    @forelse($announcements as $announcement)
        <article class="portal-announcement-card">
            <p class="portal-announcement-date"><time datetime="{{ $announcement->published_at->toIso8601String() }}">{{ $announcement->published_at->format('F j, Y · g:i A') }}</time></p>
            <h2>{{ $announcement->title }}</h2>
            @if($announcement->image_path)<img class="portal-announcement-image" src="{{ $announcement->imageUrl() }}" alt="{{ $announcement->title }}" loading="lazy">@endif
            <div class="portal-announcement-body">{{ $announcement->body }}</div>
        </article>
    @empty
        <div class="portal-announcement-empty"><h2><span data-portal-i18n="No announcements yet">{{ __('No announcements yet') }}</span></h2><p><span data-portal-i18n="Published barangay announcements will appear here.">{{ __('Published barangay announcements will appear here.') }}</span></p></div>
    @endforelse
    @if($announcements->hasPages())<div class="portal-announcement-pagination">{{ $announcements->links() }}</div>@endif
</section>
@endsection
