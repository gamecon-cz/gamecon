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
function vykresliSablonu(predmet: string, text: string, chyby: string[]): string {
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
    .replace(/\{(\w+)\}/g, (_celek: string, nazev: string) => hodnoty[nazev] ?? '')
}

/** @param obnovenyText what the browser puts back into the text field before the script starts (Firefox does) */
async function otevriEditor(
  predmet = 'Obnova hesla',
  text = 'Ahoj %jmeno%, nové heslo je v odkazu.',
  chyby: string[] = [],
  obnovenyText?: string,
) {
  document.body.innerHTML = vykresliSablonu(predmet, text, chyby)
  if (obnovenyText !== undefined) {
    document.querySelector<HTMLTextAreaElement>('.editorSablony_text')!.value = obnovenyText
  }
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
