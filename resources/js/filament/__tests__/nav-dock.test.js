import { describe, expect, test, vi, beforeEach, afterEach } from 'vitest'

const fakeAlpine = () => {
    const stores = {
        sidebar: { open: vi.fn(), close: vi.fn() },
    }
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

const load = async () => {
    vi.resetModules()
    delete window.__navDockBound
    globalThis.requestAnimationFrame = cb => cb()
    globalThis.cancelAnimationFrame = vi.fn()
    await import('../nav-dock.js')
    document.dispatchEvent(new Event('alpine:init'))
}

let origAdd
let addedListeners

beforeEach(() => {
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
    for (const [type, fn, opts] of addedListeners) document.removeEventListener(type, fn, opts)
    document.addEventListener = origAdd
})

describe('nav-dock seeding and persistence', () => {
    test('seeds the store from a saved valid mode', async () => {
        localStorage.setItem('nav_dock', 'bottom')

        await load()

        expect(window.Alpine.stores.navDock).toBe('bottom')
        expect(document.documentElement.classList.contains('nav-dock-bottom')).toBe(true)
        expect(window.Alpine.stores.sidebar.close).toHaveBeenCalled()
    })

    test('an unknown saved value falls back to side', async () => {
        localStorage.setItem('nav_dock', 'diagonal')

        await load()

        expect(window.Alpine.stores.navDock).toBe('side')
        expect(document.documentElement.classList.contains('nav-dock-bottom')).toBe(false)
        expect(window.Alpine.stores.sidebar.open).toHaveBeenCalled()
    })

    test('setNavDock validates, persists and updates the store', async () => {
        await load()

        window.setNavDock('peek')
        expect(window.Alpine.stores.navDock).toBe('peek')
        expect(localStorage.getItem('nav_dock')).toBe('peek')
        expect(document.documentElement.classList.contains('nav-dock-peek')).toBe(true)

        window.setNavDock('bogus')
        expect(window.Alpine.stores.navDock).toBe('side')
        expect(localStorage.getItem('nav_dock')).toBe('side')
    })
})

describe('nav-dock html classes', () => {
    test('peek keeps the dock class and adds the peek class', async () => {
        localStorage.setItem('nav_dock', 'peek')

        await load()

        const classes = document.documentElement.classList
        expect(classes.contains('nav-dock-bottom')).toBe(true)
        expect(classes.contains('nav-dock-peek')).toBe(true)
    })
})

describe('nav-dock click delegation', () => {
    test('a .dock-min click in bottom mode drops to peek', async () => {
        localStorage.setItem('nav_dock', 'bottom')
        await load()

        const btn = document.createElement('button')
        btn.className = 'dock-min'
        document.body.appendChild(btn)
        btn.click()

        expect(window.Alpine.stores.navDock).toBe('peek')
        btn.remove()
    })

    test('a click inside .fi-sidebar in peek mode returns to bottom', async () => {
        localStorage.setItem('nav_dock', 'peek')
        await load()

        const sidebar = document.createElement('div')
        sidebar.className = 'fi-sidebar'
        const inner = document.createElement('span')
        sidebar.appendChild(inner)
        document.body.appendChild(sidebar)
        inner.click()

        expect(window.Alpine.stores.navDock).toBe('bottom')
        sidebar.remove()
    })

    test('side mode ignores both delegated clicks', async () => {
        await load()

        const btn = document.createElement('button')
        btn.className = 'dock-min'
        document.body.appendChild(btn)
        btn.click()

        expect(window.Alpine.stores.navDock).toBe('side')
        btn.remove()
    })
})

describe('nav-dock double-bind guard', () => {
    test('a second alpine:init does not re-seed or double-bind', async () => {
        await load()

        window.Alpine.stores.sidebar.close.mockClear()
        document.dispatchEvent(new Event('alpine:init'))

        expect(window.Alpine.stores.sidebar.close).not.toHaveBeenCalled()
    })
})