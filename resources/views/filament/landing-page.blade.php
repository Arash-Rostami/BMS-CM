@php
    $counts = $counts ?? [];
    $locale = app()->getLocale();
    $isRtl  = in_array($locale, ['fa', 'ar']);
    $locales = [
        ['code' => 'en', 'flag' => 'usa.svg',    'alt' => 'English'],
        ['code' => 'fa', 'flag' => 'iran.svg',   'alt' => 'فارسی'],
        ['code' => 'fr', 'flag' => 'france.svg', 'alt' => 'Français'],
    ];
    $stats = [
        'purchaseRequests' => (int) ($counts['purchase_requests']  ?? 0),
        'registeredOrders' => (int) ($counts['registered_orders']  ?? 0),
        'purchaseOrders'   => (int) ($counts['purchase_orders']    ?? 0),
        'proformaInvoices' => (int) ($counts['proforma_invoices']  ?? 0),
        'bankProfiles'     => (int) ($counts['bank_profiles']      ?? 0),
        'payments'         => (int) ($counts['payments']           ?? 0),
        'shipments'        => (int) ($counts['shipments']          ?? 0),
        'customs'          => (int) ($counts['customs']            ?? 0),
    ];
@endphp


@push('headCSS')
    @vite('resources/css/landing-page.css')
@endpush

<div x-data="landingPage()"
     :class="darkMode ? 'dark' : 'light'"
     class="h-screen overflow-hidden transition-colors duration-500"
     dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
     @keydown.window="if (($event.metaKey || $event.ctrlKey) && $event.key === 'k') { $event.preventDefault(); activeTab = 'search'; $nextTick(() => $dispatch('tab-search-focus')); }">

    @include('filament.landing-page.loader')

    <div x-data="{ appReady: false }"
         x-init="setTimeout(() => appReady = true, 2900)"
         x-show="appReady"
         x-cloak
         x-transition:enter="transition-opacity duration-700 ease-out"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="h-full w-full">

        @include('filament.landing-page.switchers')
        @include('filament.landing-page.widget')

        <div class="relative z-10 h-full overflow-y-auto pt-8 custom-scrollbar">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 pb-10 max-w-7xl">

                @include('filament.landing-page.header')

                <div x-show="activeTab === 'customize'"
                     x-transition:enter="transition-opacity duration-150 ease-in"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity duration-100 ease-out"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     x-cloak>
                    @livewire('landing-page.workspace', ['counts' => $counts])
                </div>

                <div x-show="activeTab === 'workflow'"
                     x-transition:enter="transition-opacity duration-150 ease-in"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity duration-100 ease-out"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     x-cloak>
                    @livewire('landing-page.workflow', ['counts' => $counts, 'isRtl' => $isRtl])
                </div>

                <div x-show="activeTab === 'search'"
                     x-transition:enter="transition-opacity duration-150 ease-in"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity duration-100 ease-out"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     x-cloak
                     class="min-h-[calc(100vh-12rem)] flex flex-col">
                    @livewire('landing-page.search', ['isRtl' => $isRtl])
                </div>

            </div>
        </div>
    </div>
</div>
