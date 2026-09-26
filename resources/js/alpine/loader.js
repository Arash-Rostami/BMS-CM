import Alpine from 'alpinejs';
import landingPage from './components/landing-page.js';
import triWidget from './components/tri-widget.js';
import search from './components/search.js';
import workspace from './components/workspace.js';
import workflow from './components/workflow.js';

window.Alpine ??= Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('landingPage', landingPage);
    Alpine.data('triWidget', triWidget);
    Alpine.data('workflow', workflow);
    Alpine.data('search', search);
    Alpine.data('workspace', workspace);
});

if (!window.__alpine_running) {
    window.__alpine_running = true;
    Alpine.start();
}

export default Alpine;
