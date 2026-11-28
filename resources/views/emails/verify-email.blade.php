@extends('emails.layout')

@section('title', __('Verify your email address'))
@section('preheader', __('Confirm your email address to finish setting up your page.'))

@section('content')
    <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #6366f1;">
        Welcome to {{ config('app.name') }}
    </p>

    <h1 class="email-title" style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.03em; line-height: 38px; color: #18181b;">
        Confirm your email address
    </h1>

    <p style="margin: 22px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        Hi {{ $userName }},
    </p>

    <p style="margin: 12px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        Your page is almost ready. Confirm this email address and you can start adding links, social profiles and your bio.
    </p>

    @include('emails.partials.button', ['url' => $verificationUrl, 'label' => __('Verify my email')])

    @include('emails.partials.fallback-url', ['url' => $verificationUrl])

    <div style="margin-top: 28px; padding: 16px 18px; border: 1px solid #f4f4f5; border-radius: 10px; background-color: #fafafa; font-size: 13px; line-height: 21px; color: #52525b;">
        This link expires in {{ $expiresInMinutes }} minutes. If you did not create an account, you can safely ignore this email.
    </div>
@endsection
