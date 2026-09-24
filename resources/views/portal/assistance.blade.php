@extends('layouts.portal')
@section('title', 'Account assistance')
@section('content')<section class="card"><div class="card-header"><h2>Account assistance</h2></div><div class="card-body"><p role="alert">{{ $message }}</p><a class="btn btn-outline" href="{{ route('portal.information') }}#help">Barangay assistance</a></div></section>@endsection
