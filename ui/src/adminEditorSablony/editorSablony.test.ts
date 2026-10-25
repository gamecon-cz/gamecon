import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
// The editor is a plain script like its logic: it needs the logic on window and the real template of the form.
import logika from '../../../admin/files/editor-sablony-logika.js'

// Not `new URL(..., import.meta.url)`: Vite would take a computed path for an asset import and try to load the whole directory.
const zRepozitare = (cesta: string) => resolve(import.meta.dirname, '../../..', cesta)
const sablonaEditoru = readFileSync(zRepozitare('model/Kanaly/EmailovaSablona/templates/editor.xtpl'), 'utf-8')

const escapuj = (text: string) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

/** Fills the XTemplate placeholders and blocks the way EditorSablonyHtml::vykresli() does. */
function vykresliSablonu(predmet: string, text: string, chyby: string[], testEmail?: string, schrankaUrl?: string): string {
  const data = {
    promenne: [{ nazev: 'jmeno', popis: 'jméno příjemce', ukazka: 'Jana', vPredmetu: false }],
    systemove: [],
    neverejne: [],
  }
  const hodnoty: Record<string, string> = {
    predmet: escapuj(predmet),
    text: escapuj(text),
    dataJson: JSON.stringify(data),
    nahledUrl: 'nahled',
    testUrl: 'test',
    zpetUrl: 'seznam',
    zpetNazev: 'Zpět na seznam e-mailů',
    cssVerze: '1',
    logikaVerze: '1',
    scriptVerze: '1',
  }

  return sablonaEditoru
    .replace(/<!-- (begin|end): editor -->/g, '')
    .replace(/<!-- begin: chyby -->([\s\S]*?)<!-- end: chyby -->/, (_celek: string, blok: string) =>
      chyby.length === 0
        ? ''
        : blok.replace(/<!-- begin: chyba -->([\s\S]*?)<!-- end: chyba -->/, (_c: string, polozka: string) =>
            chyby.map((chyba) => polozka.replace('{chyba}', escapuj(chyba))).join(''),
          ),
    )
    // The test e-mail button and its confirmation are rendered only for a page that offers sending a test
    .replace(/<!-- begin: (testTlacitko|testDialog) -->([\s\S]*?)<!-- end: \1 -->/g, (_celek: string, _nazev: string, blok: string) =>
      testEmail === undefined ? '' : blok.replace('{testEmail}', escapuj(testEmail)),
    )
    // The Open mailbox button is rendered only on an environment that catches e-mails
    .replace(/<!-- begin: schrankaTlacitko -->([\s\S]*?)<!-- end: schrankaTlacitko -->/, (_celek: string, blok: string) =>
      schrankaUrl === undefined ? '' : blok.replace('{schrankaUrl}', escapuj(schrankaUrl)),
    )
    .replace(/\{(\w+)\}/g, (_celek: string, nazev: string) => hodnoty[nazev] ?? '')
}

/**
 * @param obnovenyText what the browser puts back into the text field before the script starts (Firefox does)
 * @param testEmail the address of the logged-in admin, when the page offers sending a test e-mail
 * @param schrankaUrl where the mailbox of caught e-mails is, on an environment that has one
 */
async function otevriEditor(
  predmet = 'Obnova hesla',
  text = 'Ahoj %jmeno%, nové heslo je v odkazu.',
  chyby: string[] = [],
  obnovenyText?: string,
  testEmail?: string,
  schrankaUrl?: string,
) {
  document.body.innerHTML = vykresliSablonu(predmet, text, chyby, testEmail, schrankaUrl)
  if (obnovenyText !== undefined) {
    document.querySelector<HTMLTextAreaElement>('.editorSablony_text')!.value = obnovenyText
  }
  // jsdom has the <dialog> element but not showModal() and close()
  document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.showModal = () => dialog.setAttribute('open', '')
    dialog.close = () => dialog.removeAttribute('open')
  })
  Object.assign(window, { EditorSablonyLogika: logika })
  // The script starts itself on load, so every test needs it evaluated again against its own page.
  vi.resetModules()
  await import('../../../admin/files/editor-sablony.js')

  return {
    formular: document.querySelector<HTMLFormElement>('.editorSablony_formular')!,
    predmetPole: document.querySelector<HTMLInputElement>('.editorSablony_predmet')!,
    textPole: document.querySelector<HTMLTextAreaElement>('.editorSablony_text')!,
  }
}

function pis(pole: HTMLInputElement | HTMLTextAreaElement, hodnota: string) {
  pole.value = hodnota
  pole.dispatchEvent(new Event('input', { bubbles: true }))
}

/** @return whether the browser would ask before leaving */
function zkusOdejit(): boolean {
  const udalost = new Event('beforeunload', { cancelable: true })
  window.dispatchEvent(udalost)

  return udalost.defaultPrevented
}

function odesli(formular: HTMLFormElement): boolean {
  const udalost = new Event('submit', { cancelable: true, bubbles: true })
  formular.dispatchEvent(udalost)

  return udalost.defaultPrevented
}

describe('upozornění při odchodu z editoru s neuloženými změnami', () => {
  // Listeners the editor leaves on window must not leak into the next test, and we want to know when it has any.
  const sledovaneUdalosti = ['beforeunload', 'pageshow']
  let varovani: Set<EventListenerOrEventListenerObject>
  let sledovanePosluchace: Map<EventListenerOrEventListenerObject, string>
  const pridejOriginal = window.addEventListener.bind(window)
  const odeberOriginal = window.removeEventListener.bind(window)

  beforeEach(() => {
    varovani = new Set()
    sledovanePosluchace = new Map()
    vi.spyOn(window, 'addEventListener').mockImplementation(
      (typ: string, posluchac: EventListenerOrEventListenerObject, moznosti?: boolean | AddEventListenerOptions) => {
        if (sledovaneUdalosti.includes(typ)) {
          sledovanePosluchace.set(posluchac, typ)
        }
        if (typ === 'beforeunload') {
          varovani.add(posluchac)
        }
        pridejOriginal(typ, posluchac, moznosti)
      },
    )
    vi.spyOn(window, 'removeEventListener').mockImplementation(
      (typ: string, posluchac: EventListenerOrEventListenerObject, moznosti?: boolean | EventListenerOptions) => {
        if (typ === 'beforeunload') {
          varovani.delete(posluchac)
        }
        odeberOriginal(typ, posluchac, moznosti)
      },
    )
  })

  afterEach(() => {
    sledovanePosluchace.forEach((typ, posluchac) => odeberOriginal(typ, posluchac))
    vi.restoreAllMocks()
    document.body.innerHTML = ''
  })

  it('beze změn se odejde bez ptaní', async () => {
    await otevriEditor()

    expect(zkusOdejit()).toBe(false)
  })

  it('po změně textu se prohlížeč zeptá', async () => {
    const { textPole } = await otevriEditor()

    pis(textPole, 'Ahoj %jmeno%, heslo si nastav sám.')

    expect(zkusOdejit()).toBe(true)
  })

  it('po změně předmětu se prohlížeč zeptá', async () => {
    const { predmetPole } = await otevriEditor()

    pis(predmetPole, 'Nové heslo')

    expect(zkusOdejit()).toBe(true)
  })

  it('po vrácení původního znění se už neptá a nezůstane po něm posluchač', async () => {
    const { textPole } = await otevriEditor('Obnova hesla', 'Původní text')

    pis(textPole, 'Změněný text')
    pis(textPole, 'Původní text')

    expect(zkusOdejit()).toBe(false)
    expect(varovani.size).toBe(0)
  })

  it('posluchač se přidá až s první změnou', async () => {
    const { textPole } = await otevriEditor()
    expect(varovani.size).toBe(0)

    pis(textPole, 'Něco jiného')

    expect(varovani.size).toBe(1)
  })

  it('odeslání formuláře se neptá, uložení změny přece neztratí', async () => {
    const { formular, textPole } = await otevriEditor()
    pis(textPole, 'Ahoj %jmeno%, heslo si nastav sám.')

    expect(odesli(formular)).toBe(false)

    expect(zkusOdejit()).toBe(false)
  })

  it('odeslání zablokované chybou nechá varování, text pořád není uložený', async () => {
    const { formular, textPole } = await otevriEditor()
    pis(textPole, 'Ahoj %neexistuje%')

    expect(odesli(formular)).toBe(true)

    expect(zkusOdejit()).toBe(true)
  })

  it('další úprava po pokusu o odeslání varování zase zapne', async () => {
    const { formular, textPole } = await otevriEditor()
    pis(textPole, 'Ahoj %jmeno%, heslo si nastav sám.')
    odesli(formular)

    pis(textPole, 'Ahoj %jmeno%, heslo si nastav sama.')

    expect(zkusOdejit()).toBe(true)
  })

  it('po neúspěšném uložení na serveru se zeptá hned, rozepsaný text je jen ve formuláři', async () => {
    await otevriEditor('Obnova hesla', 'Rozepsaný text', ['Text používá neznámý symbol.'])

    expect(zkusOdejit()).toBe(true)
  })

  it('text, který prohlížeč vrátil do pole ještě před spuštěním skriptu, se hlídá', async () => {
    await otevriEditor('Obnova hesla', 'Uložený text', [], 'Rozepsaný text před odchodem')

    expect(zkusOdejit()).toBe(true)
  })

  it('text, který prohlížeč po návratu zpět vrátil do pole bez události input, se hlídá po pageshow', async () => {
    const { textPole } = await otevriEditor('Obnova hesla', 'Uložený text')
    expect(zkusOdejit()).toBe(false)

    textPole.value = 'Rozepsaný text před odchodem'
    window.dispatchEvent(new Event('pageshow'))

    expect(zkusOdejit()).toBe(true)
  })

  it('pageshow bez rozepsaného textu se neptá', async () => {
    await otevriEditor()

    window.dispatchEvent(new Event('pageshow'))

    expect(zkusOdejit()).toBe(false)
  })
})

describe('odeslání zkušebního e-mailu', () => {
  const ADRESA = 'foo@example.com'
  const ADRESA_SCHRANKY = 'http://localhost:11112'
  let fetchMock: ReturnType<typeof vi.fn>

  const tlacitkoTestu = () => document.querySelector<HTMLButtonElement>('.editorSablony_test')!
  const dialogTestu = () => document.querySelector<HTMLDialogElement>('.editorSablony_testDialog')!
  const stavTestu = () => document.querySelector<HTMLElement>('.editorSablony_testStav')!
  const odpovedServeru = (telo: string) => ({ ok: true, text: () => Promise.resolve(telo) })

  beforeEach(() => {
    fetchMock = vi.fn().mockResolvedValue(odpovedServeru(JSON.stringify({ ok: true, zprava: `Test odeslán na ${ADRESA}` })))
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('nástroje jdou za sebou: přepínač Úprava / Náhled, Odeslat test, Vložit symbol, nápověda', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA)

    const poradi = [...document.querySelector('.editorSablony_nastroje')!.children].map((uzel) => {
      if (uzel.classList.contains('editorSablony_rezimy')) return 'prepinac'
      if (uzel.classList.contains('editorSablony_test')) return 'test'
      if (uzel.classList.contains('editorSablony_vlozit')) return 'vlozit'
      return 'napoveda'
    })

    expect(poradi).toEqual(['prepinac', 'test', 'vlozit', 'napoveda'])
    expect(tlacitkoTestu().textContent?.trim()).toBe('Odeslat test')
  })

  it('tlačítko Otevřít schránku je hned za Odeslat test, před Vložit symbol', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA, ADRESA_SCHRANKY)

    const poradi = [...document.querySelector('.editorSablony_nastroje')!.children].map((uzel) => {
      if (uzel.classList.contains('editorSablony_rezimy')) return 'prepinac'
      if (uzel.classList.contains('editorSablony_test')) return 'test'
      if (uzel.classList.contains('editorSablony_schranka')) return 'schranka'
      if (uzel.classList.contains('editorSablony_vlozit')) return 'vlozit'
      return 'napoveda'
    })

    expect(poradi).toEqual(['prepinac', 'test', 'schranka', 'vlozit', 'napoveda'])
    expect(document.querySelector('.editorSablony_schranka')!.textContent?.trim()).toBe('Otevřít schránku')
  })

  it('tlačítka mají ikonu Font Awesome, která je jen ozdoba a nemění jejich text', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA, ADRESA_SCHRANKY)

    const ikony = ['test', 'schranka', 'vlozit'].map((trida) => {
      const tlacitko = document.querySelector<HTMLButtonElement>(`.editorSablony_${trida}`)!
      const ikona = tlacitko.querySelector('i')!
      return [ikona?.className, ikona?.getAttribute('aria-hidden'), tlacitko.textContent?.trim()]
    })

    expect(ikony).toEqual([
      ['fa-solid fa-paper-plane', 'true', 'Odeslat test'],
      ['fa-solid fa-inbox', 'true', 'Otevřít schránku'],
      ['fa-solid fa-percent', 'true', 'Vložit symbol'],
    ])
  })

  it('klik na Otevřít schránku otevře schránku v nové záložce a nic neodešle', async () => {
    const otevri = vi.spyOn(window, 'open').mockReturnValue(null)
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA, ADRESA_SCHRANKY)

    document.querySelector<HTMLButtonElement>('.editorSablony_schranka')!.click()

    expect(otevri).toHaveBeenCalledExactlyOnceWith(ADRESA_SCHRANKY, '_blank', 'noopener')
    expect(fetchMock).not.toHaveBeenCalled()
    expect(dialogTestu().hasAttribute('open')).toBe(false)
  })

  it('prostředí, které e-maily nezachytává, tlačítko Otevřít schránku nemá', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA)

    expect(document.querySelector('.editorSablony_schranka')).toBeNull()
  })

  it('stránka, která zkušební e-mail nenabízí, tlačítko ani potvrzení nemá', async () => {
    await otevriEditor()

    expect(document.querySelector('.editorSablony_test')).toBeNull()
    expect(document.querySelector('.editorSablony_testDialog')).toBeNull()
  })

  it('klik otevře potvrzení s adresou přihlášeného admina', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA)

    tlacitkoTestu().click()

    expect(dialogTestu().hasAttribute('open')).toBe(true)
    expect(dialogTestu().querySelector('p')!.textContent.replace(/\s+/g, ' ').trim()).toBe(`Odeslat na ${ADRESA} ?`)
    expect(dialogTestu().querySelector('strong')!.textContent).toBe(ADRESA)
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('zrušení nic neodešle', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA)
    tlacitkoTestu().click()

    dialogTestu().querySelector<HTMLButtonElement>('.editorSablony_testZrusit')!.click()

    expect(dialogTestu().hasAttribute('open')).toBe(false)
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('potvrzení odešle předmět a text tak, jak jsou v editoru, i neuložené', async () => {
    const { predmetPole, textPole } = await otevriEditor('Obnova hesla', 'Uložený text', [], undefined, ADRESA)
    pis(predmetPole, 'Nový předmět')
    pis(textPole, 'Ahoj %jmeno%, rozepsaný text')
    tlacitkoTestu().click()

    dialogTestu().querySelector<HTMLButtonElement>('.editorSablony_testOdeslat')!.click()

    await vi.waitFor(() => expect(stavTestu().textContent).toBe(`Test odeslán na ${ADRESA}`))
    const [url, nastaveni] = fetchMock.mock.calls[0] as [string, { method: string; body: FormData }]
    expect(url).toBe('test')
    expect(nastaveni.method).toBe('POST')
    expect(nastaveni.body.get('predmet')).toBe('Nový předmět')
    expect(nastaveni.body.get('text')).toBe('Ahoj %jmeno%, rozepsaný text')
    expect(dialogTestu().hasAttribute('open')).toBe(false)
    expect(stavTestu().classList.contains('editorSablony_testStavChyba')).toBe(false)
  })

  it('chybu ohlášenou serverem ukáže jako chybu', async () => {
    fetchMock.mockResolvedValue(odpovedServeru(JSON.stringify({ ok: false, zprava: 'E-mail se neodeslal, SMTP nereaguje.' })))
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA)
    tlacitkoTestu().click()

    dialogTestu().querySelector<HTMLButtonElement>('.editorSablony_testOdeslat')!.click()

    await vi.waitFor(() => expect(stavTestu().textContent).toContain('SMTP nereaguje'))
    expect(stavTestu().classList.contains('editorSablony_testStavChyba')).toBe(true)
  })

  it('odpověď, která není JSON (vypršelo přihlášení), ukáže jako chybu', async () => {
    fetchMock.mockResolvedValue(odpovedServeru('<html><body>Přihlášení</body></html>'))
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA)
    tlacitkoTestu().click()

    dialogTestu().querySelector<HTMLButtonElement>('.editorSablony_testOdeslat')!.click()

    await vi.waitFor(() => expect(stavTestu().classList.contains('editorSablony_testStavChyba')).toBe(true))
    expect(stavTestu().textContent).toContain('přihlášení')
  })

  it('při odesílání je tlačítko zakázané, ať se nepošle dvakrát', async () => {
    let dokonci: (odpoved: unknown) => void = () => undefined
    fetchMock.mockReturnValue(new Promise((vyresit) => (dokonci = vyresit)))
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA)
    tlacitkoTestu().click()

    dialogTestu().querySelector<HTMLButtonElement>('.editorSablony_testOdeslat')!.click()

    expect(tlacitkoTestu().disabled).toBe(true)
    dokonci(odpovedServeru(JSON.stringify({ ok: true, zprava: 'Hotovo.' })))
    await vi.waitFor(() => expect(tlacitkoTestu().disabled).toBe(false))
  })

  it('text s chybou test odeslat nedovolí, stejně jako uložit', async () => {
    const { formular, textPole } = await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA)
    expect(tlacitkoTestu().disabled).toBe(false)

    pis(textPole, 'Ahoj %neexistuje%')
    odesli(formular)

    expect(tlacitkoTestu().disabled).toBe(true)
  })
})

describe('zablikání tlačítka Otevřít schránku po odeslání testu', () => {
  const ADRESA = 'foo@example.com'
  const ADRESA_SCHRANKY = 'http://localhost:11112'
  const BLIKA = 'editorSablony_schrankaBlika'
  let fetchMock: ReturnType<typeof vi.fn>

  const schranka = () => document.querySelector<HTMLButtonElement>('.editorSablony_schranka')!
  const odpoved = (obsah: { ok: boolean; zprava: string }) => ({ ok: true, text: () => Promise.resolve(JSON.stringify(obsah)) })

  /** Confirms the test e-mail in the dialog and lets the answer of the server be processed */
  async function odesliTest() {
    document.querySelector<HTMLButtonElement>('.editorSablony_test')!.click()
    document.querySelector<HTMLButtonElement>('.editorSablony_testOdeslat')!.click()
    await vi.advanceTimersByTimeAsync(0)
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    fetchMock = vi.fn().mockResolvedValue(odpoved({ ok: true, zprava: `Test odeslán na ${ADRESA}` }))
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('po úspěšném odeslání tlačítko zablikne', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA, ADRESA_SCHRANKY)
    expect(schranka().classList.contains(BLIKA)).toBe(false)

    await odesliTest()

    expect(schranka().classList.contains(BLIKA)).toBe(true)
  })

  it('zablikne dvakrát za sekundu: zelená 0,35 s, pauza 0,3 s, zelená 0,35 s, pak skončí', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA, ADRESA_SCHRANKY)
    await odesliTest()
    const blika = () => schranka().classList.contains(BLIKA)
    const blikaPoUplynuti = (milisekundy: number) => {
      vi.advanceTimersByTime(milisekundy)
      return blika()
    }

    expect(blika()).toBe(true)
    expect(blikaPoUplynuti(349)).toBe(true) // 349 ms: first flash
    expect(blikaPoUplynuti(1)).toBe(false) // 350 ms: pause begins
    expect(blikaPoUplynuti(299)).toBe(false) // 649 ms: still the pause
    expect(blikaPoUplynuti(1)).toBe(true) // 650 ms: second flash
    expect(blikaPoUplynuti(349)).toBe(true) // 999 ms: still the second flash
    expect(blikaPoUplynuti(1)).toBe(false) // 1000 ms: over
    expect(blikaPoUplynuti(5000)).toBe(false)
  })

  it('při neúspěšném odeslání tlačítko nebliká, ve schránce nic nepřibylo', async () => {
    fetchMock.mockResolvedValue(odpoved({ ok: false, zprava: 'E-mail se neodeslal, SMTP nereaguje.' }))
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA, ADRESA_SCHRANKY)

    await odesliTest()

    expect(document.querySelector('.editorSablony_testStav')!.textContent).toContain('SMTP nereaguje')
    expect(schranka().classList.contains(BLIKA)).toBe(false)
  })

  it('další úspěšné odeslání blikání začne znovu od začátku, zbytek předchozího nevstoupí do nového', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA, ADRESA_SCHRANKY)
    await odesliTest()
    vi.advanceTimersByTime(200)
    expect(schranka().classList.contains(BLIKA)).toBe(true)

    // sent again while the first flash is on: the first sequence would turn it off at 350 ms, i.e. 150 ms from now
    await odesliTest()
    vi.advanceTimersByTime(250)
    expect(schranka().classList.contains(BLIKA)).toBe(true)

    vi.advanceTimersByTime(100) // 350 ms of the new sequence: its pause
    expect(schranka().classList.contains(BLIKA)).toBe(false)

    vi.advanceTimersByTime(300) // 650 ms of the new sequence: its second flash
    expect(schranka().classList.contains(BLIKA)).toBe(true)

    vi.advanceTimersByTime(350) // 1000 ms of the new sequence
    expect(schranka().classList.contains(BLIKA)).toBe(false)
  })

  it('bez tlačítka Otevřít schránku se po odeslání nic nestane a hláška se ukáže', async () => {
    await otevriEditor('Obnova hesla', 'Text', [], undefined, ADRESA)

    await odesliTest()

    expect(document.querySelector('.editorSablony_schranka')).toBeNull()
    expect(document.querySelector('.editorSablony_testStav')!.textContent).toBe(`Test odeslán na ${ADRESA}`)
  })
})

describe('zvýraznění vloženého symbolu', () => {
  const zvyrazneni = () => document.querySelectorAll('.editorSablony_vlozeno')

  /** Picks the first symbol of the list the Insert symbol button opens, with the caret at the given place of the text */
  function vlozZDialogu(textPole: HTMLTextAreaElement, pozice: number) {
    textPole.focus()
    textPole.setSelectionRange(pozice, pozice)
    document.querySelector<HTMLButtonElement>('.editorSablony_vlozit')!.click()
    document.querySelector<HTMLElement>('.editorSablony_radekSymbolu')!.click()
  }

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('symbol vybraný v seznamu se vloží a zvýrazní', async () => {
    const { textPole } = await otevriEditor('Obnova hesla', 'Ahoj ')

    vlozZDialogu(textPole, 5)

    expect(textPole.value).toBe('Ahoj %jmeno%')
    expect(zvyrazneni()).toHaveLength(1)
  })

  it('symbol vybraný z našeptávače po napsání procenta se zvýrazní také', async () => {
    const { textPole } = await otevriEditor('Obnova hesla', 'Ahoj ')
    pis(textPole, 'Ahoj %jm')

    document
      .querySelector<HTMLElement>('.editorSablony_polozka')!
      .dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }))

    expect(textPole.value).toBe('Ahoj %jmeno%')
    expect(zvyrazneni()).toHaveLength(1)
  })

  it('zvýraznění po třech sekundách zmizí', async () => {
    const { textPole } = await otevriEditor('Obnova hesla', 'Ahoj ')
    vi.useFakeTimers()

    vlozZDialogu(textPole, 5)
    vi.advanceTimersByTime(2999)
    expect(zvyrazneni()).toHaveLength(1)

    vi.advanceTimersByTime(1)
    expect(zvyrazneni()).toHaveLength(0)
  })

  it('další vložený symbol zvýraznění předchozího nahradí, nepřibývá jich', async () => {
    const { textPole } = await otevriEditor('Obnova hesla', 'Ahoj ')

    vlozZDialogu(textPole, 5)
    vlozZDialogu(textPole, 5)

    expect(zvyrazneni()).toHaveLength(1)
  })

  it('úprava textu před symbolem zvýraznění zruší, protože by ukazovalo jinam', async () => {
    const { textPole } = await otevriEditor('Obnova hesla', 'Ahoj ')
    vlozZDialogu(textPole, 5)

    pis(textPole, 'X' + textPole.value)

    expect(zvyrazneni()).toHaveLength(0)
  })

  it('psaní za symbolem zvýraznění nechá', async () => {
    const { textPole } = await otevriEditor('Obnova hesla', 'Ahoj ')
    vlozZDialogu(textPole, 5)

    pis(textPole, textPole.value + ', jak se máš?')

    expect(zvyrazneni()).toHaveLength(1)
  })

  it('přepnutí na náhled zvýraznění zruší, pole je pak skryté', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, text: () => Promise.resolve('<div data-editor-sablony-nahled></div>') }))
    const { textPole } = await otevriEditor('Obnova hesla', 'Ahoj ')
    vlozZDialogu(textPole, 5)

    document.querySelector<HTMLButtonElement>('.editorSablony_rezim[data-rezim="nahled"]')!.click()

    expect(zvyrazneni()).toHaveLength(0)
  })
})
