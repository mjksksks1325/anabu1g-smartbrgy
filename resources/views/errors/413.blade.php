{{-- Shown when a form, usually with a photo, is larger than the server accepts (PHP post_max_size): PHP discards it before the application can validate it. --}}
@extends('errors.minimal')

@section('title', __('File too large'))
@section('code', '413')
@section('message', __('The file you sent is too large.'))
@section('lead', __('Nothing was saved. Go back, choose a smaller photo or file, and submit the form again. Resident photos can be up to 10 MB. If a smaller photo also fails, contact the barangay office.'))
