const CLOCK_DATE_OPTS = {weekday: 'short', month: 'short', day: 'numeric'};
const SHAMSI_OPTS = {year: 'numeric', month: 'long', day: 'numeric', weekday: 'long', numberingSystem: 'latn'};

export default function triWidget() {
    return {
        tab: 'clock',
        clockString: '',
        dateString: '',
        shamsiDateString: '',
        timer: {running: false, seconds: 300},
        customMins: null,
        alarm: 'alarm.mp3',
        alarmInterval: null,
        alarmAudioInstance: null,
        music: {
            tracks: [
                {title: 'LoFi', src: '/audio/music/lofi.m4a', time: '61 min 14 sec', image: '/img/widget/lofi.png'},
                {title: 'Vocale', src: '/audio/music/vocale.m4a', time: '95 min 55 sec', image: '/img/widget/pop.png'},
                {title: 'Pomodoro', src: '/audio/music/pomodoro.mp3', time: '147 min 15 sec', image: '/img/widget/pomodoro.png'},
                {title: 'Electronic', src: '/audio/music/electronic.m4a', time: '120 min 55 sec', image: '/img/widget/unnamed.png'},
            ],
            idx: 0,
            audio: null,
            playing: false,
            position: 0,
            duration: 0,
            progress: 0,
            volume: 0.8
        },

        init() {
            this._loadMusicPrefs();
            this._tick();
            this._clockTimerId = setInterval(() => this._tick(), 1000);
            this._countdownTimerId = setInterval(() => this._countdownTick(), 1000);

            this._onExternalPlay = (e) => {
                if (e.detail.source !== 'widget' && this.music.playing) {
                    this.music.audio?.pause();
                    this.music.playing = false;
                }
            };
            window.addEventListener('lp-audio-play', this._onExternalPlay);

            this.$watch('tab', (val) => {
                if (val === 'music') this.loadCurrentTrack();
            });
        },

        destroy() {
            clearInterval(this._clockTimerId);
            clearInterval(this._countdownTimerId);
            window.removeEventListener('lp-audio-play', this._onExternalPlay);
            this.stopAlarm();
        },

        _tick() {
            const d = new Date();
            this.clockString = d.toLocaleTimeString();
            this.dateString = d.toLocaleDateString(undefined, CLOCK_DATE_OPTS);
            this.shamsiDateString = d.toLocaleDateString('fa-IR', SHAMSI_OPTS);
        },

        _countdownTick() {
            if (!this.timer.running || this.timer.seconds <= 0) return;
            this.timer.seconds--;
            if (this.timer.seconds === 0) {
                this.timer.running = false;
                this.startAlarmLoop();
            }
        },

        startAlarmLoop() {
            this.stopAlarm();
            const a = new Audio('/audio/' + this.alarm);
            a.loop = true;
            this.alarmAudioInstance = a;
            a.play().catch(() => {});
            this.alarmInterval = setInterval(() => this.stopAlarm(), 60000);
        },

        stopAlarm() {
            if (this.alarmInterval) {
                clearInterval(this.alarmInterval);
                this.alarmInterval = null;
            }
            if (this.alarmAudioInstance) {
                this.alarmAudioInstance.pause();
                this.alarmAudioInstance.currentTime = 0;
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
            return this.music.tracks[this.music.idx] || {title: '', src: ''};
        },

        _loadMusicPrefs() {
            try {
                const saved = JSON.parse(localStorage.getItem('lp_music') || 'null');
                if (!saved) return;
                if (typeof saved.idx === 'number' && saved.idx >= 0 && saved.idx < this.music.tracks.length) this.music.idx = saved.idx;
                if (typeof saved.volume === 'number') this.music.volume = Math.min(1, Math.max(0, saved.volume));
            } catch (e) {}
        },

        saveMusic() {
            try {
                localStorage.setItem('lp_music', JSON.stringify({idx: this.music.idx, volume: this.music.volume}));
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
                    m.position = Math.round(audio.currentTime || 0);
                    m.progress = (m.position / (m.duration || 1)) * 100;
                };
                audio.onended = () => this.next();
                m.audio = audio;
            }
            if (!m.audio.src || !m.audio.src.includes(this.currentTrack.src)) m.audio.src = this.currentTrack.src;
        },

        _broadcastAndPlay() {
            window.dispatchEvent(new CustomEvent('lp-audio-play', {detail: {source: 'widget'}}));
            this.music.audio.play().then(() => this.music.playing = true).catch(() => {});
        },

        playPause() {
            if (!this.music.audio?.src || !this.music.audio.src.includes(this.currentTrack.src)) this.loadCurrentTrack();
            if (this.music.playing) {
                this.music.audio.pause();
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
            this._switchTrack((this.music.idx - 1 + this.music.tracks.length) % this.music.tracks.length);
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
            if (this.music.audio && this.music.duration) this.music.audio.currentTime = t;
        },

        setVolume() {
            if (this.music.audio) this.music.audio.volume = this.music.volume;
            this.saveMusic();
        },

        formatSeconds(s) {
            s = Number(s || 0);
            const mm = Math.floor(s / 60).toString().padStart(2, '0');
            const ss = Math.floor(s % 60).toString().padStart(2, '0');
            return `${mm}:${ss}`;
        }
    };
}
