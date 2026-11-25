import { gotoLine } from '@codemirror/search'
import { type EditorView, runScopeHandlers } from '@codemirror/view'
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { vytvorEditor } from './editor'

const editory: EditorView[] = []

// Formulář jako z db-form.xtpl: AJAX ho odesílá v obsluze submit a Uložit po dobu ukládání vypne.
const editorVeFormulari = () => {
  document.body.innerHTML = '<form><textarea data-editor-textu>Ahoj\nsvěte</textarea><button type="submit">Uložit</button></form>'
  const formular = document.querySelector('form')!
  const ulozit = formular.querySelector('button')!
  const odeslani = vi.fn((udalost: Event) => {
    udalost.preventDefault()
  })
  formular.addEventListener('submit', odeslani)
  const view = vytvorEditor(document.querySelector('textarea')!, { nahled: null, obrazky: null, odUzivatele: false })
  editory.push(view)

  return { odeslani, view, ulozit }
}

const ctrlS = () => new KeyboardEvent('keydown', { key: 's', ctrlKey: true })

beforeAll(() => {
  // jsdom rozměry textu nepočítá, CodeMirror je při měření chce
  Range.prototype.getClientRects = () => [] as unknown as DOMRectList
})

afterEach(() => {
  editory.splice(0).forEach((view) => {
    view.destroy()
  })
  document.body.innerHTML = ''
})

afterAll(() => {
  delete (Range.prototype as Partial<Range>).getClientRects
})

describe('ukládání z editoru', () => {
  it('Ctrl+S formulář odešle', () => {
    const { odeslani, view } = editorVeFormulari()

    runScopeHandlers(view, ctrlS(), 'editor')

    expect(odeslani).toHaveBeenCalledOnce()
  })

  it('Ctrl+S během ukládání formulář znovu neodešle', () => {
    const { odeslani, view, ulozit } = editorVeFormulari()
    ulozit.disabled = true

    runScopeHandlers(view, ctrlS(), 'editor')

    expect(odeslani).not.toHaveBeenCalled()
  })

  it('dialog přejít na řádek formulář neodešle', () => {
    const { odeslani, view } = editorVeFormulari()
    gotoLine(view)
    const prejit = view.dom.querySelector<HTMLButtonElement>('.cm-goto-line [type=submit]')

    expect(prejit).not.toBeNull()
    prejit!.click()

    expect(odeslani).not.toHaveBeenCalled()
  })
})
