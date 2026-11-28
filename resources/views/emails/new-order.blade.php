@extends('emails.layout')

@section('title', __('New order :reference', ['reference' => $reference]))
@section('preheader', __('A new order was placed from your menu.'))

@section('content')
    <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #6366f1;">
        New order
    </p>

    <h1 class="email-title" style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.03em; line-height: 38px; color: #18181b;">
        {{ $reference }}
    </h1>

    <p style="margin: 22px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        <strong>{{ $customerName }}</strong> placed an order from <strong>{{ $profileName }}</strong>.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 24px; border: 1px solid #e4e4e7; border-radius: 12px; border-collapse: separate; overflow: hidden;">
        <tr>
            <td style="padding: 14px 18px; background-color: #fafafa; font-size: 13px; color: #71717a;">Service</td>
            <td align="right" style="padding: 14px 18px; background-color: #fafafa; font-size: 13px; font-weight: 600; color: #18181b;">
                {{ $orderType }}{{ $tableNumber ? ' · Table '.$tableNumber : '' }}
            </td>
        </tr>
        <tr>
            <td style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #71717a;">Phone</td>
            <td align="right" style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #18181b;">{{ $customerPhone }}</td>
        </tr>
        @if ($customerEmail)
            <tr>
                <td style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #71717a;">Email</td>
                <td align="right" style="padding: 14px 18px; border-top: 1px solid #f4f4f5; font-size: 13px; color: #18181b;">{{ $customerEmail }}</td>
            </tr>
        @endif
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 24px; border: 1px solid #e4e4e7; border-radius: 12px; border-collapse: separate; overflow: hidden;">
        @foreach ($items as $item)
            <tr>
                <td style="padding: 14px 18px; border-top: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}; font-size: 14px; color: #18181b;">
                    {{ $item->quantity }} × {{ $item->name }}
                    @if ($item->notes)
                        <p style="margin: 4px 0 0; font-size: 12px; color: #71717a;">{{ $item->notes }}</p>
                    @endif
                </td>
                <td align="right" style="padding: 14px 18px; border-top: {{ $loop->first ? '0' : '1px solid #f4f4f5' }}; font-size: 14px; font-weight: 600; color: #18181b;">
                    {{ number_format($item->line_total_cents / 100, 2) }} {{ $currency }}
                </td>
            </tr>
        @endforeach
        <tr>
            <td style="padding: 16px 18px; border-top: 1px solid #e4e4e7; background-color: #fafafa; font-size: 15px; font-weight: 700; color: #18181b;">Total</td>
            <td align="right" style="padding: 16px 18px; border-top: 1px solid #e4e4e7; background-color: #fafafa; font-size: 15px; font-weight: 700; color: #18181b;">{{ $total }}</td>
        </tr>
    </table>

    @if ($notes)
        <div style="margin-top: 24px; padding: 20px; border-radius: 12px; background-color: #fafafa; border-left: 3px solid #6366f1; font-size: 15px; line-height: 25px; color: #27272a;">
            {!! nl2br(e($notes)) !!}
        </div>
    @endif

    @include('emails.partials.button', ['url' => $ordersUrl, 'label' => __('Open orders')])
@endsection
