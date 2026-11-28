<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', config('app.name'))</title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-shell { padding: 20px 12px !important; }
            .email-card { padding: 28px 22px !important; }
            .email-button { display: block !important; width: 100% !important; box-sizing: border-box !important; }
            .email-title { font-size: 26px !important; line-height: 32px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; color: #18181b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">@yield('preheader')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f5;">
        <tr>
            <td align="center" class="email-shell" style="padding: 36px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px;">
                    <tr>
                        <td style="padding: 0 4px 18px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-size: 18px; font-weight: 700; letter-spacing: -0.02em; color: #18181b;">
                                        <a href="{{ url('/') }}" style="color: #18181b; text-decoration: none;">{{ config('app.name') }}</a>
                                    </td>
                                    <td align="right" style="font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #71717a;">
                                        One page. Every link.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="overflow: hidden; border: 1px solid #e4e4e7; border-radius: 18px; background-color: #ffffff; box-shadow: 0 12px 40px -16px rgba(24, 24, 27, 0.18);">
                            <div style="height: 6px; background-color: #6366f1; background-image: linear-gradient(90deg, #6366f1 0%, #a855f7 50%, #ec4899 100%);">&nbsp;</div>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="email-card" style="padding: 40px 40px 32px;">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 22px 8px 0; text-align: center; font-size: 12px; line-height: 20px; color: #71717a;">
                            @hasSection('footer')
                                @yield('footer')
                            @else
                                <p style="margin: 0;">You received this email because of activity on your {{ config('app.name') }} account.</p>
                            @endif
                            <p style="margin: 8px 0 0;">&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
