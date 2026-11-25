import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// The web puts the scripts of Modul::pridejJsSoubor() into <head> with defer, the admin at the end of the body.
// Both QR scripts look for their elements when they start; started too early they found nothing and quietly did
// nothing (the reader button did not react, the web login page did not ask whether the phone approved the login).

/** The markup the scripts look for, as model/Uzivatel/Registrace.php and web/moduly/prihlaseni.php render it. */
const STRANKA_PRIHLASENI = `
  <div data-prihlaseni-qr data-stav-url="https://gamecon.test/api/prihlaseniQr" data-po-prihlaseni="https://gamecon.test/prihlaska">
    <p data-prihlaseni-qr-zprava aria-live="polite">Kód platí 3 minuty.</p>
  </div>`
const STRANKA_CTECKY = `
  <button type="button" data-ctecka-qr data-adresa-schvaleni="https://gamecon.test/prihlasit-zarizeni" data-jsqr="jsQR.js">
    Načíst QR kód
  </button>
  <div data-ctecka-qr-okno hidden>
    <video playsinline muted></video>
    <p data-ctecka-qr-zprava aria-live="polite"></p>
    <button type="button" data-ctecka-qr-zavrit>Zavřít</button>
  </div>`

let stavDokumentu: DocumentReadyState
// Listeners a script leaves on the document would start it again on the next test's DOMContentLoaded.
let posluchaci: Array<[string, EventListenerOrEventListenerObject]>
let fetchMock: ReturnType<typeof vi.fn>

beforeEach(() => {
  Object.defineProperty(document, 'readyState', { configurable: true, get: () => stavDokumentu })
  posluchaci = []
  const pridej = document.addEventListener.bind(document)
  vi.spyOn(document, 'addEventListener').mockImplementation((typ, posluchac, volby) => {
    posluchaci.push([typ, posluchac!])
    pridej(typ, posluchac, volby)
  })
  vi.useFakeTimers()
  // an answer that never comes, the test only needs to know that the page asked
  fetchMock = vi.fn(() => new Promise<Response>(() => {}))
  vi.stubGlobal('fetch', fetchMock)
  document.body.innerHTML = ''
})

afterEach(() => {
  posluchaci.forEach(([typ, posluchac]) => document.removeEventListener(typ, posluchac))
  vi.restoreAllMocks()
  vi.useRealTimers()
  vi.unstubAllGlobals()
  delete (document as { readyState?: DocumentReadyState }).readyState
})

const SKRIPTY = {
  prihlaseni: () => import('../../../web/soubory/prihlaseni-qr.js'),
  ctecka: () => import('../../../web/soubory/ctecka-qr.js'),
}

/** Runs the script anew, as the browser does on every page load. */
async function spustSkript(skript: keyof typeof SKRIPTY) {
  vi.resetModules()
  await SKRIPTY[skript]()
}

describe('prihlaseni-qr.js se ptá na stav přihlášení', () => {
  it('počká na celou stránku, když běží dřív, než ji prohlížeč přečte', async () => {
    stavDokumentu = 'loading'
    await spustSkript('prihlaseni')

    document.body.innerHTML = STRANKA_PRIHLASENI
    vi.advanceTimersByTime(2000)
    expect(fetchMock).not.toHaveBeenCalled()

    stavDokumentu = 'interactive'
    document.dispatchEvent(new Event('DOMContentLoaded'))
    vi.advanceTimersByTime(2000)
    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock.mock.calls[0][0]).toBe('https://gamecon.test/api/prihlaseniQr')
  })

  it.each<DocumentReadyState>(['interactive', 'complete'])(
    'začne hned, když je stránka už přečtená (%s: defer, async, vložený později)',
    async (stav) => {
      stavDokumentu = stav
      document.body.innerHTML = STRANKA_PRIHLASENI
      await spustSkript('prihlaseni')

      vi.advanceTimersByTime(2000)
      expect(fetchMock).toHaveBeenCalledOnce()
    },
  )
})

describe('ctecka-qr.js otevře čtečku tlačítkem', () => {
  const tlacitko = () => document.querySelector<HTMLButtonElement>('[data-ctecka-qr]')!
  const okno = () => document.querySelector<HTMLElement>('[data-ctecka-qr-okno]')!

  it('počká na celou stránku, když běží dřív, než ji prohlížeč přečte', async () => {
    stavDokumentu = 'loading'
    await spustSkript('ctecka')

    document.body.innerHTML = STRANKA_CTECKY
    tlacitko().click()
    expect(okno().hidden).toBe(true)

    stavDokumentu = 'interactive'
    document.dispatchEvent(new Event('DOMContentLoaded'))
    tlacitko().click()
    expect(okno().hidden).toBe(false)
  })

  it.each<DocumentReadyState>(['interactive', 'complete'])(
    'tlačítko funguje hned, když je stránka už přečtená (%s: defer, async, vložený později)',
    async (stav) => {
      stavDokumentu = stav
      document.body.innerHTML = STRANKA_CTECKY
      await spustSkript('ctecka')

      tlacitko().click()
      expect(okno().hidden).toBe(false)
      expect(tlacitko().hidden).toBe(true)
      // jsdom has no camera, the reader says so instead of failing silently
      expect(document.querySelector('[data-ctecka-qr-zprava]')!.textContent).toContain('Fotoaparát')
    },
  )
})
