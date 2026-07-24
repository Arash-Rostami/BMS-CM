@php
    $user = filament()->auth()->user();
    $greeting = (new \App\Services\GreetingService)->getGreeting(filament()->getUserName($user));
    $role = $user->roles->first();
    $roleBaseLabel = $role ? __("resources/user/strings.general.options.{$role->base_name}") : null;
    $roleBadge = $role ? trim(($role->grade_label ?? '').' '.($roleBaseLabel === "resources/user/strings.general.options.{$role->base_name}" ? \Illuminate\Support\Str::title(str_replace('_', ' ', $role->base_name)) : $roleBaseLabel)) : null;
    $now = now();
    $persianDate = toPersianDate($now);
    $gregorianDate = toGregorianDate($now);
@endphp

<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section class="relative overflow-hidden transition-all duration-300">
        <div class="flex items-center w-full gap-x-4">
            <div class="flex items-center gap-x-4 flex-1 min-w-0">
                <div class="relative shrink-0">
                    <div class="rounded-full ring-2 ring-primary-500/20 dark:ring-primary-400/20">
                        <x-filament-panels::avatar.user
                            size="lg"
                            :user="$user"
                            loading="lazy"
                            class="ring-2 ring-white dark:ring-gray-900"
                        />
                    </div>
                </div>

                <div class="min-w-0 flex-1 space-y-2">
                    <h2 class="text-base font-semibold tracking-tight text-gray-950 dark:text-white sm:text-lg truncate">
                        {{ $greeting }}
                    </h2>

                    <div class="h-px w-full bg-gradient-to-r from-transparent via-gray-300 to-transparent dark:via-neutral-700"></div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($roleBadge)
                            <x-filament::badge
                                color="primary"
                                size="md"
                                class="font-medium"
                            >
                                {{ $roleBadge }}
                            </x-filament::badge>
                        @endif

                        <x-filament::badge
                            color="gray"
                            size="md"
                            icon="heroicon-m-calendar-days"
                            class="font-normal"
                        >
                            <span class="inline-flex items-center gap-x-2">
                                <span>{{ $persianDate }}</span>
                                <span class="h-3 w-px bg-gray-300 dark:bg-gray-600"></span>
                                <span class="text-xs opacity-70 font-mono tracking-tight">{{ $gregorianDate }}</span>
                            </span>
                        </x-filament::badge>
                    </div>
                </div>
            </div>

            <form
                action="{{ filament()->getLogoutUrl() }}"
                method="post"
                class="shrink-0 ms-auto"
            >
                @csrf

                <x-filament::button
                    color="gray"
                    :icon="\Filament\Support\Icons\Heroicon::ArrowLeftEndOnRectangle"
                    :icon-alias="\Filament\View\PanelsIconAlias::WIDGETS_ACCOUNT_LOGOUT_BUTTON"
                    size="sm"
                    tag="button"
                    type="submit"
                    class="transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                    {{ __('filament-panels::widgets/account-widget.actions.logout.label') }}
                </x-filament::button>
            </form>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
