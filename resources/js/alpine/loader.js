import landingPage from './components/landing-page.js';
import triWidget from './components/tri-widget.js';
import search from './components/search.js';
import workspace from './components/workspace.js';
import workflow from './components/workflow.js';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('landingPage', landingPage);
    window.Alpine.data('triWidget', triWidget);
    window.Alpine.data('workflow', workflow);
    window.Alpine.data('search', search);
    window.Alpine.data('workspace', workspace);
});
