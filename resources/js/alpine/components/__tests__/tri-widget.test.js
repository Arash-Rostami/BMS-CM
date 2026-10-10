import { describe, expect, test, vi, beforeEach, afterEach } from 'vitest'
import triWidget from '../tri-widget.js'

class FakeAudio {
    constructor(src) {
        this.src = src ?? ''
        this.volume = 1
        this.loop = false
        this.preload = ''
        this.currentTime = 0
        this.play = vi.fn(() => Promise.resolve())
        this.pause = vi.fn()
    }
}

const makeComponent = () => {
    const c = triWidget()
    c.$watch = vi.fn()
    return c
}

beforeEach(() => {
    vi.useFakeTimers()
    localStorage.clear()
    globalThis.Audio = FakeAudio
})

afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
})

describe('triWidget clock', () => {
    test('the tick publishes the three date strings once per second', () => {
        const c = makeComponent()
        c.init()

        expect(c.clockString).not.toBe('')
        expect(c.dateString).not.toBe('')
        expect(c.shamsiDateString).not.toBe('')
    })
})

describe('triWidget timer', () => {
    test('counts down each second while running and stops at zero', () => {
        const c = makeComponent()
        c.init()
        c.setTimerPreset(3)
        c.toggleTimer()
        expect(c.timer.running).toBe(true)

        vi.advanceTimersByTime(2000)
        expect(c.timer.seconds).toBe(1)

        vi.advanceTimersByTime(1000)
        expect(c.timer.seconds).toBe(0)
        expect(c.timer.running).toBe(false)
    })

    test('hitting zero starts the alarm loop with a looping lazy Audio', () => {
        const c = makeComponent()
        c.init()
        c.setTimerPreset(1)
        c.toggleTimer()

        vi.advanceTimersByTime(1000)

        expect(c.alarmAudioInstance).toBeInstanceOf(FakeAudio)
        expect(c.alarmAudioInstance.src).toBe('/audio/alarm.mp3')
        expect(c.alarmAudioInstance.loop).toBe(true)
        expect(c.alarmAudioInstance.play).toHaveBeenCalled()
    })

    test('the alarm auto-stops after a minute and stopAlarm is idempotent', () => {
        const c = makeComponent()
        c.init()
        c.setTimerPreset(1)
        c.toggleTimer()
        vi.advanceTimersByTime(1000)

        const instance = c.alarmAudioInstance
        vi.advanceTimersByTime(60000)

        expect(c.alarmAudioInstance).toBeNull()
        expect(instance.pause).toHaveBeenCalled()

        c.stopAlarm()
        expect(c.alarmInterval).toBeNull()
    })

    test('the timer never counts while paused and reset restores 300', () => {
        const c = makeComponent()
        c.init()
        c.setTimerPreset(5)

        vi.advanceTimersByTime(3000)
        expect(c.timer.seconds).toBe(5)

        c.resetTimer()
        expect(c.timer.seconds).toBe(300)
        expect(c.timer.running).toBe(false)
    })

    test('formatSeconds renders mm:ss with zero padding', () => {
        const c = makeComponent()

        expect(c.formatSeconds(125)).toBe('02:05')
        expect(c.formatSeconds(0)).toBe('00:00')
        expect(c.formatSeconds(600)).toBe('10:00')
    })
})

describe('triWidget music prefs', () => {
    test('valid saved idx/volume are restored, invalid ones ignored', () => {
        localStorage.setItem('lp_music', JSON.stringify({ idx: 2, volume: 0.5 }))

        const c = makeComponent()
        c.init()

        expect(c.music.idx).toBe(2)
        expect(c.music.volume).toBe(0.5)

        localStorage.setItem('lp_music', JSON.stringify({ idx: 99, volume: 5 }))

        const c2 = makeComponent()
        c2.init()

        expect(c2.music.idx).toBe(0)
        expect(c2.music.volume).toBe(0.8)
    })

    test('next and prev wrap around and persist the selection', () => {
        const c = makeComponent()
        c.init()
        c.music.idx = 3

        c.next()
        expect(c.music.idx).toBe(0)

        c.prev()
        expect(c.music.idx).toBe(3)

        expect(JSON.parse(localStorage.getItem('lp_music')).idx).toBe(3)
    })

    test('loadCurrentTrack creates the Audio lazily once and reuses it for the same track', () => {
        const c = makeComponent()
        c.init()
        c.music.idx = 1

        c.loadCurrentTrack()
        const audio = c.music.audio

        expect(audio).toBeInstanceOf(FakeAudio)
        expect(audio.preload).toBe('none')
        expect(audio.src).toContain('/audio/music/vocale.m4a')

        c.loadCurrentTrack()
        expect(c.music.audio).toBe(audio)
        expect(audio.src).toContain('/audio/music/vocale.m4a')
    })

    test('switching tracks swaps the src on the same Audio instance', async () => {
        const c = makeComponent()
        c.init()
        c.music.idx = 0
        c.loadCurrentTrack()
        const audio = c.music.audio

        c.next()
        await Promise.resolve()

        expect(c.music.audio).toBe(audio)
        expect(audio.src).toContain('/audio/music/vocale.m4a')
        expect(c.music.playing).toBe(true)
    })

    test('playPause broadcasts lp-audio-play before playing, and pauses on the second press', async () => {
        const c = makeComponent()
        c.init()
        c.loadCurrentTrack()

        const heard = []
        const onPlay = (e) => heard.push(e.detail.source)
        window.addEventListener('lp-audio-play', onPlay)

        c.playPause()
        await Promise.resolve()
        expect(c.music.playing).toBe(true)
        expect(heard).toEqual(['widget'])

        c.playPause()
        expect(c.music.playing).toBe(false)
        expect(c.music.audio.pause).toHaveBeenCalled()
        window.removeEventListener('lp-audio-play', onPlay)
    })

    test('an external lp-audio-play pauses the widget player', () => {
        const c = makeComponent()
        c.init()
        c.loadCurrentTrack()
        c.music.playing = true

        window.dispatchEvent(new CustomEvent('lp-audio-play', { detail: { source: 'workflow' } }))

        expect(c.music.playing).toBe(false)
        expect(c.music.audio.pause).toHaveBeenCalled()
    })

    test('stopMusic resets the transport state', () => {
        const c = makeComponent()
        c.init()
        c.loadCurrentTrack()
        c.music.playing = true
        c.music.position = 42
        c.music.progress = 30

        c.stopMusic()

        expect(c.music.playing).toBe(false)
        expect(c.music.position).toBe(0)
        expect(c.music.progress).toBe(0)
    })

    test('destroy tears down the intervals, the listener and the alarm', () => {
        const c = makeComponent()
        c.init()
        c.loadCurrentTrack()
        c.music.playing = true
        c.startAlarmLoop()

        const instance = c.alarmAudioInstance
        c.destroy()

        expect(c.alarmAudioInstance).toBeNull()
        expect(instance.pause).toHaveBeenCalled()

        window.dispatchEvent(new CustomEvent('lp-audio-play', { detail: { source: 'workflow' } }))
        expect(c.music.audio.pause).not.toHaveBeenCalled()
    })
})