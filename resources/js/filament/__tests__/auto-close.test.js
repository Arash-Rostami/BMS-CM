import { describe, expect, test, vi, beforeEach } from 'vitest'

const load = async () => {
    vi.resetModules()
    delete window.__tablePanelAutocloseBound
    await import('../auto-close.js')
    document.dispatchEvent(new Event('alpine:init'))
}

const registeredHook = () => {
    const call = globalThis.Livewire.hook.mock.calls[0]
    return { hookName: call[0], handler: call[1] }
}

const dropdown = (data) => {
    const el = document.createElement('div')
    el.setAttribute('x-data', 'filamentDropdown')
    el._componentData = data ?? { close: vi.fn() }
    document.body.appendChild(el)
    return el
}

beforeEach(() => {
    globalThis.Livewire = { hook: vi.fn() }
    window.Alpine = { $data: el => el._componentData }
})

describe('auto-close', () => {
    test('registers one commit hook on alpine:init', async () => {
        await load()

        expect(globalThis.Livewire.hook).toHaveBeenCalledTimes(1)
        expect(registeredHook().hookName).toBe('commit')
    })

    test('an applyTableFilters commit closes every dropdown after success', async () => {
        await load()

        const first = dropdown()
        const second = dropdown()

        const { handler } = registeredHook()
        const succeed = vi.fn(cb => cb())

        handler({
            commit: { calls: [{ method: 'applyTableFilters' }] },
            succeed,
        })

        expect(succeed).toHaveBeenCalled()
        expect(first._componentData.close).toHaveBeenCalled()
        expect(second._componentData.close).toHaveBeenCalled()

        first.remove()
        second.remove()
    })

    test('a dropdown without a close method is skipped without throwing', async () => {
        await load()

        const bare = dropdown({})

        const { handler } = registeredHook()
        const succeed = vi.fn(cb => cb())

        expect(() => handler({
            commit: { calls: [{ method: 'applyTableFilters' }] },
            succeed,
        })).not.toThrow()
        expect(succeed).toHaveBeenCalled()

        bare.remove()
    })

    test('an applyTableColumnManager commit is also matched', async () => {
        await load()

        const { handler } = registeredHook()
        const succeed = vi.fn(cb => cb())

        handler({
            commit: { calls: [{ method: 'applyTableColumnManager' }] },
            succeed,
        })

        expect(succeed).toHaveBeenCalled()
    })

    test('an unrelated commit never touches the dropdowns', async () => {
        await load()

        const open = dropdown()

        const { handler } = registeredHook()
        const succeed = vi.fn(cb => cb())

        handler({
            commit: { calls: [{ method: 'deleteRecord' }] },
            succeed,
        })

        expect(succeed).not.toHaveBeenCalled()
        expect(open._componentData.close).not.toHaveBeenCalled()

        open.remove()
    })
})