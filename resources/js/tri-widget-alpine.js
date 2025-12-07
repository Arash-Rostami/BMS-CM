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
                {
                    title: 'Ambient Pop',
                    src: 'https://edef9.pcloud.com/cBZtFKUIAZqCLhG97ZkCcQZZBxQW0kZlXZZ825ZZUTNNVZjVZ9FZlpZAHZLLZOFZ9JZUVZ4RZCJZVLZa4ZWpZ3JZPr9AZ14YlYd4azFFCxsVXYFOao0InsLfy/bms.mp3',
                    time: '70 min 11 sec',
                    image: '/img/widget/pop.png'
                },
                {
                    title: 'LoFi',
                    src: 'https://edef10.pcloud.com/cBZow68BSZ8HavFgZkCcQZZawQW0kZlXZZ825ZZPxUhjZ28ZqFZbRZU4ZezZb8ZBFZkHZRHZ8LZLpZeHZXzZf4Zv8IBZjLrg2KtFAmzXWRKMqKHGrphd8LMk/3-HOUR%20STUDY%20WITH%20ME%F0%9F%8F%A1%20_%20calm%20lofi%20_%20A%20Rainy%20Evening%20in%20Tokyo%20_%20with%20countdown%2Balarm.mp3',
                    time: '177 min 31 sec',
                    image: '/img/widget/lofi.png'

                },
                {
                    title: 'Pomodoro',
                    src: 'https://edef12.pcloud.com/cBZO068BSZpJNvFgZkCcQZZxlQW0kZlXZZ825ZZpuKYmZwLZlZ9RZq0Z7JZN0ZiXZm5ZSkZjpZRJZz7Z4RZcXZB8IBZFisEfC6XFyjzUdqjXi6emjQn489y/2.5-HOUR%20STUDY%20WITH%20ME%20_%20%20calm%20piano%20_%20%F0%9F%9A%A2%20Yokohama%20Harbor%20at%20SUNSET%F0%9F%8C%83%20_%20with%20countdown%2Balarm.mp3',
                    time: '147 min 14 sec',
                    image: '/img/widget/pomodoro.png'
                },
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
            const tick = () => {
                const d = new Date();
                this.clockString = d.toLocaleTimeString();
                this.dateString = d.toLocaleDateString(undefined, {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric'
                });
                this.shamsiDateString = d.toLocaleDateString('fa-IR', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long',
                    numberingSystem: 'latn'
                });
            };
            tick();
            setInterval(tick, 1000);
            this.$watch('tab', (val) => {
                if (val === 'music') this.loadCurrentTrack();
            });
            setInterval(() => {
                if (this.timer.running && this.timer.seconds > 0) {
                    this.timer.seconds--;
                    if (this.timer.seconds === 0) {
                        this.timer.running = false;
                        this.startAlarmLoop();
                    }
                }
            }, 1000);
        },
        startAlarmLoop() {
            this.stopAlarm();
            const a = new Audio('/audio/' + this.alarm);
            a.loop = true;
            this.alarmAudioInstance = a;
            a.play().catch(() => {
            });
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
        loadCurrentTrack() {
            if (!this.music.audio) {
                this.music.audio = new Audio();
                this.music.audio.preload = 'none';
                this.music.audio.volume = this.music.volume;
                this.music.audio.onloadedmetadata = () => {
                    this.music.duration = Math.round(this.music.audio.duration);
                };
                this.music.audio.ontimeupdate = () => {
                    this.music.position = Math.round(this.music.audio.currentTime || 0);
                    this.music.progress = (this.music.position / (this.music.duration || 1)) * 100;
                };
                this.music.audio.onended = () => this.next();
            }
            if (!this.music.audio.src || !this.music.audio.src.includes(this.currentTrack.src)) this.music.audio.src = this.currentTrack.src;
        },
        playPause() {
            if (!this.music.audio || !this.music.audio.src || !this.music.audio.src.includes(this.currentTrack.src)) this.loadCurrentTrack();
            if (this.music.playing) {
                this.music.audio.pause();
                this.music.playing = false;
            } else {
                this.music.audio.play().then(() => this.music.playing = true).catch(() => {
                });
            }
        },
        next() {
            this.music.idx = (this.music.idx + 1) % this.music.tracks.length;
            if (this.music.audio) this.music.audio.src = this.currentTrack.src;
            if (this.music.audio) this.music.audio.play().then(() => this.music.playing = true).catch(() => {
            });
        },
        prev() {
            this.music.idx = (this.music.idx - 1 + this.music.tracks.length) % this.music.tracks.length;
            if (this.music.audio) this.music.audio.src = this.currentTrack.src;
            if (this.music.audio) this.music.audio.play().then(() => this.music.playing = true).catch(() => {
            });
        },
        seek(e) {
            const pct = Number(e.target.value || this.music.progress) / 100;
            const t = (this.music.duration || 0) * pct;
            if (this.music.audio && this.music.duration) this.music.audio.currentTime = t;
        },
        setVolume() {
            if (this.music.audio) this.music.audio.volume = this.music.volume;
        },
        formatSeconds(s) {
            s = Number(s || 0);
            const mm = Math.floor(s / 60).toString().padStart(2, '0');
            const ss = Math.floor(s % 60).toString().padStart(2, '0');
            return `${mm}:${ss}`;
        }
    };
}

