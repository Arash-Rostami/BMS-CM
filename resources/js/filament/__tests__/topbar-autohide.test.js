import { describe, expect, test, vi, beforeEach, afterEach } from 'vitest'

let origAdd
let addedListeners

const load = async () => {
    vi.resetModules()
    delete window.__topbarPinBound
    await import('../topbar-autohide.js')
    document.dispatchEvent(new Event('alpine:init'))
}

const fakeAlpine = () => {
    const stores = {}
    const effects = []

    return {
        stores,
        store(name, value) {
            if (value !== undefined) {
                stores[name] = value
                for (const cb of effects) cb()
            }
            return stores[name]
        },
        effect(cb) { effects.push(cb); cb() },
    }
}

beforeEach(() => {
    vi.useFakeTimers()
    localStorage.clear()
    document.documentElement.className = ''
    window.Alpine = fakeAlpine()
    addedListeners = []
    origAdd = document.addEventListener.bind(document)
    document.addEventListener = (type, fn, opts) => {
        addedListeners.push([type, fn, opts])
        origAdd(type, fn, opts)
    }
})

afterEach(() => {
    vi.useRealTimers()
    for (const [type, fn, opts] of addedListeners) document.removeEventListener(type, fn, opts)
    document.addEventListener = origAdd
})

describe('topbar-autohide', () => {
    test('a pinned topbar keeps the pinned class and never flashes hidden', async () => {
        localStorage.setItem('topbar_pinned', '1')

        await load()

        expect(window.Alpine.stores.topbarPinned).toBe(true)
        expect(document.documentElement.classList.contains('topbar-pinned')).toBe(true)
        expect(document.documentElement.classList.contains('topbar-force-hidden')).toBe(false)
    })

    test('an unpinned topbar hides for 400ms then reveals', async () => {
        await load()

        expect(window.Alpine.stores.topbarPinned).toBe(false)
        expect(document.documentElement.classList.contains('topbar-pinned')).toBe(false)
        expect(document.documentElement.classList.contains('topbar-force-hidden')).toBe(true)

        vi.advanceTimersByTime(400)
        expect(document.documentElement.classList.contains('topbar-force-hidden')).toBe(false)
    })

    test('re-pinning clears the pending hide timer immediately', async () => {
        await load()

        window.Alpine.store('topbarPinned', true)
        document.dispatchEvent(new Event('livewire:navigated'))

        expect(document.documentElement.classList.contains('topbar-pinned')).toBe(true)
        expect(document.documentElement.classList.contains('topbar-force-hidden')).toBe(false)

        vi.advanceTimersByTime(1000)
        expect(document.documentElement.classList.contains('topbar-force-hidden')).toBe(false)
    })
})