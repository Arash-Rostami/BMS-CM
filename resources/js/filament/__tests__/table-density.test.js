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
    delete window.__tableDensityBound
    await import('../table-density.js')
    document.dispatchEvent(new Event('alpine:init'))
}

beforeEach(() => {
    localStorage.clear()
    document.documentElement.className = ''
    window.Alpine = fakeAlpine()
})

describe('table-density', () => {
    test('seeds the compact store from localStorage and applies the html class', async () => {
        localStorage.setItem('table_density', 'compact')

        await load()

        expect(window.Alpine.stores.tableDensityCompact).toBe(true)
        expect(document.documentElement.classList.contains('table-density-compact')).toBe(true)
    })

    test('defaults to comfortable without a saved value', async () => {
        await load()

        expect(window.Alpine.stores.tableDensityCompact).toBe(false)
        expect(document.documentElement.classList.contains('table-density-compact')).toBe(false)
    })

    test('setTableDensity persists and toggles the html class', async () => {
        await load()

        window.setTableDensity('compact')
        expect(localStorage.getItem('table_density')).toBe('compact')
        expect(window.Alpine.stores.tableDensityCompact).toBe(true)
        expect(document.documentElement.classList.contains('table-density-compact')).toBe(true)

        window.setTableDensity('comfortable')
        expect(localStorage.getItem('table_density')).toBe('comfortable')
        expect(document.documentElement.classList.contains('table-density-compact')).toBe(false)
    })

    test('any non-compact mode is persisted as comfortable', async () => {
        await load()

        window.setTableDensity('bogus')
        expect(localStorage.getItem('table_density')).toBe('comfortable')
        expect(window.Alpine.stores.tableDensityCompact).toBe(false)
    })
})