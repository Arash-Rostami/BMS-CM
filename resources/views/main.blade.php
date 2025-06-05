<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @yield('css')
    @yield('headJS')
</head>
<body class="antialiased">

@yield('content')

@livewireScripts
@stack('scripts')
</body>
</html>
