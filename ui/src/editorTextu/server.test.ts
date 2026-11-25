import { describe, expect, it, vi } from 'vitest'
import { adresaPozadavku, ChybaEditoru, HLAVICKA, nactiNahled, nahrajObrazek } from './server'

const STRANKA = 'https://admin.gamecon.cz/web/novinka?id=42#formular'

const odpoved = (telo: string, status = 200) => vi.fn().mockResolvedValue(new Response(telo, { status }))

describe('adresa požadavku', () => {
  it('je adresa stránky s akcí, bez kotvy', () => {
    expect(adresaPozadavku('nahled', STRANKA)).toBe('https://admin.gamecon.cz/web/novinka?id=42&editorTextu=nahled')
  })
})

describe('náhled', () => {
  it('pošle text s hlavičkou editoru a vrátí HTML', async () => {
    const fetchFn = odpoved(JSON.stringify({ ok: true, html: '<p><strong>Ahoj</strong></p>' }))

    const html = await nactiNahled('**Ahoj**', { fetchFn, adresaStranky: STRANKA })

    expect(html).toBe('<p><strong>Ahoj</strong></p>')
    const [adresa, pozadavek] = fetchFn.mock.calls[0] as [string, RequestInit]
    expect(adresa).toBe('https://admin.gamecon.cz/web/novinka?id=42&editorTextu=nahled')
    expect(pozadavek.method).toBe('POST')
    expect(pozadavek.headers).toEqual({ [HLAVICKA]: '1' })
    expect((pozadavek.body as FormData).get('text')).toBe('**Ahoj**')
    expect((pozadavek.body as FormData).has('odUzivatele')).toBe(false)
  })

  it('u textu, který si píše uživatel sám, chce vyčištěné HTML', async () => {
    const fetchFn = odpoved(JSON.stringify({ ok: true, html: '<p>Ahoj</p>' }))

    await nactiNahled('Ahoj', { odUzivatele: true, fetchFn, adresaStranky: STRANKA })

    const [, pozadavek] = fetchFn.mock.calls[0] as [string, RequestInit]
    expect((pozadavek.body as FormData).get('odUzivatele')).toBe('1')
  })
})

describe('obrázek', () => {
  it('pošle soubor pod zvoleným názvem a vrátí zápis do textu', async () => {
    const fetchFn = odpoved(JSON.stringify({
      ok: true,
      cesta: 'soubory/obsah/blog/x/obrazek.avif',
      markdown: '![](soubory/obsah/blog/x/obrazek.avif)',
    }))

    const obrazek = await nahrajObrazek(new File(['png'], 'image.png', { type: 'image/png' }), 'obrazek.png', {
      fetchFn,
      adresaStranky: STRANKA,
    })

    expect(obrazek).toEqual({ cesta: 'soubory/obsah/blog/x/obrazek.avif', markdown: '![](soubory/obsah/blog/x/obrazek.avif)' })
    const telo = (fetchFn.mock.calls[0] as [string, RequestInit])[1].body as FormData
    expect((telo.get('obrazek') as File).name).toBe('obrazek.png')
  })

  it('chybu ze serveru předá s jeho zprávou', async () => {
    const fetchFn = odpoved(JSON.stringify({ ok: false, zprava: 'Obrázek je moc velký.' }), 422)

    await expect(nahrajObrazek(new Blob(['x']), 'a.png', { fetchFn, adresaStranky: STRANKA }))
      .rejects.toEqual(new ChybaEditoru('Obrázek je moc velký.'))
  })
})

describe('když server neodpoví, jak má', () => {
  it('stránka místo JSON (vypršelé přihlášení)', async () => {
    const fetchFn = odpoved('<!DOCTYPE html><title>Přihlášení</title>')

    await expect(nactiNahled('text', { fetchFn, adresaStranky: STRANKA }))
      .rejects.toThrow('Možná vypršelo přihlášení')
  })

  it('výpadek spojení', async () => {
    const fetchFn = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'))

    await expect(nactiNahled('text', { fetchFn, adresaStranky: STRANKA }))
      .rejects.toEqual(new ChybaEditoru('Nepodařilo se spojit se serverem, zkus to prosím znovu.'))
  })

  it('zrušený požadavek není chyba pro člověka', async () => {
    const ovladac = new AbortController()
    ovladac.abort()
    const zruseni = new DOMException('Aborted', 'AbortError')
    const fetchFn = vi.fn().mockRejectedValue(zruseni)

    await expect(nactiNahled('text', { fetchFn, adresaStranky: STRANKA, signal: ovladac.signal })).rejects.toBe(zruseni)
  })
})
