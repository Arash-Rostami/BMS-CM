const ACCENT_MAP = {
    blue:   { border: 'border-blue-200 dark:border-blue-500/30', bg: 'bg-blue-50 dark:bg-blue-500/10', text: 'text-blue-700 dark:text-blue-400' },
    green:  { border: 'border-green-200 dark:border-green-500/30', bg: 'bg-green-50 dark:bg-green-500/10', text: 'text-green-700 dark:text-green-400' },
    yellow: { border: 'border-yellow-200 dark:border-yellow-500/30', bg: 'bg-yellow-50 dark:bg-yellow-500/10', text: 'text-yellow-800 dark:text-yellow-400' },
    red:    { border: 'border-red-200 dark:border-red-500/30', bg: 'bg-red-50 dark:bg-red-500/10', text: 'text-red-700 dark:text-red-400' },
};

const EVENT_AUDIO = 'lp-audio-play';
const SRC_WORKFLOW = 'workflow';
const MOTION_QUERY = '(prefers-reduced-motion: reduce)';

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

        _audioHandler: null,

        init() {
            this.flattenTips();

            this._audioHandler = (e) => {
                if (e.detail.source !== SRC_WORKFLOW && this.playing) this.stopAudio();
            };

            window.addEventListener(EVENT_AUDIO, this._audioHandler);

            if (!window.matchMedia(MOTION_QUERY).matches) {
                this.startRotate();
            }
        },

        destroy() {
            clearInterval(this.rotateInterval);
            if (this._audioHandler) window.removeEventListener(EVENT_AUDIO, this._audioHandler);
            this.stopAudio();
        },

        flattenTips() {
            const result = [];
            const grps = this.groups || [];
            const len = grps.length;

            for (let i = 0; i < len; i++) {
                const g = grps[i];
                const gTips = g.tips || [];
                const tLen = gTips.length;

                for (let j = 0; j < tLen; j++) {
                    result.push({
                        tip: gTips[j],
                        accent: g.accent,
                        title: g.title,
                        key: g.key,
                        route: g.route,
                        group: g
                    });
                }
            }
            this.tips = result;
        },

        startRotate() {
            this.rotateInterval = setInterval(() => {
                const len = this.tips.length;
                if (len === 0 || this.textOpen || this.videoOpen || this.playing || this.paused) return;
                this.rotateIdx = (this.rotateIdx + 1) % len;
            }, 7000);
        },

        nextTip() {
            const len = this.tips.length;
            if (len === 0) return;
            this.rotateIdx = (this.rotateIdx + 1) % len;
        },

        setTip(i) {
            if (i >= 0 && i < this.tips.length) this.rotateIdx = i;
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
            if (this.playing && this.selected?.key === group?.key) {
                this.stopAudio();
                return;
            }

            this.stopAudio();

            if (!group?.audio) return;

            window.dispatchEvent(new CustomEvent(EVENT_AUDIO, { detail: { source: SRC_WORKFLOW } }));

            this.audio = new Audio(group.audio);
            this.selected = group;

            this.audio.play().then(() => {
                this.playing = true;
            }).catch(() => {});

            this.audio.onended = () => {
                this.playing = false;
            };
        },

        stopAudio() {
            if (this.audio) {
                this.audio.pause();
                this.audio.src = '';
                this.audio = null;
            }
            this.playing = false;
        },
    };
}
