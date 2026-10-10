import { describe, expect, test, vi, beforeEach } from 'vitest'

const fakeAlpine = () => {
    const stores = {}

    return {
        stores,
        store(name, value) {
            if (value !== undefined) stores[name] = value
            return stores[name]
        },
        effect(cb) { cb() },
    }
}

const setFullscreenElement = (value) => {
    Object.defineProperty(document, 'fullscreenElement', {
        configurable: true,
        get: () => value,
    })
}

const load = async () => {
    vi.resetModules()
    delete window.__fullscreenBound
    document.exitFullscreen = vi.fn(() => Promise.resolve())
    document.documentElement.requestFullscreen = vi.fn(() => Promise.resolve())
    await import('../fullscreen.js')
    document.dispatchEvent(new Event('alpine:init'))
}

beforeEach(() => {
    setFullscreenElement(null)
    window.Alpine = fakeAlpine()
})

describe('fullscreen', () => {
    test('seeds the store from the actual browser state', async () => {
        setFullscreenElement(document.documentElement)

        await load()

        expect(window.Alpine.stores.isFullscreen).toBe(true)
    })

    test('toggleFullscreen enters when not fullscreen and exits when fullscreen', async () => {
        await load()

        window.toggleFullscreen()
        expect(document.documentElement.requestFullscreen).toHaveBeenCalled()

        setFullscreenElement(document.documentElement)
        window.toggleFullscreen()
        expect(document.exitFullscreen).toHaveBeenCalled()
    })

    test('an external fullscreenchange re-syncs the store to reality', async () => {
        await load()
        expect(window.Alpine.stores.isFullscreen).toBe(false)

        setFullscreenElement(document.documentElement)
        document.dispatchEvent(new Event('fullscreenchange'))
        expect(window.Alpine.stores.isFullscreen).toBe(true)

        setFullscreenElement(null)
        document.dispatchEvent(new Event('fullscreenchange'))
        expect(window.Alpine.stores.isFullscreen).toBe(false)
    })
})