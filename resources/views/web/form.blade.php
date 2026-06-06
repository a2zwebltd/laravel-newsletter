@extends('newsletter::web.layout')

@section('title', __('Newsletter Sign Up'))

@section('content')
    <h1>{{ __('Newsletter Sign Up') }}</h1>
    <div class="card">
        @include('newsletter::partials.newsletter-form')
    </div>
@endsection
