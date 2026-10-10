import { describe, expect, test, vi, beforeEach, afterEach } from 'vitest'
import workflow from '../workflow.js'

class FakeAudio {
    constructor(src) {
        this.src = src
        this.play = vi.fn(() => Promise.resolve())
        this.pause = vi.fn()
        this.onended = null
    }
}

const GROUP_A = {
    key: 'payments', title: 'Payments', accent: 'blue', audio: '/audio/payments.mp3',
    tips: ['tip a1', 'tip a2'],
    terms: ['t'], process: [], dos: [], donts: [],
}

const GROUP_B = {
    key: 'shipments', title: 'Shipments', accent: 'green', audio: null,
    tips: ['tip b1'],
    terms: [], process: [], dos: [], donts: [],
}

const GROUPS = [GROUP_A, GROUP_B]

const makeComponent = (groups = GROUPS, motionReduce = false) => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: motionReduce })
    const c = workflow(groups)
    c.$watch = vi.fn()
    c.init()
    return c
}

beforeEach(() => {
    vi.useFakeTimers()
    globalThis.Audio = FakeAudio
})

afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
})

describe('workflow tips', () => {
    test('flattenTips carries each tip with its group accent and title', () => {
        const c = makeComponent()

        expect(c.tips.map(t => t.tip)).toEqual(['tip a1', 'tip a2', 'tip b1'])
        expect(c.tips[1]).toMatchObject({ accent: 'blue', title: 'Payments', key: 'payments' })
        expect(c.tips[2]).toMatchObject({ accent: 'green', title: 'Shipments' })
        expect(c.currentTip.tip).toBe('tip a1')
    })

    test('a missing groups payload flattens to no tips', () => {
        const c = makeComponent(null)

        expect(c.tips).toEqual([])
        expect(c.currentTip).toEqual({})
    })
})

describe('workflow rotation', () => {
    test('auto-rotates every 7 seconds and wraps around', () => {
        const c = makeComponent()

        vi.advanceTimersByTime(7000)
        expect(c.rotateIdx).toBe(1)

        vi.advanceTimersByTime(7000)
        expect(c.rotateIdx).toBe(2)

        vi.advanceTimersByTime(7000)
        expect(c.rotateIdx).toBe(0)
    })

    test('skips ticks while a modal is open, audio plays, or paused', () => {
        const c = makeComponent()

        c.textOpen = true
        vi.advanceTimersByTime(7000)
        expect(c.rotateIdx).toBe(0)

        c.textOpen = false
        c.videoOpen = true
        vi.advanceTimersByTime(7000)
        expect(c.rotateIdx).toBe(0)

        c.videoOpen = false
        c.playing = true
        vi.advanceTimersByTime(7000)
        expect(c.rotateIdx).toBe(0)

        c.playing = false
        c.togglePause()
        vi.advanceTimersByTime(7000)
        expect(c.rotateIdx).toBe(0)
        expect(c.paused).toBe(true)

        c.togglePause()
        vi.advanceTimersByTime(7000)
        expect(c.rotateIdx).toBe(1)
    })

    test('prefers-reduced-motion disables auto-rotation entirely', () => {
        const c = makeComponent(GROUPS, true)

        expect(c.rotateInterval).toBeNull()
    })

    test('nextTip and setTip navigate with bounds respect', () => {
        const c = makeComponent()

        c.nextTip()
        expect(c.rotateIdx).toBe(1)

        c.setTip(2)
        expect(c.rotateIdx).toBe(2)

        c.setTip(-1)
        c.setTip(99)
        expect(c.rotateIdx).toBe(2)
    })
})

describe('workflow modals', () => {
    test('openText/openVideo select the group and toggle their modal', () => {
        const c = makeComponent()

        c.openText(GROUP_B)
        expect(c.selected).toBe(GROUP_B)
        expect(c.textOpen).toBe(true)

        c.openVideo(GROUP_A)
        expect(c.videoOpen).toBe(true)
        expect(c.selected).toBe(GROUP_A)
    })

    test('openVideo stops any playing narration first', () => {
        const c = makeComponent()
        c.playPause(GROUP_A)
        const instance = c.audio

        c.openVideo(GROUP_B)

        expect(c.audio).toBeNull()
        expect(instance.pause).toHaveBeenCalled()
    })
})

describe('workflow narration', () => {
    test('playPause broadcasts lp-audio-play, creates the audio and flips playing', async () => {
        const c = makeComponent()
        const heard = []
        window.addEventListener('lp-audio-play', e => heard.push(e.detail.source))

        c.playPause(GROUP_A)
        await vi.advanceTimersByTimeAsync(0)

        expect(heard).toEqual(['workflow'])
        expect(c.audio.src).toBe('/audio/payments.mp3')
        expect(c.selected).toBe(GROUP_A)
        expect(c.playing).toBe(true)
    })

    test('toggling the same group off stops without restarting, without audio it never starts', async () => {
        const c = makeComponent()
        c.playPause(GROUP_A)
        await vi.advanceTimersByTimeAsync(0)
        const instance = c.audio

        c.playPause(GROUP_A)

        expect(c.playing).toBe(false)
        expect(c.audio).toBeNull()
        expect(instance.pause).toHaveBeenCalled()

        const before = c.audio
        c.playPause(GROUP_B)
        expect(c.playing).toBe(false)
        expect(c.audio).toBe(before)
    })

    test('an external lp-audio-play stops the narration', async () => {
        const c = makeComponent()
        c.playPause(GROUP_A)
        await vi.advanceTimersByTimeAsync(0)

        window.dispatchEvent(new CustomEvent('lp-audio-play', { detail: { source: 'widget' } }))

        expect(c.playing).toBe(false)
    })

    test('onended flips playing back off', async () => {
        const c = makeComponent()
        c.playPause(GROUP_A)
        await vi.advanceTimersByTimeAsync(0)

        c.audio.onended()

        expect(c.playing).toBe(false)
    })
})

describe('workflow accents and content gate', () => {
    test('accent helpers resolve the map with an empty fallback', () => {
        const c = makeComponent()

        expect(c.accentBg('blue')).toBe(c.accentMap.blue.bg)
        expect(c.accentText('green')).toBe(c.accentMap.green.text)
        expect(c.accentBg('purple')).toBe('')
    })

    test('hasContent mirrors the server-side guide gate', () => {
        const c = makeComponent()

        expect(c.hasContent(GROUP_A)).toBe(true)
        expect(c.hasContent(GROUP_B)).toBe(false)
        expect(c.hasContent(undefined)).toBe(false)
    })
})

describe('workflow.destroy', () => {
    test('clears the interval and removes the audio listener', async () => {
        const c = makeComponent()

        c.destroy()

        const before = c.rotateIdx
        vi.advanceTimersByTime(70000)
        expect(c.rotateIdx).toBe(before)

        c.playing = true
        window.dispatchEvent(new CustomEvent('lp-audio-play', { detail: { source: 'widget' } }))
        expect(c.playing).toBe(true)
    })
})