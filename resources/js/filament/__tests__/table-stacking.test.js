import { describe, expect, test, vi, beforeEach } from 'vitest'

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

const load = async () => {
    vi.resetModules()
    delete window.__tableStackingBound
    await import('../table-stacking.js')
    document.dispatchEvent(new Event('alpine:init'))
}

beforeEach(() => {
    localStorage.clear()
    document.documentElement.className = ''
    window.Alpine = fakeAlpine()
})

describe('table-stacking', () => {
    test('seeds the classic store from localStorage and applies the html class', async () => {
        localStorage.setItem('table_stacking', 'classic')

        await load()

        expect(window.Alpine.stores.tableStackingClassic).toBe(true)
        expect(document.documentElement.classList.contains('table-stacking-classic')).toBe(true)
    })

    test('defaults to stacked without a saved value', async () => {
        await load()

        expect(window.Alpine.stores.tableStackingClassic).toBe(false)
        expect(document.documentElement.classList.contains('table-stacking-classic')).toBe(false)
    })

    test('setTableStacking persists and toggles the html class', async () => {
        await load()

        window.setTableStacking('classic')
        expect(localStorage.getItem('table_stacking')).toBe('classic')
        expect(document.documentElement.classList.contains('table-stacking-classic')).toBe(true)

        window.setTableStacking('stacked')
        expect(localStorage.getItem('table_stacking')).toBe('stacked')
        expect(document.documentElement.classList.contains('table-stacking-classic')).toBe(false)
    })

    test('any non-classic mode is persisted as stacked', async () => {
        await load()

        window.setTableStacking('bogus')
        expect(localStorage.getItem('table_stacking')).toBe('stacked')
        expect(window.Alpine.stores.tableStackingClassic).toBe(false)
    })
})