import { describe, expect, it } from 'vitest'
// The editor logic lives in admin/files as a plain script without a bundler (the browser exposes it as window.EditorSablonyLogika).
import logika from '../../../admin/files/editor-sablony-logika.js'
import sdilenaData from '../../../tests/Model/Kanaly/EmailovaSablona/validace-pripady.json'

const popis = (text: string) => logika.rozloz(text).map((token) => `${token.typ}:${token.hodnota}`)

describe('validace sdílená s PHP validátorem', () => {
  it.each(sdilenaData.pripady.map((pripad) => [pripad.nazev, pripad] as const))('%s', (_nazev, pripad) => {
    expect(logika.validuj(sdilenaData.data, pripad.predmet, pripad.text)).toEqual(pripad.chyby)
  })
})

describe('rozloz', () => {
  it('text bez procent je jeden token', () => {
    expect(popis('Ahoj světe')).toEqual(['TEXT:Ahoj světe'])
  })

  it('symbol mezi procenty', () => {
    expect(popis('Ahoj %jmeno%!')).toEqual(['TEXT:Ahoj ', 'SYMBOL:jmeno', 'TEXT:!'])
  })

  it('dvě procenta jsou jedno procento v textu', () => {
    expect(popis('Sleva 50%% na vše')).toEqual(['TEXT:Sleva 50% na vše'])
  })

  it('holé procento před mezerou je text', () => {
    expect(popis('Vrátíme 50 % částky')).toEqual(['TEXT:Vrátíme 50 % částky'])
  })

  it('neuzavřený a číselný symbol', () => {
    expect(popis('%jmen a %1')).toEqual(['NEUZAVRENY_SYMBOL:jmen', 'TEXT: a ', 'CISLICNY_SYMBOL:1'])
  })

  it('pozice tokenů ukazují do původního textu včetně procent', () => {
    const tokeny = logika.rozloz('a %jmeno% b')

    expect(tokeny.map((token) => [token.zacatek, token.konec])).toEqual([
      [0, 2],
      [2, 9],
      [9, 11],
    ])
  })

  it('pozice textu s dvojitým procentem pokrývají oba znaky', () => {
    expect(logika.rozloz('a%%b').map((token) => [token.zacatek, token.konec])).toEqual([[0, 4]])
  })
})

describe('najdiRozepsanySymbol', () => {
  it('osamělé procento těsně před kurzorem otevírá symbol bez předpony', () => {
    expect(logika.najdiRozepsanySymbol('Ahoj %', 6)).toEqual({ zacatek: 5, predpona: '' })
  })

  it('rozepsané jméno je předpona', () => {
    expect(logika.najdiRozepsanySymbol('Ahoj %jme', 9)).toEqual({ zacatek: 5, predpona: 'jme' })
  })

  it('dokončený symbol nic neotevírá', () => {
    expect(logika.najdiRozepsanySymbol('Ahoj %jmeno%', 12)).toBeNull()
  })

  it('dvojité procento nic neotevírá', () => {
    expect(logika.najdiRozepsanySymbol('Sleva 50%%', 10)).toBeNull()
  })

  it('procento hned za dokončeným symbolem znovu otevírá nový', () => {
    expect(logika.najdiRozepsanySymbol('%jmeno%%', 8)).toEqual({ zacatek: 7, predpona: '' })
  })

  it('kurzor uprostřed textu se dívá jen na text před sebou', () => {
    expect(logika.najdiRozepsanySymbol('Ahoj %jme a dál', 9)).toEqual({ zacatek: 5, predpona: 'jme' })
  })

  it('obyčejný text nic neotevírá', () => {
    expect(logika.najdiRozepsanySymbol('Ahoj světe', 10)).toBeNull()
  })
})

describe('rozsahKPrepsani', () => {
  const text = 'Ahoj %jmeno% a %vs%'

  it('prostý kurzor mimo symbol vkládá na místě', () => {
    expect(logika.rozsahKPrepsani(text, 2, 2)).toEqual({ zacatek: 2, konec: 2 })
  })

  it('kurzor těsně před nebo za symbolem vkládá vedle něj', () => {
    expect(logika.rozsahKPrepsani(text, 5, 5)).toEqual({ zacatek: 5, konec: 5 })
    expect(logika.rozsahKPrepsani(text, 12, 12)).toEqual({ zacatek: 12, konec: 12 })
  })

  it('kurzor uvnitř symbolu přepíše celý symbol', () => {
    expect(logika.rozsahKPrepsani(text, 8, 8)).toEqual({ zacatek: 5, konec: 12 })
  })

  it('výběr uvnitř symbolu přepíše celý symbol', () => {
    expect(logika.rozsahKPrepsani(text, 7, 10)).toEqual({ zacatek: 5, konec: 12 })
  })

  it('výběr zasahující do symbolu ho vezme celý', () => {
    expect(logika.rozsahKPrepsani(text, 2, 8)).toEqual({ zacatek: 2, konec: 12 })
  })

  it('výběr přesně přes celý symbol zůstane, jak je', () => {
    expect(logika.rozsahKPrepsani(text, 5, 12)).toEqual({ zacatek: 5, konec: 12 })
  })

  it('výběr přes víc symbolů je nechá být', () => {
    expect(logika.rozsahKPrepsani(text, 5, 19)).toEqual({ zacatek: 5, konec: 19 })
  })
})

describe('filtrujSymboly', () => {
  const symboly = [
    { nazev: 'jmeno', popis: 'jméno' },
    { nazev: 'prijmeni', popis: 'příjmení' },
    { nazev: 'GC_BEZI_OD', popis: 'začátek' },
  ]

  it('bez předpony vrátí všechno', () => {
    expect(logika.filtrujSymboly(symboly, '')).toHaveLength(3)
  })

  it('nejdřív ty, co předponou začínají, pak ty, co ji obsahují', () => {
    expect(logika.filtrujSymboly(symboly, 'meno').map((symbol) => symbol.nazev)).toEqual(['jmeno'])
    expect(logika.filtrujSymboly(symboly, 'j').map((symbol) => symbol.nazev)).toEqual(['jmeno', 'prijmeni'])
  })

  it('nerozlišuje velikost písmen', () => {
    expect(logika.filtrujSymboly(symboly, 'gc_').map((symbol) => symbol.nazev)).toEqual(['GC_BEZI_OD'])
  })
})
