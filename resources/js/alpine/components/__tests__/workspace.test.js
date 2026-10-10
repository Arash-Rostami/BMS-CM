import { describe, expect, test, vi, beforeEach } from 'vitest'
import workspace from '../workspace.js'

const MODULES = [
    { id: 'purchase-requests', theme: 'from-blue-500 to-blue-600', icon: 'bi-cart', searchable: true },
    { id: 'payments', theme: 'from-emerald-500 to-emerald-600', icon: 'bi-cash', searchable: true },
    { id: 'bank-profiles', icon: 'bi-bank', searchable: false },
]

const makeComponent = (config = {}) => {
    const c = workspace({
        modules: MODULES,
        stats: { 'purchase-requests': 4, 'payments': 0 },
        recordsUrl: '/workspace/records/__RES__',
        ...config,
    })
    c.$watch = vi.fn()
    return c
}

const pin = (overrides = {}) => ({
    key: 'purchase-requests:12',
    resourceId: 'purchase-requests',
    recordId: 12,
    label: 'PR 12',
    subtitle: 'steel',
    url: '/dashboard/purchase-requests/12',
    ...overrides,
})

beforeEach(() => {
    localStorage.clear()
})

describe('workspace.readStorage', () => {
    test('returns empty for a missing key and for corrupted JSON', () => {
        const c = makeComponent()

        expect(c.readStorage()).toEqual({ modules: [], records: [] })

        localStorage.setItem('user_shortcuts', '{broken')
        expect(c.readStorage()).toEqual({ modules: [], records: [] })
    })

    test('migrates the legacy bare-array shape into modules ids', () => {
        localStorage.setItem('user_shortcuts', JSON.stringify([{ id: 'payments' }, { id: 'unknown' }, null]))

        const c = makeComponent()
        expect(c.readStorage()).toEqual({ modules: ['payments', 'unknown'], records: [] })
    })

    test('passes through the modern object shape', () => {
        localStorage.setItem('user_shortcuts', JSON.stringify({
            modules: ['payments'],
            records: [pin()],
        }))

        const c = makeComponent()
        expect(c.readStorage()).toEqual({ modules: ['payments'], records: [pin()] })
    })
})

describe('workspace.init', () => {
    test('filters saved module pins against the known module list', () => {
        localStorage.setItem('user_shortcuts', JSON.stringify({
            modules: ['payments', 'ghost-module'],
            records: [],
        }))

        const c = makeComponent()
        c.init()

        expect(c.pinnedModuleIds).toEqual(['payments'])
    })

    test('decorates saved record pins with the parent module icon and theme', () => {
        localStorage.setItem('user_shortcuts', JSON.stringify({
            modules: [],
            records: [pin({ icon: 'fallback-icon', theme: 'from-red-500 to-red-600' })],
        }))

        const c = makeComponent()
        c.init()

        expect(c.recordPins).toHaveLength(1)
        expect(c.recordPins[0].icon).toBe('bi-cart')
        expect(c.recordPins[0].theme).toBe('from-blue-500 to-blue-600')
    })

    test('each accordion opens only when its own section has pins', () => {
        localStorage.setItem('user_shortcuts', JSON.stringify({
            modules: ['payments'],
            records: [],
        }))

        const c = makeComponent()
        c.init()

        expect(c.modulesOpen).toBe(true)
        expect(c.recordsOpen).toBe(false)
    })

    test('seeds the record picker from the first searchable module', () => {
        const c = makeComponent()
        c.init()

        expect(c.pickerResource).toBe('purchase-requests')
        expect(c.pickerTheme).toBe('from-blue-500 to-blue-600')
    })

    test('falling back to the default theme without a searchable module', () => {
        const c = makeComponent({ modules: MODULES.filter(m => !m.searchable) })
        c.init()

        expect(c.pickerResource).toBe('')
        expect(c.pickerTheme).toBe('from-slate-500 to-slate-600')
    })
})

describe('workspace modules', () => {
    test('pin/unpin are idempotent and persist', () => {
        const c = makeComponent()
        c.init()

        c.pinModule('payments')
        c.pinModule('payments')
        expect(c.pinnedModuleIds).toEqual(['payments'])

        c.unpinModule('payments')
        expect(c.pinnedModuleIds).toEqual([])

        expect(JSON.parse(localStorage.getItem('user_shortcuts')))
            .toEqual({ modules: [], records: c.recordPins })
    })

    test('pinned/unpinned lists stay in registration order', () => {
        const c = makeComponent()
        c.init()
        c.pinModule('payments')

        expect(c.pinnedModules().map(m => m.id)).toEqual(['payments'])
        expect(c.unpinnedModules().map(m => m.id)).toEqual(['purchase-requests', 'bank-profiles'])
        expect(c.moduleStat('purchase-requests')).toBe(4)
        expect(c.moduleStat('unknown')).toBe(0)
    })
})

describe('workspace records', () => {
    test('add/remove/rename pin records with persistence', () => {
        const c = makeComponent()
        c.init()

        c.addRecord(pin())
        c.addRecord(pin())
        expect(c.recordPins).toHaveLength(1)

        c.renameRecord('purchase-requests:12', '  renamed  ')
        expect(c.recordPins[0].label).toBe('renamed')
        expect(c.editingKey).toBeNull()

        c.removeRecord('purchase-requests:12')
        expect(c.recordPins).toEqual([])
        expect(JSON.parse(localStorage.getItem('user_shortcuts'))).toEqual({ modules: [], records: [] })
    })

    test('an empty rename keeps the label and still closes editing', () => {
        const c = makeComponent()
        c.init()
        c.addRecord(pin())
        c.editingKey = 'purchase-requests:12'

        c.renameRecord('purchase-requests:12', '   ')

        expect(c.recordPins[0].label).toBe('PR 12')
        expect(c.editingKey).toBeNull()
    })

    test('isRecordPinned and decorateRecord fall back through parent, pin, default', () => {
        const c = makeComponent()

        const orphan = c.decorateRecord(pin({ resourceId: 'ghost', icon: 'own-icon' }))
        expect(orphan.icon).toBe('own-icon')
        expect(orphan.theme).toBe('from-slate-500 to-slate-600')
        expect(orphan.status).toBe('')

        const withStatus = c.decorateRecord(pin({ status: 'متوسط' }))
        expect(withStatus.status).toBe('متوسط')

        c.addRecord(pin({ key: 'k' }))
        expect(c.isRecordPinned('k')).toBe(true)
        expect(c.isRecordPinned('other')).toBe(false)
    })
})

describe('workspace.initials', () => {
    test.each([
        ['', '#'],
        ['   ', '#'],
        ['purchase request', 'PR'],
        ['bank-profiles', 'BP'],
        ['payments', 'PA'],
        ['a', 'A'],
        ['!!', '#'],
    ])('initials(%p) → %p', (input, expected) => {
        expect(makeComponent().initials(input)).toBe(expected)
    })
})

describe('workspace.searchRecords', () => {
    test('without a picker resource it clears the results and skips the fetch', async () => {
        const c = makeComponent()
        c.init()
        c.pickerResource = ''

        await c.searchRecords()

        expect(c.recordResults).toEqual([])
    })

    test('substitutes __RES__ and the query into the records url', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [{ key: 'k', label: 'found' }] }),
        })

        const c = makeComponent()
        c.init()
        c.recordQuery = 'steel'
        await c.searchRecords()

        expect(fetch).toHaveBeenCalledWith('/workspace/records/purchase-requests?q=steel', expect.objectContaining({
            signal: expect.any(AbortSignal),
        }))
        expect(c.recordResults).toEqual([{ key: 'k', label: 'found' }])
        expect(c.recordLoading).toBe(false)
    })

    test('an aborted request leaves the newer call state untouched', async () => {
        let resolveSecond
        globalThis.fetch = vi.fn()
            .mockImplementationOnce((_url, { signal }) => new Promise((_resolve, reject) => {
                signal.addEventListener('abort', () => reject(new DOMException('aborted', 'AbortError')))
            }))
            .mockImplementationOnce(() => new Promise(resolve => { resolveSecond = resolve }))

        const c = makeComponent()
        c.init()

        const first = c.searchRecords()
        const second = c.searchRecords()
        await Promise.allSettled([first])

        expect(c.recordLoading).toBe(true)

        resolveSecond({ ok: true, json: () => Promise.resolve({ data: [{ key: 'newer' }] }) })
        await second

        expect(c.recordResults).toEqual([{ key: 'newer' }])
        expect(c.recordLoading).toBe(false)
        expect(c.recordError).toBe(false)
    })

    test('a failed response raises the error flag and empties the results', async () => {
        globalThis.fetch = vi.fn().mockRejectedValue(new Error('boom'))

        const c = makeComponent()
        c.init()
        await c.searchRecords()

        expect(c.recordError).toBe(true)
        expect(c.recordResults).toEqual([])
        expect(c.recordLoading).toBe(false)
    })

    test('selecting another resource resets the query and results, then searches', async () => {
        const calls = []
        globalThis.fetch = vi.fn((url) => {
            calls.push(url)
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ data: [] }) })
        })

        const c = makeComponent()
        c.init()
        c.recordQuery = 'old'
        c.recordResults = [{ stale: true }]

        await c.selectResource('payments')

        expect(c.pickerResource).toBe('payments')
        expect(c.pickerTheme).toBe('from-emerald-500 to-emerald-600')
        expect(c.recordQuery).toBe('')
        expect(c.recordResults).toEqual([])
        expect(calls[0]).toContain('/workspace/records/payments?q=')
    })
})

describe('workspace.recentCandidates', () => {
    const ROUTED_MODULES = [
        { id: 'purchaseRequests', searchable: true, label: 'Purchase Requests', route: '/dashboard/purchase-requests', theme: 'from-blue-500 to-blue-600', icon: 'bi-cart' },
        { id: 'payments', searchable: true, label: 'Payments', route: '/dashboard/payments', theme: 'from-emerald-500 to-emerald-600', icon: 'bi-cash' },
    ]

    const component = () => {
        const c = makeComponent({ modules: ROUTED_MODULES })
        c.init()
        return c
    }

    const seedRecents = (entries) => localStorage.setItem('recent_records', JSON.stringify(entries))

    test('maps stored recents of the selected resource to pin candidates', () => {
        seedRecents([
            { label: 'Purchase Requests', number: '12', url: '/dashboard/purchase-requests/12/edit', slug: 'purchase-requests' },
            { label: 'Payments', number: '7', url: '/dashboard/payments/7', slug: 'payments' },
            { label: 'Purchase Requests', number: '99', url: '/dashboard/purchase-requests/99', slug: 'purchase-requests' },
            { label: 'Broken', slug: 'purchase-requests' },
        ])

        const c = component()

        expect(c.recentCandidates()).toEqual([
            { key: 'purchaseRequests:12', resourceId: 'purchaseRequests', recordId: 12, label: 'Purchase Requests', number: '12', url: '/dashboard/purchase-requests/12/edit', subtitle: '' },
            { key: 'purchaseRequests:99', resourceId: 'purchaseRequests', recordId: 99, label: 'Purchase Requests', number: '99', url: '/dashboard/purchase-requests/99', subtitle: '' },
        ])
    })

    test('excludes already pinned rows and rows the server list already shows', () => {
        seedRecents([
            { label: 'Purchase Requests', number: '12', url: '/dashboard/purchase-requests/12', slug: 'purchase-requests' },
            { label: 'Purchase Requests', number: '99', url: '/dashboard/purchase-requests/99', slug: 'purchase-requests' },
        ])

        const c = component()
        c.addRecord({ key: 'purchaseRequests:12', resourceId: 'purchaseRequests', recordId: 12, label: 'PR 12', subtitle: '', url: '/dashboard/purchase-requests/12' })
        c.recordResults = [{ key: 'purchaseRequests:99', label: 'server row' }]

        expect(c.recentCandidates()).toEqual([])
    })

    test('hides recents while a search term is set or no resource is picked', () => {
        seedRecents([{ label: 'Purchase Requests', number: '12', url: '/dashboard/purchase-requests/12', slug: 'purchase-requests' }])

        const c = component()
        c.recordQuery = 'steel'
        expect(c.recentCandidates()).toEqual([])

        c.recordQuery = ''
        c.pickerResource = ''
        expect(c.recentCandidates()).toEqual([])
    })

    test('addRecent pins the candidate under a numbered label', () => {
        seedRecents([{ label: 'Purchase Requests', number: '12', url: '/dashboard/purchase-requests/12/edit', slug: 'purchase-requests' }])

        const c = component()
        const [candidate] = c.recentCandidates()
        c.addRecent(candidate)

        expect(c.recordPins).toHaveLength(1)
        expect(c.recordPins[0].key).toBe('purchaseRequests:12')
        expect(c.recordPins[0].label).toBe('Purchase Requests #12')
        expect(JSON.parse(localStorage.getItem('user_shortcuts')).records).toHaveLength(1)
    })

    test('rejects a tampered recent whose url is outside /dashboard/', () => {
        seedRecents([
            { label: 'Purchase Requests', number: '12', url: '/dashboard/purchase-requests/12', slug: 'purchase-requests' },
            { label: 'Purchase Requests', number: '5', url: 'javascript:alert(1)/5', slug: 'purchase-requests' },
            { label: 'Purchase Requests', number: '6', url: 'https://evil.example/dashboard/purchase-requests/6', slug: 'purchase-requests' },
        ])

        const c = component()

        expect(c.recentCandidates().map(r => r.key)).toEqual(['purchaseRequests:12'])
    })
})