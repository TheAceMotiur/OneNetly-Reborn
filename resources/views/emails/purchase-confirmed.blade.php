@extends('emails.layout')

@section('title', __('Your :plan plan is active', ['plan' => $planName]))
@section('preheader', __('Your payment is confirmed and your new plan is ready.'))

@section('content')
    <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #059669;">
        Payment confirmed
    </p>

    <h1 class="email-title" style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.03em; line-height: 38px; color: #18181b;">
        You're all set, {{ $customerName }}
    </h1>

    <p style="margin: 22px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        Your payment has been confirmed and your <strong>{{ $planName }}</strong> plan is now active.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 28px; border: 1px solid #e4e4e7; border-radius: 12px; border-collapse: separate; overflow: hidden;">
        <tr>
            <td style="padding: 14px 18px; background-color: #fafafa; font-size: 13px; color: #71717a;">Plan</td>
            <td align="right" style="padding: 14px 18px; background-color: #fafafa; font-size: 13px; font-weight: 600; color: #18181b;">{{ $planName }}</td>
        </tr>
        <tr>
            <td style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #71717a;">Amount</td>
            <td align="right" style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; font-weight: 600; color: #18181b;">{{ $amount }}</td>
        </tr>
        <tr>
            <td style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #71717a;">Access</td>
            <td align="right" style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; font-weight: 600; color: #18181b;">{{ $accessUntil }}</td>
        </tr>
        @if ($reference)
            <tr>
                <td style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #71717a;">PayPal reference</td>
                <td align="right" style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: #18181b;">{{ $reference }}</td>
            </tr>
        @endif
    </table>

    @include('emails.partials.button', ['url' => $purchasesUrl, 'label' => __('View my purchases')])

    <p style="margin: 22px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        Thanks for supporting {{ setting('site_name', config('app.name')) }}. Your new features are available right away.
    </p>
@endsection
