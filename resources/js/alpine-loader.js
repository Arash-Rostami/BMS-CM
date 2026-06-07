import Alpine from 'alpinejs';
import landingPage from './landing-page-alpine.js';
import triWidget from './tri-widget-alpine.js';
import workspace from './workspace.js';

window.Alpine = window.Alpine || Alpine;

document.addEventListener('alpine:init', () => {
    if (document.querySelector('[x-data="landingPage()"]')) Alpine.data('landingPage', landingPage);
    if (document.querySelector('[x-data="triWidget()"]')) Alpine.data('triWidget', triWidget);

    Alpine.data('workspace', workspace);
});

if (!window.__alpine_running) {
    Alpine.start();
    window.__alpine_running = true;
}

export default Alpine;
