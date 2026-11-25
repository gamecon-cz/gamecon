import { history, undo } from '@codemirror/commands'
import { EditorSelection, EditorState } from '@codemirror/state'
import { describe, expect, it } from 'vitest'
import {
  mistaObrazku,
  nazevProNahrani,
  poziceMista,
  type Prenos,
  pridejMisto,
  souboryZeSchranky,
  souboryZPretazeni,
  vlozNaMisto,
} from './obrazky'

const soubor = (nazev: string, typ: string) => new File(['x'], nazev, { type: typ })

const prenos = (soubory: File[], text = ''): Prenos => ({
  files: soubory,
  getData: (format: string) => format === 'text/plain' ? text : '',
})

describe('co se nahraje', () => {
  it('obrázek ze schránky', () => {
    const snimek = soubor('image.png', 'image/png')

    expect(souboryZeSchranky(prenos([snimek]))).toEqual({ obrazky: [snimek], ostatni: [] })
  })

  it('ze schránky má přednost text: Word k textu přidává i jeho obrázek', () => {
    expect(souboryZeSchranky(prenos([soubor('image.png', 'image/png')], 'Odstavec z Wordu'))).toBeNull()
  })

  it('schránka bez obrázku se vloží obvyklým způsobem', () => {
    expect(souboryZeSchranky(prenos([], 'text'))).toBeNull()
    expect(souboryZeSchranky(prenos([soubor('tabulka.pdf', 'application/pdf')]))).toBeNull()
    expect(souboryZeSchranky(null)).toBeNull()
  })

  it('přetažený soubor obslouží editor vždy, i když to není obrázek', () => {
    const fotka = soubor('Fotka.JPG', 'image/jpeg')
    const pdf = soubor('pravidla.pdf', 'application/pdf')

    expect(souboryZPretazeni(prenos([fotka, pdf]))).toEqual({ obrazky: [fotka], ostatni: [pdf] })
    expect(souboryZPretazeni(prenos([]))).toBeNull()
    expect(souboryZPretazeni({ files: null })).toBeNull()
  })
})

describe('název pro server', () => {
  it('obrázek ze schránky dostane srozumitelný název podle typu', () => {
    expect(nazevProNahrani(soubor('image.png', 'image/png'), true)).toBe('obrazek.png')
    expect(nazevProNahrani(soubor('image.jpeg', 'image/jpeg'), true)).toBe('obrazek.jpg')
    expect(nazevProNahrani(soubor('', 'image/svg+xml'), false)).toBe('obrazek.svg')
  })

  it('přetažený nebo vybraný soubor si název nechá', () => {
    expect(nazevProNahrani(soubor('Plakát 2026.jpg', 'image/jpeg'), false)).toBe('Plakát 2026.jpg')
  })
})

describe('místo nahrávaného obrázku', () => {
  const OBRAZEK = '![](soubory/obsah/blog/x/obrazek.avif)'

  const sMistem = (text: string, pozice: number): EditorState => EditorState.create({
    doc: text,
    selection: EditorSelection.cursor(pozice),
    extensions: [history(), mistaObrazku],
  }).update({ effects: pridejMisto.of({ id: 1, pozice, popis: 'obrazek.png' }), userEvent: 'input.paste' }).state

  it('není v textu, takže se s textem neuloží', () => {
    const state = sMistem('Text ', 5)

    expect(state.doc.toString()).toBe('Text ')
    expect(poziceMista(state, 1)).toBe(5)
  })

  it('posouvá se s textem a co se po vložení píše dál, zůstane za obrázkem', () => {
    let state = sMistem('Text ', 5)
    state = state.update({ changes: { from: 0, insert: 'Úvod. ' } }).state
    state = state.update({ changes: { from: 11, insert: 'a dál' } }).state

    state = state.update(vlozNaMisto(state, 1, OBRAZEK)).state

    expect(state.doc.toString()).toBe(`Úvod. Text ${OBRAZEK}a dál`)
    expect(poziceMista(state, 1)).toBeNull()
  })

  it('čekající kurzor skončí za obrázkem a jedno Zpět obrázek odebere', () => {
    let state = sMistem('Text ', 5)

    state = state.update(vlozNaMisto(state, 1, OBRAZEK)).state
    expect(state.selection.main.head).toBe(5 + OBRAZEK.length)

    undo({ state, dispatch: (transakce) => { state = transakce.state } })
    expect(state.doc.toString()).toBe('Text ')
  })

  it('když se nic nenahrálo, jen zmizí', () => {
    let state = sMistem('Text ', 5)

    state = state.update(vlozNaMisto(state, 1, '')).state

    expect(state.doc.toString()).toBe('Text ')
    expect(poziceMista(state, 1)).toBeNull()
  })
})
