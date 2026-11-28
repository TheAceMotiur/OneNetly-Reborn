<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])

        @php
            $publicProfile = data_get($page, 'props.profile');
            $profileTitle = is_array($publicProfile) ? ($publicProfile['seo_title'] ?? null) : null;
            $profileDescription = is_array($publicProfile) ? ($publicProfile['seo_description'] ?? null) : null;
            $profileShareUrl = is_array($publicProfile) ? ($publicProfile['share_url'] ?? null) : null;
            $profileShareImage = is_array($publicProfile) ? ($publicProfile['share_image'] ?? null) : null;
        @endphp

        <x-inertia::head>
            <title>{{ filled($profileTitle) ? $profileTitle : setting('site_name', config('app.name', 'OneNetly')) }}</title>
            @if (filled($profileDescription) || filled(setting('meta_description')))
                <meta name="description" content="{{ filled($profileDescription) ? $profileDescription : setting('meta_description') }}">
            @endif

            @if (filled($profileTitle))
                <meta property="og:type" content="profile">
                <meta property="og:title" content="{{ $profileTitle }}">
                <meta property="og:description" content="{{ $profileDescription }}">
                @if (filled($profileShareUrl))
                    <meta property="og:url" content="{{ $profileShareUrl }}">
                @endif
                @if (filled($profileShareImage))
                    <meta property="og:image" content="{{ $profileShareImage }}">
                @endif
                <meta name="twitter:card" content="{{ filled($profileShareImage) ? 'summary_large_image' : 'summary' }}">
                <meta name="twitter:title" content="{{ $profileTitle }}">
                <meta name="twitter:description" content="{{ $profileDescription }}">
            @endif

            @if (($page['component'] ?? null) === 'bio/Password')
                <meta name="robots" content="noindex, nofollow">
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
