@php
    $links = [
        ['route' => route('filament.dashboard.resources.categories.index'),            'icon' => 'heroicon-o-rectangle-stack',   'label' => __('dashboard/strings.resources.categories') ?? 'Categories'],
        ['route' => route('filament.dashboard.resources.products.index'),              'icon' => 'heroicon-o-archive-box',       'label' => __('dashboard/strings.resources.products') ?? 'Products'],
        ['route' => route('filament.dashboard.resources.companies.index'),             'icon' => 'heroicon-o-building-office-2', 'label' => __('dashboard/strings.resources.companies') ?? 'Companies'],
        ['route' => route('filament.dashboard.resources.banks.index'),                 'icon' => 'heroicon-o-building-library',  'label' => __('dashboard/strings.resources.banks') ?? 'Banks'],
        ['route' => route('filament.dashboard.resources.currencies.index'),            'icon' => 'heroicon-o-currency-dollar',   'label' => __('dashboard/strings.resources.currencies') ?? 'Currencies'],
        ['route' => route('filament.dashboard.resources.statuses.index'),              'icon' => 'heroicon-o-tag',                'label' => __('dashboard/strings.resources.statuses') ?? 'Statuses'],
        ['route' => route('filament.dashboard.resources.targets.index'),               'icon' => 'heroicon-o-cube',               'label' => __('dashboard/strings.resources.targets') ?? 'Targets'],
        ['route' => route('filament.dashboard.resources.users.index'),                 'icon' => 'heroicon-o-users',              'label' => __('dashboard/strings.resources.users') ?? 'Users'],
        ['route' => route('filament.dashboard.resources.permissions.index'),           'icon' => 'heroicon-o-key',                'label' => __('dashboard/strings.resources.permissions') ?? 'Permissions'],
        ['route' => route('filament.dashboard.resources.notification-settings.index'), 'icon' => 'heroicon-o-bell-alert',         'label' => __('dashboard/strings.resources.notification_settings') ?? 'Notifications'],
    ];
@endphp

<div class="lp-surface p-4 sm:p-5">
    <div class="flex flex-wrap justify-evenly gap-2">
        @foreach($links as $link)
            <a href="{{ $link['route'] }}" target="_blank" rel="noopener noreferrer"
               class="lp-surface-hover flex items-center gap-2 rounded-md px-2.5 py-2 border lp-divider transition-colors duration-150">
                {!! svg($link['icon'], 'w-4 h-4 flex-shrink-0 text-slate-500 dark:text-slate-400')->toHtml() !!}
                <span class="text-xs font-medium whitespace-nowrap text-slate-700 dark:text-slate-300">
                    {{ $link['label'] }}
                </span>
            </a>
        @endforeach
    </div>
</div>
