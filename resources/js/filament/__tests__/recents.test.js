import { describe, expect, test, vi, beforeEach } from 'vitest'
import '../recents.js'

const MAP = {
    'purchase-requests': { label: 'Purchase Requests' },
    'payments': { label: 'Payments' },
}

const navigate = (path) => {
    window.history.pushState({}, '', path)
    document.dispatchEvent(new Event('livewire:navigated'))
}

const entries = () => JSON.parse(localStorage.getItem('recent_records') || '[]')

beforeEach(() => {
    localStorage.clear()
    window.BMS_RESOURCE_MAP = MAP
    window.history.pushState({}, '', '/')
})

describe('recents capture', () => {
    test('a record edit visit is stored with the map label and the bare number', () => {
        navigate('/dashboard/purchase-requests/12')

        expect(entries()).toEqual([{
            label: 'Purchase Requests',
            number: '12',
            url: '/dashboard/purchase-requests/12',
            slug: 'purchase-requests',
        }])
    })

    test('revisiting the same record url dedupes to one newest-first entry', () => {
        navigate('/dashboard/payments/3')
        navigate('/dashboard/payments/3')

        expect(entries()).toHaveLength(1)
        expect(entries()[0]).toMatchObject({ number: '3', url: '/dashboard/payments/3' })
    })

    test('non-record and unknown-slug paths are never recorded', () => {
        navigate('/dashboard')
        navigate('/dashboard/users/5')
        navigate('/dashboard/purchase-requests/not-an-id')

        expect(localStorage.getItem('recent_records')).toBeNull()
    })

    test('the list stays capped at 20 newest-first entries', () => {
        for (let i = 1; i <= 25; i++) {
            navigate(`/dashboard/payments/${i}`)
        }

        const list = entries()

        expect(list).toHaveLength(20)
        expect(list[0].number).toBe('25')
        expect(list.at(-1).number).toBe('6')
    })

    test('each capture announces recents-updated', () => {
        const heard = vi.fn()
        window.addEventListener('recents-updated', heard)

        navigate('/dashboard/payments/9')

        expect(heard).toHaveBeenCalledTimes(1)
        window.removeEventListener('recents-updated', heard)
    })
})