@extends('emails.layout')

@section('title', __(':name invited you to :page', ['name' => $inviterName, 'page' => $pageName]))
@section('preheader', __('Accept the invitation to start managing :page.', ['page' => $pageName]))

@section('content')
    <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #6366f1;">
        Invitation
    </p>

    <h1 class="email-title" style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.03em; line-height: 38px; color: #18181b;">
        Join {{ $pageName }}
    </h1>

    <p style="margin: 22px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        <strong>{{ $inviterName }}</strong> invited you to help manage
        <strong>{{ $pageName }}</strong> as {{ $role }}.
    </p>

    <p style="margin: 16px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        If you do not have an account yet, create one with this email address and your access will be added automatically.
    </p>

    @include('emails.partials.button', ['url' => $acceptUrl, 'label' => __('Accept invitation')])

    <p style="margin: 22px 0 0; font-size: 13px; line-height: 22px; color: #71717a;">
        If you were not expecting this invitation you can safely ignore this email.
    </p>
@endsection
