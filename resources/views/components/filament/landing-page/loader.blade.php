<div x-data="{ showing: !sessionStorage.getItem('bms_loaded') }"
     x-cloak
     x-show="showing"
     x-init="if (showing) { setTimeout(() => { showing = false; sessionStorage.setItem('bms_loaded', '1') }, 2900) }"
     x-transition:leave="transition-opacity duration-700 ease-in-out"
     class="loader-overlay"
     dir="ltr">

    <div class="ldr-grid"></div>
    <div class="ldr-scan"></div>
    <div class="ldr-glow"></div>
    <div class="ldr-c ldr-c-tl"></div>
    <div class="ldr-c ldr-c-tr"></div>
    <div class="ldr-c ldr-c-bl"></div>
    <div class="ldr-c ldr-c-br"></div>

    <div class="ldr-body">
        <div class="ldr-eyebrow">Work <span class="ldr-slogan-hard">hard</span> <span class="ldr-slogan-smart">smart</span></div>
        <div class="ldr-logo">
            <span class="ldr-letter" style="--i:0">B</span>
            <span class="ldr-letter" style="--i:1">M</span>
            <span class="ldr-letter" style="--i:2">S</span><span class="ldr-cm" style="--i:3">CM</span>
        </div>
        <div class="ldr-subtitle">Business Management System</div>
        <div class="ldr-divider"></div>
        <div class="ldr-progress">
            <div class="ldr-track">
                <div class="ldr-fill"></div>
            </div>
            <div class="ldr-status">
                <svg class="ldr-status-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10 17 15 12 10 7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
                Loading Resources
            </div>
        </div>
    </div>
</div>
