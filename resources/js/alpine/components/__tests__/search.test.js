import { describe, expect, test, vi, beforeEach } from 'vitest'
import search from '../search.js'

const makeComponent = () => {
    const c = search()
    c.$watch = vi.fn()
    return c
}

const okResponse = (data) => ({ data })

beforeEach(() => {
    vi.restoreAllMocks()
    globalThis.axios = { get: vi.fn() }
})

describe('search rings', () => {
    test('the stroke offsets map progress 0-100 onto the ring circumference', () => {
        const c = makeComponent()

        expect(c.getOffset(0)).toBeCloseTo(2 * Math.PI * 16)
        expect(c.getOffset(100)).toBe(0)
        expect(c.getOffset(50)).toBeCloseTo(Math.PI * 16)

        expect(c.getOffsetL(0)).toBeCloseTo(2 * Math.PI * 22)
        expect(c.getOffsetL(100)).toBe(0)
    })
})

describe('search.breadcrumbStages', () => {
    test('defaults to the 8-stage pipeline with isLast on the last stage only', () => {
        const c = makeComponent()

        const stages = c.breadcrumbStages()

        expect(stages).toHaveLength(8)
        expect(stages.map(s => s.key)).toEqual([
            'purchaseRequest', 'proformaInvoice', 'purchaseOrder', 'registeredOrder',
            'bankProfile', 'payment', 'shipment', 'custom',
        ])
        expect(stages.at(-1).isLast).toBe(true)
        expect(stages.filter(s => s.isLast)).toHaveLength(1)
        expect(stages[0]).toMatchObject({ state: 'upcoming', label: 'Purchase Request' })
    })
})

describe('search.performSearch', () => {
    test('clears everything for queries shorter than two characters without calling the API', async () => {
        const c = makeComponent()
        c.searchQuery = 'a'
        c.results = [{ title: 'stale' }]

        await c.performSearch()

        expect(c.results).toEqual([])
        expect(c.selectedResult).toBeNull()
        expect(c.byUser).toBeNull()
        expect(c.isSearching).toBe(false)
        expect(axios.get).not.toHaveBeenCalled()
    })

    test('stores the spotlight results and the by_user payload', async () => {
        axios.get.mockResolvedValue(okResponse({ results: [{ title: 'PR-1' }], by_user: 'alice' }))

        const c = makeComponent()
        c.searchQuery = 'PR'
        await c.performSearch()

        expect(axios.get).toHaveBeenCalledWith('/api/search/spotlight', {
            params: { q: 'PR' },
            signal: expect.any(AbortSignal),
        })
        expect(c.results).toEqual([{ title: 'PR-1' }])
        expect(c.byUser).toBe('alice')
        expect(c.isSearching).toBe(false)
    })

    test('a superseded search never clobbers the newer call state', async () => {
        axios.get.mockImplementationOnce((_url, { signal }) => new Promise((_resolve, reject) => {
            signal.addEventListener('abort', () => setTimeout(() => reject(new Error('canceled')), 0))
        }))
        axios.get.mockResolvedValueOnce(okResponse({ results: [{ title: 'second' }] }))

        const c = makeComponent()
        c.searchQuery = 'PR'
        const first = c.performSearch()
        const second = c.performSearch()

        await Promise.allSettled([first, second])

        expect(c.results).toEqual([{ title: 'second' }])
        expect(c.isSearching).toBe(false)
    })

    test('a real failure empties the results without throwing', async () => {
        axios.get.mockRejectedValue(new Error('boom'))

        const c = makeComponent()
        c.searchQuery = 'PR'
        await c.performSearch()

        expect(c.results).toEqual([])
        expect(c.isSearching).toBe(false)
    })
})

describe('search.selectResult', () => {
    test('selects the result but skips the chain call without type/id', async () => {
        const c = makeComponent()
        c.breadcrumb = { custom: { state: 'upcoming', label: 'Custom' } }

        await c.selectResult({ title: 'bare' })

        expect(c.selectedResult).toEqual({ title: 'bare' })
        expect(c.chain).toEqual([])
        expect(axios.get).not.toHaveBeenCalled()
    })

    test('loads the chain and replaces the breadcrumb wholesale', async () => {
        const breadcrumb = { payment: { state: 'done', label: 'Payment' } }
        axios.get.mockResolvedValue(okResponse({ chain: [{ title: 'RO-1' }], breadcrumb }))

        const c = makeComponent()
        await c.selectResult({ type: 'registered_order', id: 7 })

        expect(axios.get).toHaveBeenCalledWith('/api/search/chain', {
            params: { type: 'registered_order', id: 7 },
            signal: expect.any(AbortSignal),
        })
        expect(c.chain).toEqual([{ title: 'RO-1' }])
        expect(c.breadcrumb).toEqual(breadcrumb)
        expect(c.chainLoading).toBe(false)

        const stages = c.breadcrumbStages()
        expect(stages).toHaveLength(1)
        expect(stages[0]).toMatchObject({ key: 'payment', state: 'done', isLast: true })
    })

    test('a chain failure raises the error flag without throwing', async () => {
        axios.get.mockRejectedValue(new Error('boom'))

        const c = makeComponent()
        await c.selectResult({ type: 'shipment', id: 3 })

        expect(c.chain).toEqual([])
        expect(c.chainError).toBe(true)
        expect(c.chainLoading).toBe(false)
    })

    test('a superseded chain load never clobbers the newer call state', async () => {
        axios.get.mockImplementationOnce((_url, { signal }) => new Promise((_resolve, reject) => {
            signal.addEventListener('abort', () => setTimeout(() => reject(new Error('canceled')), 0))
        }))
        axios.get.mockResolvedValueOnce(okResponse({ chain: [{ title: 'second' }] }))

        const c = makeComponent()
        const first = c.selectResult({ type: 'a', id: 1 })
        const second = c.selectResult({ type: 'b', id: 2 })

        await Promise.allSettled([first, second])

        expect(c.chain).toEqual([{ title: 'second' }])
        expect(c.chainError).toBe(false)
        expect(c.chainLoading).toBe(false)
    })
})

describe('search.clearSelected', () => {
    test('resets the selection and chain', async () => {
        const c = makeComponent()
        c.selectedResult = { title: 'PR-1' }
        c.chain = [{ title: 'RO-1' }]
        c.chainError = true

        c.clearSelected()

        expect(c.selectedResult).toBeNull()
        expect(c.chain).toEqual([])
        expect(c.chainError).toBe(false)
    })
})