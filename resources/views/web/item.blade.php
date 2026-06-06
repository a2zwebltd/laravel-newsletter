@extends('newsletter::web.layout')

@section('title', str_replace('{name}', 'there', (string) $newsletter->getTitle()))

@section('content')
    <p><a href="{{ route('newsletters') }}">&larr; {{ __('All newsletters') }}</a></p>

    <h1>{{ str_replace('{name}', 'there', (string) $newsletter->getTitle()) }}</h1>
    <div class="muted">{{ optional($newsletter->getApprovedAt())->toFormattedDateString() }}</div>

    <div class="card">
        {!! \Illuminate\Support\Str::markdown(str_replace('{name}', 'there', (string) $newsletter->getContent())) !!}

        @if($newsletter->getCtaUrl() && $newsletter->getCtaContent())
            <p><a href="{{ $newsletter->getCtaUrlShort() ?: $newsletter->getCtaUrl() }}">{{ $newsletter->getCtaContent() }}</a></p>
        @endif

        @if($newsletter->getContentExtra())
            {!! \Illuminate\Support\Str::markdown(str_replace('{name}', 'there', (string) $newsletter->getContentExtra())) !!}
        @endif
    </div>
@endsection
