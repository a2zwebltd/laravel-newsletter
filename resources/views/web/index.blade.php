@extends('newsletter::web.layout')

@section('title', __('Newsletters'))

@section('content')
    <h1>{{ __('Newsletters') }}</h1>

    @forelse($newsletters as $newsletter)
        <div class="card">
            <a href="{{ route('newsletters.item', ['slug' => $newsletter->getSlug()]) }}">
                <strong>{{ str_replace('{name}', 'there', (string) $newsletter->getTitle()) }}</strong>
            </a>
            <div class="muted">{{ optional($newsletter->getApprovedAt())->toFormattedDateString() }}</div>
        </div>
    @empty
        <p class="muted">{{ __('No newsletters published yet.') }}</p>
    @endforelse

    <p><a href="{{ route('newsletters.form') }}">{{ __('Subscribe to our newsletter') }}</a></p>
@endsection
