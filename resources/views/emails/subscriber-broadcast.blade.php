@extends('emails.layout')

@section('title', $pageName)
@section('preheader', \Illuminate\Support\Str::limit(strip_tags($body), 120))
@section('footer')
    <p style="margin: 0;">
        You received this email because you subscribed to updates from {{ $pageName }}.
        <a href="{{ $unsubscribeUrl }}" style="color: #71717a; text-decoration: underline;">Unsubscribe</a>.
    </p>
@endsection

@section('content')
    <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #6366f1;">
        {{ $pageName }}
    </p>

    <div style="font-size: 15px; line-height: 26px; color: #3f3f46;">
        {!! nl2br(e($body)) !!}
    </div>

    @if (trim($footer ?? '') !== '')
        <div style="margin: 24px 0 0; padding-top: 16px; border-top: 1px solid #e4e4e7; font-size: 12px; line-height: 20px; color: #a1a1aa;">
            {!! nl2br(e($footer)) !!}
        </div>
    @endif

    <p style="margin: 26px 0 0; font-size: 12px; line-height: 20px; color: #a1a1aa;">
        <a href="{{ $unsubscribeUrl }}" style="color: #71717a; text-decoration: underline;">Unsubscribe from these emails</a>
    </p>
@endsection
