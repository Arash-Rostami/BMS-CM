import { describe, expect, test, vi, beforeEach, afterEach } from 'vitest'
import landingPage from '../landing-page.js'

const makeComponent = () => {
    const c = landingPage()
    c.$watch = vi.fn()
    return c
}

beforeEach(() => {
    localStorage.clear()
    document.documentElement.className = ''
})

describe('landingPage.init', () => {
    test('reads dark mode from the truthy set and toggles html.dark', () => {
        for (const value of ['1', 'true', 'dark', 'on']) {
            localStorage.setItem('theme', value)
            document.documentElement.className = ''
            makeComponent().init()
            expect(document.documentElement.classList.contains('dark')).toBe(true)
        }

        localStorage.setItem('theme', 'light')
        makeComponent().init()
        expect(document.documentElement.classList.contains('dark')).toBe(false)
    })

    test('defaults the tab to workflow and only overrides on a saved value', () => {
        const c = makeComponent()
        c.init()
        expect(c.activeTab).toBe('workflow')

        localStorage.setItem('lp_tab', 'search')
        const saved = makeComponent()
        saved.init()
        expect(saved.activeTab).toBe('search')
    })

    test('restores the widget visibility pair independently', () => {
        localStorage.setItem('lp_widget_open', '1')
        localStorage.setItem('lp_widget_min', '0')

        const c = makeComponent()
        c.init()

        expect(c.widgetOpen).toBe(true)
        expect(c.widgetMinimized).toBe(false)
    })

    test('registers the four persistence watchers', () => {
        const c = makeComponent()
        c.init()

        expect(c.$watch).toHaveBeenCalledTimes(4)
        expect(c.$watch.mock.calls.map(([name]) => name)).toEqual(
            ['darkMode', 'activeTab', 'widgetMinimized', 'widgetOpen']
        )
    })

    test('reacts to the dark-mode-toggled window event', () => {
        const c = makeComponent()
        c.init()

        window.dispatchEvent(new CustomEvent('dark-mode-toggled', { detail: true }))
        expect(c.darkMode).toBe(true)

        window.dispatchEvent(new CustomEvent('dark-mode-toggled', { detail: false }))
        expect(c.darkMode).toBe(false)
    })
})

describe('landingPage watchers', () => {
    const watcher = (c, name) => c.$watch.mock.calls.find(([n]) => n === name)[1]

    test('darkMode watcher persists theme and toggles the html class', () => {
        const c = makeComponent()
        c.init()

        watcher(c, 'darkMode')(true)
        expect(localStorage.getItem('theme')).toBe('dark')
        expect(document.documentElement.classList.contains('dark')).toBe(true)

        watcher(c, 'darkMode')(false)
        expect(localStorage.getItem('theme')).toBe('light')
        expect(document.documentElement.classList.contains('dark')).toBe(false)
    })

    test.each([
        ['activeTab', 'customize', 'lp_tab'],
        ['widgetMinimized', true, 'lp_widget_min'],
        ['widgetOpen', false, 'lp_widget_open'],
    ])('%s watcher persists %p to %s', (name, value, key) => {
        const c = makeComponent()
        c.init()

        watcher(c, name)(value)

        const expected = name === 'activeTab' ? value : (value ? '1' : '0')
        expect(localStorage.getItem(key)).toBe(expected)
    })
})

describe('landingPage responsive panel', () => {
    afterEach(() => {
        window.innerWidth = 1024
    })

    test('the switcher panel starts closed and wide screens keep it inline', () => {
        const c = makeComponent()

        expect(c.panelOpen).toBe(false)
        expect(c.isWide).toBe(true)
    })

    test('a resize listener keeps isWide in sync with the viewport', () => {
        const c = makeComponent()
        c.init()

        window.innerWidth = 480
        window.dispatchEvent(new Event('resize'))
        expect(c.isWide).toBe(false)

        window.innerWidth = 1280
        window.dispatchEvent(new Event('resize'))
        expect(c.isWide).toBe(true)
    })
})