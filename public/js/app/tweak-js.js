document.addEventListener('DOMContentLoaded', () => {
    // MutationObserver to detect when the modal is added to the DOM
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            const modal = document.querySelector('.fi-modal');
            if (modal && modal.parentElement !== document.body) {
                // Move the modal to the body
                document.body.appendChild(modal);
                // Ensure it’s visible
                modal.style.display = 'block';
            }
        });
    });

    // Watch the entire document for changes
    observer.observe(document.body, { childList: true, subtree: true });

    // Also check on Livewire updates (since Filament uses Livewire)
    window.addEventListener('livewire:load', () => {
        const modal = document.querySelector('.fi-modal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
            modal.style.display = 'block';
        }
    });
});
