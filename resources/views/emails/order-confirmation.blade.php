@extends('emails.layout')

@section('title', __('Order :reference received', ['reference' => $reference]))
@section('preheader', __('Your order at :restaurant is confirmed.', ['restaurant' => $profileName]))

@section('content')
    <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #059669;">
        Order received
    </p>

    <h1 class="email-title" style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.03em; line-height: 38px; color: #18181b;">
        Thanks, {{ $customerName }}
    </h1>

    <p style="margin: 22px 0 0; font-size: 15px; line-height: 25px; color: #3f3f46;">
        <strong>{{ $profileName }}</strong> received your order
        <strong>{{ $reference }}</strong> ({{ $orderType }}{{ $tableNumber ? ' · Table '.$tableNumber : '' }}).
    </p>

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

    @include('emails.partials.button', ['url' => $trackUrl, 'label' => __('Track my order')])
@endsection
