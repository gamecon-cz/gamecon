import { beforeAll, describe, expect, it, vi } from 'vitest'

// The service worker (web/sw.js) is a plain file in the root of the web. In jsdom `self` is the window, the script
// only adds its listeners to it; its pure logic is exposed on self.gcPushServiceWorker for these tests.

type Zprava = { nadpis: string; text: string; adresa: string; tag: string | undefined }

type Sluzba = { registration: { scope: string; showNotification: (nadpis: string, volby: object) => Promise<void> } }

type ServiceWorker = {
  bezpecnaAdresa: (adresa: unknown, scope: string) => string
  prectiZpravu: (data: { json: () => unknown } | null) => Zprava
  zpracujPush: (sluzba: Sluzba, data: { json: () => unknown } | null) => Promise<void>
}

let serviceWorker: ServiceWorker

beforeAll(async () => {
  // @ts-expect-error a plain script without types
  await import('../../../web/sw.js')
  serviceWorker = (self as unknown as { gcPushServiceWorker: ServiceWorker }).gcPushServiceWorker
})

describe('adresa, kterou otevře kliknutí na notifikaci', () => {
  const OSTRA = 'https://gamecon.cz/'
  const LOKAL = 'http://localhost/web/'

  it.each<[string, string, string]>([
    ['stránka webu', 'https://gamecon.cz/program/muj', 'https://gamecon.cz/program/muj'],
    ['cesta bez domény', 'program/muj', 'https://gamecon.cz/program/muj'],
    ['úvodní stránka', 'https://gamecon.cz/', 'https://gamecon.cz/'],
    ['s dotazem a kotvou', 'https://gamecon.cz/program?den=3#patek', 'https://gamecon.cz/program?den=3#patek'],
  ])('%s se otevře', (_popis, adresa, ocekavana) => {
    expect(serviceWorker.bezpecnaAdresa(adresa, OSTRA)).toBe(ocekavana)
  })

  it.each<[string, unknown]>([
    ['cizí web', 'https://example.com/program'],
    ['cizí web bez schématu', '//example.com/program'],
    ['cizí web se jménem GameConu na začátku', 'https://gamecon.cz.example.com/'],
    ['GameCon jako jméno uživatele', 'https://gamecon.cz@example.com/'],
    ['jiná subdoména', 'https://admin.gamecon.cz/'],
    ['jiný port', 'https://gamecon.cz:8443/program'],
    ['http místo https', 'http://gamecon.cz/program'],
    ['javascript', 'javascript:alert(1)'],
    ['data', 'data:text/html,<script>alert(1)</script>'],
    ['prázdná', ''],
    ['chybí', undefined],
    ['není text', { href: 'https://gamecon.cz/' }],
  ])('%s otevře úvodní stránku', (_popis, adresa) => {
    expect(serviceWorker.bezpecnaAdresa(adresa, OSTRA)).toBe(OSTRA)
  })

  it('lokálně je web ve složce: stránka mimo ni (admin na téže doméně) se neotevře', () => {
    expect(serviceWorker.bezpecnaAdresa('http://localhost/web/program/muj', LOKAL)).toBe('http://localhost/web/program/muj')
    expect(serviceWorker.bezpecnaAdresa('http://localhost/admin/', LOKAL)).toBe(LOKAL)
    expect(serviceWorker.bezpecnaAdresa('http://localhost/web/../admin/', LOKAL)).toBe(LOKAL)
  })
})

describe('obsah notifikace', () => {
  const data = (obsah: unknown) => ({ json: () => obsah })

  it('přečte zprávu ze serveru', () => {
    expect(
      serviceWorker.prectiZpravu(
        data({ title: 'GameCon – připomínka', body: 'Za 15 minut…', url: 'https://gamecon.cz/program/muj', tag: 'aktivita' }),
      ),
    ).toEqual({ nadpis: 'GameCon – připomínka', text: 'Za 15 minut…', adresa: 'https://gamecon.cz/program/muj', tag: 'aktivita' })
  })

  it.each<[string, { json: () => unknown } | null]>([
    ['push bez obsahu', null],
    ['obsah, který není JSON', { json: () => { throw new SyntaxError('not JSON') } }],
    ['JSON, který není objekt', data('text')],
    ['null', data(null)],
    ['pole jiných typů', data({ title: 5, body: [], url: {}, tag: '' })],
  ])('%s ukáže notifikaci s výchozím nadpisem, ne nic', (_popis, obsah) => {
    expect(serviceWorker.prectiZpravu(obsah)).toEqual({ nadpis: 'GameCon', text: '', adresa: '', tag: undefined })
  })
})

describe('příchozí push', () => {
  const sluzba = () => {
    const showNotification = vi.fn((_nadpis: string, _volby: object) => Promise.resolve())
    return { showNotification, sluzba: { registration: { scope: 'http://localhost/web/', showNotification } } }
  }

  it('ukáže notifikaci s ikonou ze svého scope a adresou pro kliknutí', async () => {
    const { showNotification, sluzba: sw } = sluzba()

    await serviceWorker.zpracujPush(sw, {
      json: () => ({ title: 'Nadpis', body: 'Text', url: 'http://localhost/web/program/muj', tag: 'aktivita' }),
    })

    expect(showNotification).toHaveBeenCalledExactlyOnceWith('Nadpis', {
      body: 'Text',
      icon: 'http://localhost/web/soubory/push/ikona-192.png',
      tag: 'aktivita',
      renotify: true,
      data: { adresa: 'http://localhost/web/program/muj' },
    })
  })

  it('i push s nečitelným obsahem skončí viditelnou notifikací, jinak prohlížeč subscription zruší', async () => {
    const { showNotification, sluzba: sw } = sluzba()

    await serviceWorker.zpracujPush(sw, null)

    expect(showNotification).toHaveBeenCalledOnce()
    expect(showNotification.mock.calls[0][0]).toBe('GameCon')
    expect(showNotification.mock.calls[0][1]).toMatchObject({ renotify: false, tag: undefined })
  })

  it('serveru se při doručení neptá: požadavek z pozadí by měnil token trvalého přihlášení', async () => {
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    const { sluzba: sw } = sluzba()

    await serviceWorker.zpracujPush(sw, { json: () => ({ title: 'Nadpis' }) })

    expect(fetchMock).not.toHaveBeenCalled()
    vi.unstubAllGlobals()
  })
})
