<div x-show="loading"
     x-init="setTimeout(() => loading = false, 1000)"
     x-transition:leave="transition-opacity duration-700"
     class="loader-overlay">
    <div class="relative">
        <div class="loader-logo">BMS</div>
        <div class="loader-bar"></div>
    </div>
</div>
