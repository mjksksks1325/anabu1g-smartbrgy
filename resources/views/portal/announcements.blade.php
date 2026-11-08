@extends('layouts.portal')
@section('title', __('Barangay Announcements'))
@section('band')
<x-portal.page-band title="Barangay Announcements" parent="Home" :parent-url="route('home')">
    <p><span data-portal-i18n="Official updates from Barangay Anabu I-G.">{{ __('Official updates from Barangay Anabu I-G.') }}</span></p>
</x-portal.page-band>
@endsection
@section('content')
<section class="portal-announcement-archive" aria-label="{{ __('Barangay announcements') }}">
    @forelse($announcements as $announcement)
        <article class="portal-announcement-card v2-card" id="announcement-{{ $announcement->id }}">
            <p class="portal-announcement-date"><time datetime="{{ $announcement->published_at->toIso8601String() }}">{{ $announcement->published_at->format('F j, Y · g:i A') }}</time></p>
            <h2>{{ $announcement->title }}</h2>
            <div class="portal-announcement-gallery">
                        @foreach($announcement->imageUrls() as $imageUrl)
                            <img class="portal-announcement-image" src="{{ $imageUrl }}" alt="{{ $announcement->title }} - {{ $loop->iteration }}" loading="lazy">
                        @endforeach
                    </div>
            <div class="portal-announcement-body">{{ $announcement->body }}</div>
        </article>
    @empty
        <div class="portal-announcement-empty"><h2><span data-portal-i18n="No announcements yet">{{ __('No announcements yet') }}</span></h2><p><span data-portal-i18n="Published barangay announcements will appear here.">{{ __('Published barangay announcements will appear here.') }}</span></p></div>
    @endforelse
    @if($announcements->hasPages())<div class="portal-announcement-pagination">{{ $announcements->links() }}</div>@endif
</section>
@endsection
