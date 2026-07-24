@props([
    'open',
    'width' => '3xl',
])

<div
    x-show="{{ $open }}"
    x-cloak
    x-on:keydown.window.escape="{{ $open }} = false"
    class="fi-modal fi-absolute-positioning-context"
    :class="{ 'fi-modal-open': {{ $open }} }"
    role="dialog"
    aria-modal="true"
>
    <div aria-hidden="true" x-show="{{ $open }}" x-transition.duration.300ms.opacity class="fi-modal-close-overlay" x-on:click="{{ $open }} = false"></div>
    <div class="fi-modal-window-ctn fi-clickable" x-on:click.self="{{ $open }} = false">
        <div x-show="{{ $open }}" x-transition:enter="fi-transition-enter" x-transition:enter-start="fi-transition-enter-start" x-transition:enter-end="fi-transition-enter-end"
             x-transition:leave="fi-transition-leave" x-transition:leave-start="fi-transition-leave-start" x-transition:leave-end="fi-transition-leave-end"
             class="fi-modal-window fi-width-{{ $width }} fi-modal-window-has-close-btn fi-modal-window-has-content">
            <div class="fi-modal-header">
                <button type="button" x-on:click="{{ $open }} = false" class="fi-modal-close-btn" aria-label="{{ __('filament::components/modal.actions.close.label') }}">
                    <x-heroicon-o-x-mark class="w-5 h-5"/>
                </button>
                @isset($heading)
                    <div>
                        {{ $heading }}
                        {{ $description ?? '' }}
                    </div>
                @endisset
            </div>
            <div class="fi-modal-content">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
