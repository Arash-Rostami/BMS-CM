const CLOCK_OPTS = { weekday: 'short', month: 'short', day: 'numeric' };
const SHAMSI_OPTS = { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long', numberingSystem: 'latn' };
const DATE_FMT = new Intl.DateTimeFormat(undefined, CLOCK_OPTS);
const SHAMSI_FMT = new Intl.DateTimeFormat('fa-IR', SHAMSI_OPTS);

const EVENT_AUDIO = 'lp-audio-play';
const SRC_WIDGET = 'widget';
const STORAGE_MUSIC = 'lp_music';
const ALARM_BASE = '/audio/';

const WIDGET_TRACKS = [
    { title: 'LoFi', src: '/audio/music/lofi.m4a', time: '61 min 14 sec', image: '/img/widget/lofi.png' },
    { title: 'Vocale', src: '/audio/music/vocale.m4a', time: '95 min 55 sec', image: '/img/widget/pop.png' },
    { title: 'Pomodoro', src: '/audio/music/pomodoro.mp3', time: '147 min 15 sec', image: '/img/widget/pomodoro.png' },
    { title: 'Electronic', src: '/audio/music/electronic.m4a', time: '120 min 55 sec', image: '/img/widget/unnamed.png' },
];

export default function triWidget() {
    return {
        tab: 'clock',
        clockString: '',
        dateString: '',
        shamsiDateString: '',
        timer: { running: false, seconds: 300 },
        customMins: null,
        alarm: 'alarm.mp3',
        alarmInterval: null,
        alarmAudioInstance: null,
        music: {
            tracks: WIDGET_TRACKS,
            idx: 0,
            audio: null,
            playing: false,
            position: 0,
            duration: 0,
            progress: 0,
            volume: 0.8
        },

        _lastSec: -1,
        _clockTimerId: null,
        _countdownTimerId: null,

        init() {
            this._loadMusicPrefs();
            this._tick();

            this._clockTimerId = setInterval(() => this._tick(), 1000);
            this._countdownTimerId = setInterval(() => this._countdownTick(), 1000);

            this._onExternalPlay = (e) => {
                if (e.detail.source !== SRC_WIDGET && this.music.playing) {
                    this.music.audio?.pause?.();
                    this.music.playing = false;
                }
            };

            window.addEventListener(EVENT_AUDIO, this._onExternalPlay);

            this.$watch('tab', (val) => {
                if (val === 'music') this.loadCurrentTrack();
            });
        },

        destroy() {
            clearInterval(this._clockTimerId);
            clearInterval(this._countdownTimerId);
            window.removeEventListener(EVENT_AUDIO, this._onExternalPlay);
            this.stopAlarm();
        },

        _tick() {
            const d = new Date();
            const s = d.getSeconds();

            if (this._lastSec === s) return;
            this._lastSec = s;

            this.clockString = d.toLocaleTimeString();
            this.dateString = DATE_FMT.format(d);
            this.shamsiDateString = SHAMSI_FMT.format(d);
        },

        _countdownTick() {
            if (!this.timer.running || this.timer.seconds <= 0) return;

            if (--this.timer.seconds === 0) {
                this.timer.running = false;
                this.startAlarmLoop();
            }
        },

        startAlarmLoop() {
            this.stopAlarm();

            const a = new Audio(ALARM_BASE + this.alarm);
            a.loop = true;
            this.alarmAudioInstance = a;
            a.play().catch(() => {});

            this.alarmInterval = setTimeout(() => this.stopAlarm(), 60000);
        },

        stopAlarm() {
            if (this.alarmInterval) {
                clearTimeout(this.alarmInterval);
                this.alarmInterval = null;
            }
            if (this.alarmAudioInstance) {
                this.alarmAudioInstance.pause();
                this.alarmAudioInstance.currentTime = 0;
                this.alarmAudioInstance.src = '';
                this.alarmAudioInstance = null;
            }
        },

        toggleTimer() {
            this.timer.running = !this.timer.running;
            if (this.timer.running) this.stopAlarm();
        },

        resetTimer() {
            this.timer.running = false;
            this.timer.seconds = 300;
            this.stopAlarm();
        },

        setTimerPreset(s) {
            this.timer.seconds = Number(s) || 0;
            this.timer.running = false;
            this.stopAlarm();
        },

        get currentTrack() {
            return this.music.tracks[this.music.idx] || { title: '', src: '' };
        },

        _loadMusicPrefs() {
            try {
                const raw = localStorage.getItem(STORAGE_MUSIC);
                if (!raw) return;

                const saved = JSON.parse(raw);
                const idx = Number(saved.idx);
                const vol = Number(saved.volume);

                if (idx >= 0 && idx < this.music.tracks.length) this.music.idx = idx;
                if (vol >= 0 && vol <= 1) this.music.volume = vol;
            } catch (e) {}
        },

        saveMusic() {
            try {
                localStorage.setItem(STORAGE_MUSIC, JSON.stringify({
                    idx: this.music.idx,
                    volume: this.music.volume
                }));
            } catch (e) {}
        },

        loadCurrentTrack() {
            const m = this.music;

            if (!m.audio) {
                const audio = new Audio();
                audio.preload = 'none';
                audio.volume = m.volume;

                audio.onloadedmetadata = () => m.duration = Math.round(audio.duration);

                audio.ontimeupdate = () => {
                    const pos = Math.round(audio.currentTime || 0);
                    if (m.position !== pos) {
                        m.position = pos;
                        m.duration = m.duration || Math.round(audio.duration || 1);
                        m.progress = (pos / m.duration) * 100;
                    }
                };

                audio.onended = () => this.next();
                m.audio = audio;
            }

            const trackSrc = this.currentTrack.src;
            if (!m.audio.src || m.audio.src.indexOf(trackSrc) === -1) {
                m.audio.src = trackSrc;
            }
        },

        _broadcastAndPlay() {
            window.dispatchEvent(new CustomEvent(EVENT_AUDIO, { detail: { source: SRC_WIDGET } }));
            this.music.audio?.play?.().then(() => { this.music.playing = true; }).catch(() => {});
        },

        playPause() {
            const src = this.currentTrack.src;
            if (!this.music.audio?.src || this.music.audio.src.indexOf(src) === -1) {
                this.loadCurrentTrack();
            }

            if (this.music.playing) {
                this.music.audio?.pause?.();
                this.music.playing = false;
            } else {
                this._broadcastAndPlay();
            }
        },

        _switchTrack(idx) {
            this.music.idx = idx;
            this.saveMusic();

            if (!this.music.audio) return;

            this.music.audio.src = this.currentTrack.src;
            this._broadcastAndPlay();
        },

        next() {
            this._switchTrack((this.music.idx + 1) % this.music.tracks.length);
        },

        prev() {
            const len = this.music.tracks.length;
            this._switchTrack((this.music.idx - 1 + len) % len);
        },

        stopMusic() {
            if (this.music.audio) {
                this.music.audio.pause();
                this.music.audio.currentTime = 0;
            }
            this.music.playing = false;
            this.music.position = 0;
            this.music.progress = 0;
        },

        seek(e) {
            const pct = Number(e.target.value || this.music.progress) / 100;
            const t = (this.music.duration || 0) * pct;

            if (this.music.audio && this.music.duration) {
                this.music.audio.currentTime = t;
            }
        },

        setVolume() {
            if (this.music.audio) {
                this.music.audio.volume = this.music.volume;
            }
            this.saveMusic();
        },

        formatSeconds(s) {
            const n = s | 0;
            const m = (n / 60) | 0;
            const rm = n % 60;
            return (m < 10 ? '0' : '') + m + ':' + (rm < 10 ? '0' : '') + rm;
        }
    };
}
