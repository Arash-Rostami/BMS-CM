import { describe, expect, test, vi, beforeEach, afterEach } from 'vitest'

const load = async () => {
    vi.resetModules()
    delete window.__filepondLocaleFixBound
    await import('../filepond-locale.js')
    document.dispatchEvent(new Event('alpine:init'))
}

const freshFilePond = () => {
    const setOptions = vi.fn()
    window.FilePond = { setOptions }
    return { setOptions }
}

beforeEach(() => {
    vi.useFakeTimers()
    window.FILEPOND_MAX_SIZE_LABEL = 'فایل بسیار حجیم است'
})

afterEach(() => {
    vi.useRealTimers()
    delete window.FilePond
})

describe('filepond-locale', () => {
    test('wraps setOptions immediately when FilePond already exists', async () => {
        const pond = freshFilePond()
        await load()

        expect(pond.setOptions).toHaveBeenCalledTimes(1)
        expect(pond.setOptions.mock.calls[0][0]).toMatchObject({
            labelMaxFileSizeExceeded: 'فایل بسیار حجیم است',
            labelMaxFileSize: 'فایل بسیار حجیم است',
        })

        window.FilePond.setOptions({ labelIdle: 'x' })

        expect(pond.setOptions).toHaveBeenCalledTimes(2)
        expect(pond.setOptions.mock.calls[1][0]).toMatchObject({
            labelIdle: 'x',
            labelMaxFileSizeExceeded: 'فایل بسیار حجیم است',
        })
    })

    test('every later call re-merges the label on top of the caller options', async () => {
        const pond = freshFilePond()
        await load()

        window.FilePond.setOptions({ labelIdle: 'a' })
        window.FilePond.setOptions({ labelIdle: 'b', labelButtonType: 'c' })

        expect(pond.setOptions.mock.calls[2][0]).toMatchObject({
            labelIdle: 'b',
            labelButtonType: 'c',
            labelMaxFileSizeExceeded: 'فایل بسیار حجیم است',
            labelMaxFileSize: 'فایل بسیار حجیم است',
        })
    })

    test('polls until FilePond appears, then stops polling', async () => {
        await load()

        const pond = freshFilePond()
        vi.advanceTimersByTime(200)

        expect(pond.setOptions).toHaveBeenCalled()

        const callsAfterApply = pond.setOptions.mock.calls.length
        vi.advanceTimersByTime(2000)
        expect(pond.setOptions.mock.calls.length).toBe(callsAfterApply)
    })

    test('gives up after ten seconds and never applies a late FilePond', async () => {
        await load()
        vi.advanceTimersByTime(10500)

        const pond = freshFilePond()
        vi.advanceTimersByTime(5000)

        expect(pond.setOptions).not.toHaveBeenCalled()
    })

    test('without a label nothing is ever patched', async () => {
        delete window.FILEPOND_MAX_SIZE_LABEL
        await load()

        const pond = freshFilePond()
        vi.advanceTimersByTime(2000)

        expect(pond.setOptions).not.toHaveBeenCalled()
    })
})