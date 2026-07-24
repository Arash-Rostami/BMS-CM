import Alpine from 'alpinejs';
import landingPage from './components/landing-page.js';
import triWidget from './components/tri-widget.js';
import search from './components/search.js';
import workspace from './components/workspace.js';
import workflow from './components/workflow.js';

window.Alpine = window.Alpine || Alpine;

document.addEventListener('alpine:init', () => {
    if (document.querySelector('[x-data="landingPage()"]')) Alpine.data('landingPage', landingPage);
    if (document.querySelector('[x-data="triWidget()"]')) Alpine.data('triWidget', triWidget);
    if (document.querySelector('[x-data^="workflow("]')) Alpine.data('workflow', workflow);

    Alpine.data('search', search);
    Alpine.data('workspace', workspace);
});

if (!window.__alpine_running) {
    Alpine.start();
    window.__alpine_running = true;
}

export default Alpine;
