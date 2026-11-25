import { gotoLine } from '@codemirror/search'
import { type EditorView, runScopeHandlers } from '@codemirror/view'
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { type NastaveniEditoru, vytvorEditor } from './editor'
import { nahrajObrazek, type NahranyObrazek } from './server'

vi.mock('./server', async (importOriginal) => ({
  ...await importOriginal<typeof import('./server')>(),
  nahrajObrazek: vi.fn(),
}))

const editory: EditorView[] = []

// Formulář jako z db-form.xtpl: AJAX ho odesílá v obsluze submit a Uložit po dobu ukládání vypne.
const editorVeFormulari = (nastaveni: Partial<NastaveniEditoru> = {}) => {
  document.body.innerHTML = '<form><textarea data-editor-textu>Ahoj\nsvěte</textarea><button type="submit">Uložit</button></form>'
  const formular = document.querySelector('form')!
  const ulozit = formular.querySelector('button')!
  const odeslani = vi.fn((udalost: Event) => {
    udalost.preventDefault()
  })
  formular.addEventListener('submit', odeslani)
  const view = vytvorEditor(document.querySelector('textarea')!, {
    nahled: null,
    obrazky: null,
    odUzivatele: false,
    ...nastaveni,
  })
  editory.push(view)

  return { odeslani, view, ulozit }
}

const ctrlS = () => new KeyboardEvent('keydown', { key: 's', ctrlKey: true })

// jsdom nemá DataTransfer, editor z události čte jen soubory a text
const vlozZeSchranky = (view: EditorView, soubor: File) => {
  const udalost = new Event('paste', { bubbles: true, cancelable: true })
  Object.defineProperty(udalost, 'clipboardData', { value: { files: [soubor], getData: () => '' } })
  view.contentDOM.dispatchEvent(udalost)
}

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

  it('dokud se obrázek nahrává, text se neuloží, jinak by v něm obrázek chyběl', async () => {
    let dokonciNahrani: (obrazek: NahranyObrazek) => void = () => undefined
    vi.mocked(nahrajObrazek).mockReturnValue(new Promise((resolve) => {
      dokonciNahrani = resolve
    }))
    const { odeslani, view, ulozit } = editorVeFormulari({ obrazky: 'image/*' })

    vlozZeSchranky(view, new File(['x'], 'image.png', { type: 'image/png' }))
    await vi.waitFor(() => {
      expect(nahrajObrazek).toHaveBeenCalledOnce()
    })
    ulozit.click()
    runScopeHandlers(view, ctrlS(), 'editor')

    expect(odeslani).not.toHaveBeenCalled()
    expect(view.dom.textContent).toContain('Počkej, až se obrázky nahrají')

    dokonciNahrani({ cesta: 'soubory/obsah/x/obrazek.png', markdown: '![](soubory/obsah/x/obrazek.png)' })
    await vi.waitFor(() => {
      expect(view.state.doc.toString()).toContain('![](soubory/obsah/x/obrazek.png)')
    })
    ulozit.click()

    expect(odeslani).toHaveBeenCalledOnce()
  })
})
