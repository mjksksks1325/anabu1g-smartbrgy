{{-- Shown instead of any database error. The details (SQL, values, file paths) are only in the protected log, under this reference. --}}
@extends('errors.minimal')

@section('title', __('Something went wrong'))
@section('code', '500')
@section('message', __('Something went wrong on our side and the action was not completed. Try again; if it keeps happening, give the system administrator this reference: :reference.', ['reference' => $reference]))
