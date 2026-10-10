import { describe, expect, test, vi, beforeEach } from 'vitest'

const load = () => {
    vi.resetModules()
    return import('../theme.js')
}

beforeEach(() => {
    localStorage.clear()
    delete document.documentElement.dataset.theme
    window.BMS_THEMES = ['slate', 'zinc', 'rose']
})

describe('theme.js setTheme', () => {
    test('a valid key persists, applies to <html data-theme> and dispatches palette-changed', async () => {
        await load()

        const heard = []
        window.addEventListener('palette-changed', e => heard.push(e.detail.palette))

        window.setTheme('zinc')

        expect(localStorage.getItem('theme_palette')).toBe('zinc')
        expect(document.documentElement.dataset.theme).toBe('zinc')
        expect(heard).toEqual(['zinc'])
    })

    test('an invalid key falls back to slate instead of persisting garbage', async () => {
        await load()

        window.setTheme('not-a-palette')

        expect(localStorage.getItem('theme_palette')).toBe('slate')
        expect(document.documentElement.dataset.theme).toBe('slate')
    })

    test('a missing BMS_THEMES whitelist also falls back to slate', async () => {
        window.BMS_THEMES = undefined
        await load()

        window.setTheme('zinc')

        expect(localStorage.getItem('theme_palette')).toBe('slate')
    })
})

describe('theme.js navigation re-apply', () => {
    test('a saved valid palette is applied at script execution without any event', async () => {
        localStorage.setItem('theme_palette', 'rose')

        await load()

        expect(document.documentElement.dataset.theme).toBe('rose')
    })

    test('a missing or invalid saved palette leaves <html> untouched at script execution', async () => {
        localStorage.setItem('theme_palette', 'garbage')

        await load()

        expect(document.documentElement.dataset.theme).toBeUndefined()
    })

    test('livewire:navigated re-applies a saved valid palette to <html>', async () => {
        localStorage.setItem('theme_palette', 'rose')
        delete document.documentElement.dataset.theme

        await load()
        document.dispatchEvent(new Event('livewire:navigated'))

        expect(document.documentElement.dataset.theme).toBe('rose')
    })

    test('livewire:navigating registers an onSwap callback that re-applies the palette after the body swap', async () => {
        localStorage.setItem('theme_palette', 'rose')
        delete document.documentElement.dataset.theme

        await load()

        const swapCallbacks = []
        document.dispatchEvent(new CustomEvent('livewire:navigating', {
            detail: { onSwap: cb => swapCallbacks.push(cb) },
        }))

        expect(swapCallbacks.length).toBeGreaterThan(0)
        swapCallbacks.forEach(cb => cb())
        expect(document.documentElement.dataset.theme).toBe('rose')
    })

    test('an invalid saved palette is never applied on navigation', async () => {
        localStorage.setItem('theme_palette', 'garbage')

        await load()
        document.dispatchEvent(new Event('livewire:navigated'))

        expect(document.documentElement.dataset.theme).toBeUndefined()
    })

    test('a storage event for theme_palette (another tab changed it) re-applies the palette', async () => {
        localStorage.setItem('theme_palette', 'rose')
        delete document.documentElement.dataset.theme

        await load()
        window.dispatchEvent(new StorageEvent('storage', { key: 'theme_palette' }))

        expect(document.documentElement.dataset.theme).toBe('rose')
    })

    test('a storage event for an unrelated key is ignored', async () => {
        localStorage.setItem('theme_palette', 'rose')
        delete document.documentElement.dataset.theme

        await load()
        delete document.documentElement.dataset.theme
        window.dispatchEvent(new StorageEvent('storage', { key: 'other_key' }))

        expect(document.documentElement.dataset.theme).toBeUndefined()
    })

    test('refocusing the tab (visibilitychange) re-applies the saved palette', async () => {
        localStorage.setItem('theme_palette', 'rose')
        await load()
        delete document.documentElement.dataset.theme

        document.dispatchEvent(new Event('visibilitychange'))

        expect(document.documentElement.dataset.theme).toBe('rose')
    })
})

describe('theme.js dark-mode cross-tab sync', () => {
    test('a storage event for theme (mode changed in another tab) re-applies it via theme-changed', async () => {
        localStorage.setItem('theme', 'dark')
        await load()

        const heard = []
        window.addEventListener('theme-changed', e => heard.push(e.detail))

        localStorage.setItem('theme', 'light')
        window.dispatchEvent(new StorageEvent('storage', { key: 'theme' }))

        expect(heard[heard.length - 1]).toBe('light')
    })

    test('the same sync tells the landing factory via dark-mode-toggled with a boolean', async () => {
        localStorage.setItem('theme', 'dark')
        await load()

        const toggles = []
        window.addEventListener('dark-mode-toggled', e => toggles.push(e.detail))

        window.dispatchEvent(new StorageEvent('storage', { key: 'theme' }))

        expect(toggles[toggles.length - 1]).toBe(true)
    })

    test('a mode that is neither dark nor light is never dispatched', async () => {
        localStorage.setItem('theme', 'garbage')
        await load()

        const heard = []
        window.addEventListener('theme-changed', e => heard.push(e.detail))

        window.dispatchEvent(new StorageEvent('storage', { key: 'theme' }))

        expect(heard).toEqual([])
    })
})

describe('theme.js dark-mode-slot self-heal', () => {
    test('a palette key sitting in the legacy dark-mode slot is removed at load', async () => {
        localStorage.setItem('theme', 'zinc')

        await load()

        expect(localStorage.getItem('theme')).toBeNull()
    })

    test('a real dark/light value in the legacy slot is left alone', async () => {
        localStorage.setItem('theme', 'dark')

        await load()

        expect(localStorage.getItem('theme')).toBe('dark')
    })
})