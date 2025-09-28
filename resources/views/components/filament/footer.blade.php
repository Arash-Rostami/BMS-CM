@php
    $isSidebarCollapsible = filament()->isSidebarCollapsibleOnDesktop();
    $isSidebarFullyCollapsible = filament()->isSidebarFullyCollapsibleOnDesktop();
@endphp

<footer
    @if ($isSidebarCollapsible)
        x-bind:class="{
            'lg:ps-[--sidebar-width]': ! $isSidebarFullyCollapsible,
            'lg:ps-[--collapsed-sidebar-width]': $isSidebarFullyCollapsible && ! $isSidebarCollapsed,
        }"
    @endif
    class="custom-footer"
>
    <div class="flex items-center justify-center h-full">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            &copy; {{ date('Y') }} Persol CM BMS
        </p>
    </div>
</footer>
