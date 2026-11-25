import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { Nahled } from './nahled'

describe('náhled při psaní', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('počká, až se chvíli nepíše, a načte jen poslední text', async () => {
    const nacti = vi.fn().mockResolvedValue('<p>hotovo</p>')
    const vykresli = vi.fn()
    const nahled = new Nahled(nacti, vykresli, vi.fn(), 400)

    nahled.naplanuj('A')
    await vi.advanceTimersByTimeAsync(200)
    nahled.naplanuj('Ah')
    await vi.advanceTimersByTimeAsync(399)
    expect(nacti).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1)
    expect(nacti).toHaveBeenCalledTimes(1)
    expect(nacti.mock.calls[0][0]).toBe('Ah')
    expect(vykresli).toHaveBeenCalledWith('<p>hotovo</p>')
  })

  it('pomalejší starší odpověď nepřepíše novější náhled', async () => {
    const odpovedi: ((html: string) => void)[] = []
    const signaly: AbortSignal[] = []
    const nacti = vi.fn((_text: string, signal: AbortSignal) => {
      signaly.push(signal)
      return new Promise<string>((vyres) => odpovedi.push(vyres))
    })
    const vykresli = vi.fn()
    const nahled = new Nahled(nacti, vykresli, vi.fn(), 10)

    nahled.naplanuj('starý')
    await vi.advanceTimersByTimeAsync(10)
    nahled.naplanuj('nový')
    await vi.advanceTimersByTimeAsync(10)
    expect(signaly[0].aborted).toBe(true)

    odpovedi[1]('<p>nový</p>')
    odpovedi[0]('<p>starý</p>')
    await vi.advanceTimersByTimeAsync(0)
    expect(vykresli).toHaveBeenCalledTimes(1)
    expect(vykresli).toHaveBeenCalledWith('<p>nový</p>')
  })

  it('chybu ohlásí, zrušení ne', async () => {
    const priChybe = vi.fn()
    const nacti = vi.fn()
      .mockImplementationOnce((_text: string, signal: AbortSignal) => new Promise((_vyres, odmitni) => {
        signal.addEventListener('abort', () => odmitni(new DOMException('Aborted', 'AbortError')))
      }))
      .mockRejectedValueOnce(new Error('Spojení selhalo'))
    const nahled = new Nahled(nacti, vi.fn(), priChybe, 10)

    nahled.naplanuj('první')
    await vi.advanceTimersByTimeAsync(10)
    nahled.naplanuj('druhý')
    await vi.advanceTimersByTimeAsync(10)

    expect(priChybe).toHaveBeenCalledTimes(1)
    expect(priChybe).toHaveBeenCalledWith(new Error('Spojení selhalo'))
  })
})
