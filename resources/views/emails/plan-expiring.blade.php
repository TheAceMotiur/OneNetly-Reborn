@extends('emails.layout')

@section('title', __('Your :plan plan ends soon', ['plan' => $planName]))
@section('preheader', __('Renew to keep your custom domain and analytics.'))
@section('footer')
    <p style="margin: 0;">You received this email because you have a paid plan on {{ config('app.name') }}.</p>
@endsection

@section('content')
    <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #d97706;">
        Plan ending
    </p>

    <h1 class="email-title" style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.03em; line-height: 38px; color: #18181b;">
        Your access ends {{ $expiresAt }}
    </h1>

    <p style="margin: 22px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        Hi {{ $customerName }}, your <strong>{{ $planName }}</strong> plan ends on
        <strong>{{ $expiresAt }}</strong>. After that date your page stays online but custom domains and analytics switch off.
    </p>

    @include('emails.partials.button', ['url' => $pricingUrl, 'label' => __('Renew my plan')])
@endsection
