<div class="lp-surface p-4 sm:p-5">
    <div class="flex items-center justify-between gap-2 sm:gap-3 flex-wrap">

        <!-- Categories -->
        <a href="{{ route('filament.dashboard.resources.categories.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-rectangle-stack class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.categories') ?? 'Categories' }}
            </span>
        </a>

        <!-- Products -->
        <a href="{{ route('filament.dashboard.resources.products.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-archive-box class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.products') ?? 'Products' }}
            </span>
        </a>

        <!-- Companies -->
        <a href="{{ route('filament.dashboard.resources.companies.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-building-office-2 class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.companies') ?? 'Companies' }}
            </span>
        </a>

        <!-- Banks -->
        <a href="{{ route('filament.dashboard.resources.banks.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-building-library class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.banks') ?? 'Banks' }}
            </span>
        </a>

        <!-- Currencies -->
        <a href="{{ route('filament.dashboard.resources.currencies.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-currency-dollar class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.currencies') ?? 'Currencies' }}
            </span>
        </a>

        <!-- Statuses -->
        <a href="{{ route('filament.dashboard.resources.statuses.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-tag class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.statuses') ?? 'Statuses' }}
            </span>
        </a>

        <!-- Targets -->
        <a href="{{ route('filament.dashboard.resources.targets.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-cube class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.targets') ?? 'Targets' }}
            </span>
        </a>

        <!-- Users -->
        <a href="{{ route('filament.dashboard.resources.users.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-users class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.users') ?? 'Users' }}
            </span>
        </a>
        <!-- Permissions -->
        <a href="{{ route('filament.dashboard.resources.permissions.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-key class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.permissions') ?? 'Permissions' }}
            </span>
        </a>

        <!-- Notifications -->
        <a href="{{ route('filament.dashboard.resources.notification-settings.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-2.5 h-11">
            <div class="flex items-center justify-center w-8 h-8 rounded-md border lp-divider text-slate-500 dark:text-slate-400 flex-shrink-0">
                <x-heroicon-o-bell-alert class="w-4.5 h-4.5"/>
            </div>
            <span class="font-medium text-xs sm:text-sm whitespace-nowrap text-slate-700 dark:text-slate-300">
                {{ __('dashboard/strings.resources.notification_settings') ?? 'Notifications' }}
            </span>
        </a>

    </div>
</div>
