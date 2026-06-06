@extends('newsletter::web.layout')

@section('title', __('Unsubscribe'))

@section('content')
    <h1>{{ __('Unsubscribe') }}</h1>

    <div class="card">
        @if($unsubscribed)
            <p>{{ __('You have been unsubscribed. We are sorry to see you go.') }}</p>
        @else
            <p>{{ __('Are you sure you want to unsubscribe from our newsletter?') }}</p>
            <form method="POST" action="{{ route('unsubscribe.confirm', ['uuid' => $uuid]) }}">
                @csrf
                <button type="submit">{{ __('Yes, unsubscribe me') }}</button>
            </form>
        @endif
    </div>
@endsection
