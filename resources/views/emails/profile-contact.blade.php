@extends('emails.layout')

@section('title', __('New message from :name', ['name' => $senderName]))
@section('preheader', __('A visitor sent you a message from your bio page.'))

@section('content')
    <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #6366f1;">
        New contact message
    </p>

    <h1 class="email-title" style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.03em; line-height: 38px; color: #18181b;">
        A new message from {{ $senderName }}
    </h1>

    <p style="margin: 22px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        Someone used the contact form on your page <strong>{{ $profileName }}</strong>.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 24px; border: 1px solid #e4e4e7; border-radius: 12px; border-collapse: separate; overflow: hidden;">
        <tr>
            <td style="padding: 14px 18px; background-color: #fafafa; font-size: 13px; color: #71717a;">From</td>
            <td align="right" style="padding: 14px 18px; background-color: #fafafa; font-size: 13px; font-weight: 600; color: #18181b;">{{ $senderName }}</td>
        </tr>
        <tr>
            <td style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #71717a;">Email</td>
            <td align="right" style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #18181b;">
                <a href="mailto:{{ $senderEmail }}" style="color: #4f46e5; text-decoration: none;">{{ $senderEmail }}</a>
            </td>
        </tr>
    </table>

    <div style="margin-top: 24px; padding: 20px; border-radius: 12px; background-color: #fafafa; border-left: 3px solid #6366f1; font-size: 15px; line-height: 25px; color: #27272a;">
        {!! nl2br(e($messageBody)) !!}
    </div>

    @include('emails.partials.button', ['url' => $profileUrl, 'label' => __('Open my page')])

    <p style="margin: 22px 0 0; font-size: 13px; line-height: 21px; color: #71717a;">
        Reply directly to this email to answer {{ $senderName }}.
    </p>
@endsection
