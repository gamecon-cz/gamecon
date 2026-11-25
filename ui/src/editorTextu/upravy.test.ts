import { EditorSelection, EditorState, type StateCommand } from '@codemirror/state'
import { describe, expect, it } from 'vitest'
import { CISLOVANY_SEZNAM, CITACE, NADPIS, ODRAZKY, prepniObaleni, prepniPredponuRadku, vlozOdkaz } from './upravy'

// Zápis stavu editoru: ⟨vybraný text⟩, ‸ kurzor; výběrů může být víc.
const ZACATEK = '⟨'
const KONEC = '⟩'
const KURZOR = '‸'

const zeZapisu = (zapis: string): EditorState => {
  const rozsahy = []
  let text = ''
  let kotva: number | null = null
  for (const znak of zapis) {
    if (znak === ZACATEK) {
      kotva = text.length
    } else if (znak === KONEC && kotva !== null) {
      rozsahy.push(EditorSelection.range(kotva, text.length))
      kotva = null
    } else if (znak === KURZOR) {
      rozsahy.push(EditorSelection.cursor(text.length))
    } else {
      text += znak
    }
  }

  return EditorState.create({
    doc: text,
    selection: EditorSelection.create(rozsahy),
    extensions: EditorState.allowMultipleSelections.of(true),
  })
}

const doZapisu = (state: EditorState): string => {
  let text = state.doc.toString()
  const znacky = state.selection.ranges.flatMap((rozsah) => rozsah.empty
    ? [{ pozice: rozsah.from, znak: KURZOR }]
    : [{ pozice: rozsah.to, znak: KONEC }, { pozice: rozsah.from, znak: ZACATEK }])
  for (const { pozice, znak } of znacky.sort((a, b) => b.pozice - a.pozice)) {
    text = text.slice(0, pozice) + znak + text.slice(pozice)
  }

  return text
}

const proved = (prikaz: StateCommand, zapis: string): string => {
  let state = zeZapisu(zapis)
  prikaz({ state, dispatch: (transakce) => { state = transakce.state } })

  return doZapisu(state)
}

const jenText = (prikaz: StateCommand, zapis: string): string => proved(prikaz, zapis).replace(/[⟨⟩‸]/gu, '')

describe('tučně a kurzíva', () => {
  const tucne = prepniObaleni('**')
  const kurziva = prepniObaleni('_')

  it('obalí výběr a nechá vybraný text', () => {
    expect(proved(tucne, 'Ahoj ⟨světe⟩!')).toBe('Ahoj **⟨světe⟩**!')
    expect(proved(kurziva, 'Ahoj ⟨světe⟩!')).toBe('Ahoj _⟨světe⟩_!')
  })

  it('bez výběru vloží značky s kurzorem mezi nimi', () => {
    expect(proved(tucne, 'Ahoj ‸')).toBe('Ahoj **‸**')
  })

  it('podruhé značky odebere, ať jsou kolem výběru, nebo v něm', () => {
    expect(proved(tucne, 'Ahoj **⟨světe⟩**!')).toBe('Ahoj ⟨světe⟩!')
    expect(proved(tucne, 'Ahoj ⟨**světe**⟩!')).toBe('Ahoj ⟨světe⟩!')
    expect(proved(tucne, 'Ahoj **‸**')).toBe('Ahoj ‸')
  })

  it('mezeru na kraji výběru nechá venku, jinak by to Markdown nepoznal', () => {
    expect(proved(tucne, '⟨slovo ⟩dál')).toBe('**⟨slovo⟩** dál')
    expect(proved(kurziva, 'před⟨ slovo⟩')).toBe('před _⟨slovo⟩_')
  })

  it('upraví každý výběr zvlášť', () => {
    expect(proved(tucne, '⟨jedna⟩ a ⟨dvě⟩')).toBe('**⟨jedna⟩** a **⟨dvě⟩**')
  })
})

describe('předpony řádků', () => {
  it('odrážky na všech řádcích výběru, prázdné přeskočí', () => {
    expect(jenText(prepniPredponuRadku(ODRAZKY), 'pr⟨vní\n\ndru⟩há\ntřetí')).toBe('- první\n\n- druhá\ntřetí')
  })

  it('čísluje po řádcích', () => {
    expect(jenText(prepniPredponuRadku(CISLOVANY_SEZNAM), '⟨a\nb\nc⟩')).toBe('1. a\n2. b\n3. c')
  })

  it('když předponu mají všechny řádky, odebere ji', () => {
    expect(jenText(prepniPredponuRadku(ODRAZKY), '⟨- a\n* b⟩')).toBe('a\nb')
    expect(jenText(prepniPredponuRadku(CISLOVANY_SEZNAM), '⟨1. a\n12. b⟩')).toBe('a\nb')
    expect(jenText(prepniPredponuRadku(CITACE), '⟨> a\n>b⟩')).toBe('a\nb')
  })

  it('když ji mají jen některé, přidá ji ostatním a čísla zamění za odrážky a naopak', () => {
    expect(jenText(prepniPredponuRadku(ODRAZKY), '⟨1. a\n2. b⟩')).toBe('- a\n- b')
    expect(jenText(prepniPredponuRadku(CISLOVANY_SEZNAM), '⟨- a\n- b⟩')).toBe('1. a\n2. b')
    expect(jenText(prepniPredponuRadku(CITACE), '⟨> a\nb⟩')).toBe('> a\n> b')
    expect(jenText(prepniPredponuRadku(ODRAZKY), '⟨- a\nb⟩')).toBe('- a\n- b')
    expect(jenText(prepniPredponuRadku(NADPIS), '⟨## Titul\ntext⟩')).toBe('## Titul\n## text')
    expect(jenText(prepniPredponuRadku(CISLOVANY_SEZNAM), '⟨1. a\nb⟩')).toBe('1. a\n2. b')
  })

  it('nadpis přidá na řádek s kurzorem a odebere jakoukoli úroveň', () => {
    expect(proved(prepniPredponuRadku(NADPIS), 'Úvod‸\ntext')).toBe('## Úvod‸\ntext')
    expect(proved(prepniPredponuRadku(NADPIS), '## Úvod‸')).toBe('Úvod‸')
    expect(proved(prepniPredponuRadku(NADPIS), '# Velký‸')).toBe('Velký‸')
  })

  it('řádek vybraný i s odřádkováním (trojklikem) změní sám, ne i ten pod ním', () => {
    expect(jenText(prepniPredponuRadku(NADPIS), '⟨Program\n⟩První odstavec')).toBe('## Program\nPrvní odstavec')
    expect(jenText(prepniPredponuRadku(NADPIS), '⟨## Program\n⟩První odstavec')).toBe('Program\nPrvní odstavec')
    expect(jenText(prepniPredponuRadku(ODRAZKY), '⟨- a\n⟩b')).toBe('a\nb')
    expect(jenText(prepniPredponuRadku(CITACE), '⟨a\nb\n⟩c')).toBe('> a\n> b\nc')
  })

  it('na prázdném řádku předponu přidá, ať jde začít psát seznam', () => {
    expect(proved(prepniPredponuRadku(ODRAZKY), 'text\n‸')).toBe('text\n- ‸')
  })
})

describe('odkaz a obrázek', () => {
  it('z vybraného textu udělá text odkazu a vybere adresu k přepsání', () => {
    expect(proved(vlozOdkaz(), 'Viz ⟨náš web⟩.')).toBe('Viz [náš web](⟨https://⟩).')
  })

  it('bez výběru vybere text odkazu', () => {
    expect(proved(vlozOdkaz(), 'Viz ‸')).toBe('Viz [⟨text odkazu⟩](https://)')
  })

  it('obrázek z jiné adresy', () => {
    expect(proved(vlozOdkaz(true), '‸')).toBe('![⟨popis⟩](https://)')
    expect(proved(vlozOdkaz(true), '⟨Mapa⟩')).toBe('![Mapa](⟨https://⟩)')
  })
})
