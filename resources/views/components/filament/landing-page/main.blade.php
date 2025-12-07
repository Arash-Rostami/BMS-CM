@php
    $counts = $counts ?? [];
    $isDark = filament()->getCurrentPanel()->hasDarkMode() && filament()->getCurrentPanel()->hasDarkModeForced();
    $locale = app()->getLocale();
    $isRtl = in_array($locale, ['fa', 'ar']);
    $locales = [
        ['code' => 'en', 'flag' => 'usa.svg', 'alt' => 'English'],
        ['code' => 'fa', 'flag' => 'iran.svg', 'alt' => 'فارسی'],
        ['code' => 'fr', 'flag' => 'france.svg', 'alt' => 'Français'],
    ];
    $stats = [
     'purchaseRequests'  => (int) ($counts['purchase_requests'] ?? 0),
     'registeredOrders'  => (int) ($counts['registered_orders'] ?? 0),
     'purchaseOrders'    => (int) ($counts['purchase_orders'] ?? 0),
     'proformaInvoices'  => (int) ($counts['proforma_invoices'] ?? 0),
     'bankProfiles'      => (int) ($counts['bank_profiles'] ?? 0),
     'payments'          => (int) ($counts['payments'] ?? 0),
     'shipments'         => (int) ($counts['payments'] ?? 0),
     'customs'         => (int) ($counts['customs'] ?? 0),
 ];
@endphp

@push('headCSS')
    @vite('resources/css/landing-page.css')
@endpush


<div x-data="landingPage()"
     :class="darkMode ? 'dark' : 'light'"
     class="min-h-screen transition-colors duration-500"
     dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
    <!-- Loader -->
    @include('components.filament.landing-page.loader')

    <canvas id="canvas-bg" class="fixed top-0 left-0 w-full h-full z-0 opacity-40"></canvas>

    <div class="relative z-10 container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-7xl">
        <!-- Language & Theme Switcher -->
        @include('components.filament.landing-page.switchers')

        <!-- Tri-widget  -->
        @include('components.filament.landing-page.widget')

        <!-- Dashboard Header -->
        @include('components.filament.landing-page.header')

        <!-- Main Workflow -->
        @include('components.filament.landing-page.workflow')


        <!-- Dashboard Footer -->
        @include('components.filament.landing-page.footer')
    </div>
</div>

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    @vite('resources/js/landing-page.js')
@endpush
