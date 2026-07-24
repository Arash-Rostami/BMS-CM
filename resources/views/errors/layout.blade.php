@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'fa';
    $title = __("errors/strings.codes.{$code}.title");
    $message = __("errors/strings.codes.{$code}.message");
    $timestamp = match ($locale) {
        'fa' => toPersianDate(now()).' - '.now()->format('H:i'),
        'fr' => now()->locale('fr')->translatedFormat('d F Y - H:i'),
        default => now()->format('F j, Y - H:i'),
    };
    $dashboardUrl = url('/dashboard');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — {{ $code }} | {{ $title }}</title>
    <script>
        (function () {
            var theme = localStorage.getItem('theme');
            var isDark = theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) document.documentElement.classList.add('dark');
        })();
    </script>
    <style>
        @font-face {
            font-family: 'Roboto';
            src: url('{{ asset('fonts/Roboto.woff2') }}') format('woff2'),
                 url('{{ asset('fonts/Roboto.woff') }}') format('woff');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'IranYekan';
            src: url('{{ asset('fonts/Iranyekan.woff') }}') format('woff'),
                 url('{{ asset('fonts/Iranyekan.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --custom-neutral: #E8E8E8;
            --custom-first: #F8FAFC;
            --custom-fourth: #9AA6B2;
            --custom-third-light: rgba(188, 204, 220, 0.4);
            --filament-dark: #09090B;
            --filament-dark-mid: #18181B;
            --google-fourth-light: #5C6AC4;
            --google-fourth-dark: #6750A4;
            --md-elevation-3: 0px 1px 3px 0px rgba(0, 0, 0, .3), 0px 4px 8px 3px rgba(0, 0, 0, .15);
            --md-elevation-3-dark: 0px 1px 3px 0px rgba(0, 0, 0, .5), 0px 4px 8px 3px rgba(0, 0, 0, .3);
            --md-motion: cubic-bezier(0.2, 0, 0, 1);

            --accent: var(--google-fourth-light);
            --surface: var(--custom-neutral);
            --body-bg: var(--custom-first);
            --text-primary: var(--filament-dark);
            --text-muted: var(--custom-fourth);
            --border-color: var(--custom-third-light);
            --shadow: var(--md-elevation-3);
        }

        html.dark {
            --accent: var(--google-fourth-dark);
            --surface: var(--filament-dark-mid);
            --body-bg: var(--filament-dark);
            --text-primary: #F8FAFC;
            --text-muted: #9AA6B2;
            --border-color: rgba(255, 255, 255, .08);
            --shadow: var(--md-elevation-3-dark);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--body-bg);
            color: var(--text-primary);
            font-family: 'Roboto', sans-serif;
            padding: 1.5rem;
        }

        [dir="rtl"] body {
            font-family: 'IranYekan', 'Roboto', sans-serif;
        }

        .card {
            width: 100%;
            max-width: 28rem;
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2.5rem 2rem;
            text-align: center;
        }

        .logo {
            width: 100px;
            height: auto;
            margin: 0 auto 1.5rem;
            display: block;
            object-fit: contain;
            border-radius: 12px;
        }

        .code {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
            color: var(--accent);
            letter-spacing: -0.02em;
        }

        .title {
            margin-top: .5rem;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .message {
            margin-top: .75rem;
            font-size: .9375rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .meta {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            font-size: .75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
        }

        .actions {
            margin-top: 1.75rem;
            display: flex;
            gap: .75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .625rem 1.25rem;
            border-radius: 10px;
            font-size: .875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            font-family: inherit;
            transition: transform .15s var(--md-motion);
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-secondary {
            background: transparent;
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .btn:hover {
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="card">
        <img class="logo" src="{{ asset(config('app.branding.favicon')) }}" alt="{{ config('app.name') }}">

        <div class="code">{{ $code }}</div>
        <div class="title">{{ $title }}</div>
        <p class="message">{{ $message }}</p>

        <div class="meta">
            <span>{{ __('errors/strings.occurred_at') }}:</span>
            <span>{{ $timestamp }}</span>
        </div>

        <div class="actions">
            <a href="{{ $dashboardUrl }}" class="btn btn-primary">{{ __('errors/strings.go_to_dashboard') }}</a>
            <button type="button" class="btn btn-secondary" onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ $dashboardUrl }}'">{{ __('errors/strings.go_back') }}</button>
        </div>
    </div>
</body>
</html>
