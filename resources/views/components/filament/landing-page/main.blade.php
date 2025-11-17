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
     'logistics'         => 0,
 ];
@endphp


@push('headCSS')
    @vite('resources/css/landing-page.css')
@endpush


<div x-data="{
        darkMode: false,
        showSubmodules: false,
        hoveredStep: null,
        loading: true
     }"
     :class="darkMode ? 'dark' : 'light'"
     class="min-h-screen transition-colors duration-500"
     dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
     x-init="
        const KEY = 'theme';
        let raw = null;
        try { raw = localStorage.getItem(KEY); } catch(e) {}
        if (raw !== null) {
            const v = String(raw).toLowerCase();
            darkMode = (v === '1' || v === 'true' || v === 'dark' || v === 'on');
        }
        document.documentElement.classList.toggle('dark', !!darkMode);
        window.addEventListener('dark-mode-toggled', e => darkMode = e.detail);
        $watch('darkMode', val => {
            try { localStorage.setItem(KEY, val ? 'dark' : 'light'); } catch(e) {}
            document.documentElement.classList.toggle('dark', !!val);
            if (window.torusMaterial) {
                window.torusMaterial.opacity = val ? 0.1 : 0.3;
                window.ringMaterial.opacity  = val ? 0.2 : 0.1;
            }
        });
     ">
    <!-- Loader -->
    @include('components.filament.landing-page.loader')

    <canvas id="canvas-bg" class="fixed top-0 left-0 w-full h-full z-0 opacity-40"></canvas>

    <div class="relative z-10 container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-7xl">
        <!-- Language & Theme Switcher -->
        @include('components.filament.landing-page.switchers')

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
