const ACCENT_MAP = {
    blue:   { border: 'border-blue-200 dark:border-blue-500/30', bg: 'bg-blue-50 dark:bg-blue-500/10', text: 'text-blue-700 dark:text-blue-400' },
    green:  { border: 'border-green-200 dark:border-green-500/30', bg: 'bg-green-50 dark:bg-green-500/10', text: 'text-green-700 dark:text-green-400' },
    yellow: { border: 'border-yellow-200 dark:border-yellow-500/30', bg: 'bg-yellow-50 dark:bg-yellow-500/10', text: 'text-yellow-800 dark:text-yellow-400' },
    red:    { border: 'border-red-200 dark:border-red-500/30', bg: 'bg-red-50 dark:bg-red-500/10', text: 'text-red-700 dark:text-red-400' },
};

export default function workflow(groups) {
    return {
        groups,
        tips: [],
        rotateIdx: 0,
        playing: false,
        audio: null,
        selected: null,
        textOpen: false,
        videoOpen: false,
        paused: false,
        rotateInterval: null,
        accentMap: ACCENT_MAP,

        init() {
            this.flattenTips();
            this._audioHandler = (e) => {
                if (e.detail.source !== 'workflow' && this.playing) this.stopAudio();
            };
            window.addEventListener('lp-audio-play', this._audioHandler);
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) this.startRotate();
        },

        destroy() {
            clearInterval(this.rotateInterval);
            if (this._audioHandler) window.removeEventListener('lp-audio-play', this._audioHandler);
            this.stopAudio();
        },

        flattenTips() {
            this.tips = (this.groups || []).flatMap(g => (g.tips || []).map(t => ({tip: t, accent: g.accent, title: g.title, key: g.key, route: g.route, group: g})));
        },

        startRotate() {
            this.rotateInterval = setInterval(() => {
                if (!this.tips.length || this.textOpen || this.videoOpen || this.playing || this.paused) return;
                this.rotateIdx = (this.rotateIdx + 1) % this.tips.length;
            }, 7000);
        },

        nextTip() {
            if (!this.tips.length) return;
            this.rotateIdx = (this.rotateIdx + 1) % this.tips.length;
        },

        setTip(i) {
            if (this.tips[i]) this.rotateIdx = i;
        },

        togglePause() {
            this.paused = !this.paused;
        },

        get currentTip() {
            return this.tips[this.rotateIdx] || {};
        },

        accentBg(accent) {
            return this.accentMap[accent]?.bg || '';
        },

        accentText(accent) {
            return this.accentMap[accent]?.text || '';
        },

        hasContent(group) {
            return !!(group?.terms?.length || group?.process?.length || group?.dos?.length || group?.donts?.length);
        },

        openText(group) {
            this.selected = group;
            this.textOpen = true;
        },

        openVideo(group) {
            this.stopAudio();
            this.selected = group;
            this.videoOpen = true;
        },

        playPause(group) {
            if (this.playing && this.selected?.key === group.key) {
                this.stopAudio();
                return;
            }
            this.stopAudio();
            if (!group?.audio) return;
            window.dispatchEvent(new CustomEvent('lp-audio-play', {detail: {source: 'workflow'}}));
            this.audio = new Audio(group.audio);
            this.selected = group;
            this.audio.play().then(() => {this.playing = true;}).catch(() => {});
            this.audio.onended = () => {this.playing = false;};
        },

        stopAudio() {
            if (this.audio) {
                this.audio.pause();
                this.audio = null;
            }
            this.playing = false;
        },
    };
}
