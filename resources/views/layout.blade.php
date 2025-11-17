<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @filamentStyles
    @livewireStyles

    @stack('headCSS')
    @stack('headJS')
</head>
<body class="antialiased">
{{ $slot }}
@yield('content')


@filamentScripts
@livewireScripts
@stack('scripts')
</body>
</html>
