<div x-data="{ showing: true }"
     x-cloak
     x-show="showing"
     x-init="setTimeout(() => showing = false, 2900)"
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
        <div class="ldr-eyebrow">Trade App</div>
        <div class="ldr-logo">
            <span class="ldr-letter" style="--i:0">B</span>
            <span class="ldr-letter" style="--i:1">M</span>
            <span class="ldr-letter" style="--i:2">S</span>
        </div>
        <div class="ldr-subtitle">Business Management System</div>
        <div class="ldr-divider"></div>
        <div class="ldr-progress">
            <div class="ldr-track">
                <div class="ldr-fill"></div>
            </div>
            <div class="ldr-status">Loading Resources</div>
        </div>
    </div>
</div>
