@php
    $resources = [
        \App\Filament\Resources\PurchaseRequestResource::class,
        \App\Filament\Resources\ProformaInvoiceResource::class,
        \App\Filament\Resources\RegisteredOrderResource::class,
        \App\Filament\Resources\BankProfileResource::class,
        \App\Filament\Resources\CorrespondenceResource::class,
        \App\Filament\Resources\PurchaseOrderResource::class,
        \App\Filament\Resources\PaymentResource::class,
        \App\Filament\Resources\ShipmentResource::class,
        \App\Filament\Resources\CustomResource::class,
    ];

    $createGroups = [];
    $resourceMap = [];

    foreach ($resources as $resource) {
        $slug = $resource::getSlug();
        $resourceMap[$slug] = [
            'group' => $resource::getNavigationGroup(),
            'label' => $resource::getNavigationLabel(),
        ];

        if ($resource::canCreate() && $resource::canViewAny()) {
            $createGroups[$resource::getNavigationGroup()][] = [
                'label' => $resource::getNavigationLabel(),
                'icon' => $resource::getNavigationIcon(),
                'url' => route('filament.dashboard.resources.'.$slug.'.create'),
            ];
        }
    }
@endphp

<script>
    window.BMS_RESOURCE_MAP = @json($resourceMap);
</script>

<div class="hidden shrink-0 items-center lg:flex" style="order: 8">
    <div class="w-px self-stretch my-1.5 me-3 bg-black/10 dark:bg-white/10" aria-hidden="true"></div>

    @if ($createGroups)
        <div class="relative" x-data="{ open: false }">
            <x-icon-button
                x-on:click="open = !open"
                tooltip="{{ __('resources/general/strings.topbar.quick_create') }}"
                aria-haspopup="true"
                x-bind:aria-expanded="open"
            >
                <x-heroicon-o-plus class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
            </x-icon-button>

            <div x-cloak
                 x-show="open"
                 @click.away="open = false"
                 @keydown.escape.window="open = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="tb-menu w-72">
                @foreach ($createGroups as $groupLabel => $items)
                    <div class="tb-menu-group">{{ $groupLabel }}</div>
                    @foreach ($items as $item)
                        <a href="{{ $item['url'] }}" x-on:click="open = false" class="tb-menu-item">
                            <x-dynamic-component :component="$item['icon']" class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" />
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative"
         x-data="{
             open: false,
             entries: [],
             load() {
                 try { this.entries = JSON.parse(localStorage.getItem('recent_records')) || []; }
                 catch (e) { this.entries = []; }
                 this.entries = this.entries.filter((e) => e && typeof e.url === 'string' && e.url.startsWith('/dashboard/'));
                 this.entries.forEach((e) => {
                     if (!e.number) {
                         const m = (e.label || '').match(/\s#(\d+)$/);
                         if (m) { e.number = m[1]; e.label = e.label.slice(0, m.index); }
                         else { e.number = (e.url.match(/\/(\d+)/) || [])[1] || ''; }
                     }
                 });
             },
             clear() {
                 try { localStorage.removeItem('recent_records'); } catch (e) {}
                 this.entries = [];
             },
             meta(slug) { return (window.BMS_RESOURCE_MAP || {})[slug] || {}; },
             init() {
                 this.load();
                 window.addEventListener('recents-updated', () => this.load());
             },
         }">
        <x-icon-button
            x-on:click="open = !open"
            tooltip="{{ __('resources/general/strings.topbar.recent_records') }}"
            aria-haspopup="true"
            x-bind:aria-expanded="open"
        >
            <x-heroicon-o-clock class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
        </x-icon-button>

        <div x-cloak
             x-show="open"
             @click.away="open = false"
             @keydown.escape.window="open = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="tb-menu w-80">
            <div x-show="entries.length === 0" class="flex flex-col items-center gap-2 px-4 py-6 text-center">
                <x-heroicon-o-clock class="h-7 w-7 text-gray-300 dark:text-gray-600" />
                <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('resources/general/strings.topbar.recents_empty') }}</span>
            </div>

            <div x-show="entries.length > 0" class="tb-menu-scroll">
                <template x-for="entry in entries" :key="entry.url">
                    <a :href="entry.url" x-on:click="open = false" class="tb-menu-item">
                        <span class="tb-chip" x-text="meta(entry.slug).group"></span>
                        <span class="truncate" x-text="entry.label"></span>
                        <span dir="ltr" class="shrink-0 tabular-nums" x-text="'#' + entry.number"></span>
                    </a>
                </template>
            </div>

            <div x-show="entries.length > 0" class="tb-menu-footer">
                <button type="button" x-on:click="clear()" class="flex items-center gap-1.5 text-xs font-medium text-gray-500 transition-colors hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                    <x-heroicon-o-trash class="h-3.5 w-3.5" />
                    {{ __('resources/general/strings.topbar.recents_clear') }}
                </button>
            </div>
        </div>
    </div>
</div>