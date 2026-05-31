<div class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-4 sm:p-6 lg:p-8 shadow-2xl">
    <div class="flex items-center justify-between gap-2 sm:gap-3 flex-wrap">

        <!-- Categories -->
        <a href="{{ route('filament.dashboard.resources.categories.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-amber-500/10 border border-amber-500/30 flex-shrink-0 shadow-lg shadow-amber-500/20">
                <x-heroicon-o-rectangle-stack class="w-5 h-5 text-amber-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.categories') ?? 'Categories' }}
            </span>
        </a>

        <!-- Products -->
        <a href="{{ route('filament.dashboard.resources.products.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-indigo-500/10 border border-indigo-500/30 flex-shrink-0 shadow-lg shadow-indigo-500/20">
                <x-heroicon-o-archive-box class="w-5 h-5 text-indigo-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.products') ?? 'Products' }}
            </span>
        </a>

        <!-- Companies -->
        <a href="{{ route('filament.dashboard.resources.companies.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-emerald-500/10 border border-emerald-500/30 flex-shrink-0 shadow-lg shadow-emerald-500/20">
                <x-heroicon-o-building-office-2 class="w-5 h-5 text-emerald-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.companies') ?? 'Companies' }}
            </span>
        </a>

        <!-- Banks -->
        <a href="{{ route('filament.dashboard.resources.banks.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-sky-500/10 border border-sky-500/30 flex-shrink-0 shadow-lg shadow-sky-500/20">
                <x-heroicon-o-building-library class="w-5 h-5 text-sky-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.banks') ?? 'Banks' }}
            </span>
        </a>

        <!-- Currencies -->
        <a href="{{ route('filament.dashboard.resources.currencies.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-lime-500/10 border border-lime-500/30 flex-shrink-0 shadow-lg shadow-lime-500/20">
                <x-heroicon-o-currency-dollar class="w-5 h-5 text-lime-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.currencies') ?? 'Currencies' }}
            </span>
        </a>

        <!-- Statuses -->
        <a href="{{ route('filament.dashboard.resources.statuses.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-teal-500/10 border border-teal-500/30 flex-shrink-0 shadow-lg shadow-teal-500/20">
                <x-heroicon-o-tag class="w-5 h-5 text-teal-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.statuses') ?? 'Statuses' }}
            </span>
        </a>

        <!-- Targets -->
        <a href="{{ route('filament.dashboard.resources.targets.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-rose-500/10 border border-rose-500/30 flex-shrink-0 shadow-lg shadow-rose-500/20">
                <x-heroicon-o-cube class="w-5 h-5 text-rose-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.targets') ?? 'Targets' }}
            </span>
        </a>

        <!-- Users -->
        <a href="{{ route('filament.dashboard.resources.users.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-violet-500/10 border border-violet-500/30 flex-shrink-0 shadow-lg shadow-violet-500/20">
                <x-heroicon-o-users class="w-5 h-5 text-violet-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.users') ?? 'Users' }}
            </span>
        </a>
        <!-- Permissions -->
        <a href="{{ route('filament.dashboard.resources.permissions.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-red-500/10 border border-red-500/30 flex-shrink-0 shadow-lg shadow-red-500/20">
                <x-heroicon-o-key class="w-5 h-5 text-red-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.permissions') ?? 'Permissions' }}
            </span>
        </a>

        <!-- Notifications -->
        <a href="{{ route('filament.dashboard.resources.notification-settings.index') }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-3 h-12 sm:h-14 hover:opacity-90">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-lg backdrop-blur-md bg-pink-500/10 border border-pink-500/30 flex-shrink-0 shadow-lg shadow-pink-500/20">
                <x-heroicon-o-bell-alert class="w-5 h-5 text-pink-500"/>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
                {{ __('dashboard/strings.resources.notification_settings') ?? 'Notifications' }}
            </span>
        </a>

    </div>
</div>
