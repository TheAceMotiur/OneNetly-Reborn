@extends('emails.layout')

@section('title', __('Reset your password'))
@section('preheader', __('Use the secure link inside to choose a new password.'))

@section('content')
    <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #6366f1;">
        Account security
    </p>

    <h1 class="email-title" style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.03em; line-height: 38px; color: #18181b;">
        Reset your password
    </h1>

    <p style="margin: 22px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        Hi {{ $userName }},
    </p>

    <p style="margin: 12px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        We received a request to reset the password for your {{ config('app.name') }} account. Choose a new password using the button below.
    </p>

    @include('emails.partials.button', ['url' => $resetUrl, 'label' => __('Choose a new password')])

    @include('emails.partials.fallback-url', ['url' => $resetUrl])

    <div style="margin-top: 28px; padding: 16px 18px; border: 1px solid #f4f4f5; border-radius: 10px; background-color: #fafafa; font-size: 13px; line-height: 21px; color: #52525b;">
        This link expires in {{ $expiresInMinutes }} minutes. If you did not request a reset, no action is needed and your account is still secure.
    </div>
@endsection
