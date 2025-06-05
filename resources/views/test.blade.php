<div
    x-data="{ isOpen: {{ session()->has('user') ? 'true' : 'false' }}, user: @js(session('user')) }"
    x-show="isOpen"
    x-cloak
    @keydown.escape.window="isOpen = false"
    class="fixed inset-0  flex items-center justify-center"
    style="z-index: 10000!important;"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-black/60 transition-opacity duration-300"
        :class="{ 'opacity-0': !isOpen, 'opacity-100': isOpen }"
        @click="isOpen = false"
    ></div>

    {{-- Fullscreen Content Panel --}}
    <div
        class="relative bg-white shadow-xl rounded-none
               w-full h-full
               transform transition-all duration-300 ease-out
               opacity-0 scale-95 translate-y-8"
        :class="{
            'opacity-100 scale-100 translate-y-0': isOpen,
            'opacity-0 scale-95 translate-y-8': !isOpen
        }"
    >
        {{-- Header --}}
        <header class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-2xl font-semibold text-gray-800">
                @if(session()->has('user'))
                    Hello, {{ session('user') }}!
                @else
                    Modal Title
                @endif
            </h2>
            <button
                @click="isOpen = false"
                class="text-gray-500 hover:text-gray-700 focus:outline-none"
                aria-label="Close"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </header>

        {{-- Body --}}
        <div class="p-6 overflow-auto h-[calc(100%-112px)]">
            <p class="text-gray-700 mb-4">
                This modal panel now spans the <strong>full height and width</strong> of the screen.
            </p>
            <p class="text-gray-600">
                Customize this area with any content you like. It scrolls internally if it overflows.
            </p>
        </div>

        {{-- Footer --}}
        <footer class="px-6 py-4 bg-gray-50 border-t text-right">
            <button
                @click="isOpen = false"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 focus:outline-none transition"
            >
                Close
            </button>
        </footer>
    </div>
</div>
