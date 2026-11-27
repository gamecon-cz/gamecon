import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// The script of push notifications is a plain file outside the UI bundle (web/soubory/push-notifikace.js): the web loads
// it on every page of a logged-in user. It exposes its pure logic on window.gcPushNotifikace for these tests.

type Stav = 'nepodporovano' | 'ios-na-plochu' | 'prepnuto' | 'bez-trvaleho-prihlaseni' | 'zamitnuto' | 'zapnuto' | 'vypnuto'

type Logika = {
  zjistiPodporu: (okno: unknown) => 'ano' | 'ios-na-plochu' | 'ne'
  urciStav: (vstup: { podpora: string; opravneni?: string; stavServeru?: string; maSubscription?: boolean }) => Stav
  naBajty: (base64url: string) => Uint8Array
  TEXTY: Record<Stav, { stav: string; napoveda: string[]; tlacitko?: string }>
}

const KONFIGURACE = `<meta name="gc-push-notifikace" data-api="https://gamecon.test/api/pushSubscription"
  data-service-worker="https://gamecon.test/sw.js" data-scope="https://gamecon.test/" data-vapid-klic="BEl62iUYgUivxIkv69yViEuiBIa"
  data-uzivatel="42">`
/** The markup of Gamecon\PushNotifikace\PushNaWebu::getHtmlNastaveni(). */
const PANEL = `
  <div data-push-notifikace>
    <p data-push-stav aria-live="polite">Zjišťuji…</p>
    <div data-push-napoveda hidden></div>
    <p data-push-chyba role="alert" hidden></p>
    <button type="button" data-push-tlacitko hidden></button>
  </div>`
const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc'

let posluchaci: Array<[string, EventListenerOrEventListenerObject]>
let fetchMock: ReturnType<typeof vi.fn>

async function nactiSkript(): Promise<Logika> {
  vi.resetModules()
  // @ts-expect-error a plain script without types
  await import('../../../web/soubory/push-notifikace.js')
  return (window as unknown as { gcPushNotifikace: Logika }).gcPushNotifikace
}

/** Lets the promise chains of the script run to their end: reading a Response body takes more than a microtask. */
async function dobehni() {
  for (let i = 0; i < 10; i++) {
    await new Promise((vyres) => setTimeout(vyres, 0))
  }
}

function odpovedServeru(obsah: object, status = 200) {
  return Promise.resolve(new Response(JSON.stringify(obsah), { status }))
}

type FalesnaSubscription = { endpoint: string; unsubscribe: ReturnType<typeof vi.fn>; toJSON: () => object }

function falesnaSubscription(): FalesnaSubscription {
  return {
    endpoint: ENDPOINT,
    unsubscribe: vi.fn(() => Promise.resolve(true)),
    toJSON: () => ({ endpoint: ENDPOINT, keys: { p256dh: 'klic', auth: 'auth' } }),
  }
}

/** A browser that can do web push, with or without an existing subscription. */
function prohlizecSPodporou(subscription: FalesnaSubscription | null, opravneni = 'default') {
  const pushManager = {
    getSubscription: vi.fn(() => Promise.resolve(subscription)),
    subscribe: vi.fn((_volby: { userVisibleOnly: boolean }) => Promise.resolve(falesnaSubscription())),
  }
  const registrace = { pushManager }
  const serviceWorker = {
    getRegistration: vi.fn(() => Promise.resolve(subscription ? registrace : undefined)),
    register: vi.fn(() => Promise.resolve(registrace)),
    ready: Promise.resolve(registrace),
  }
  const notification = { permission: opravneni, requestPermission: vi.fn(() => Promise.resolve('granted')) }
  vi.stubGlobal('navigator', { serviceWorker, userAgent: 'Chrome' })
  vi.stubGlobal('PushManager', function PushManager() {})
  vi.stubGlobal('Notification', notification)
  return { pushManager, serviceWorker, notification }
}

function teloPozadavku(volani: number) {
  return JSON.parse(fetchMock.mock.calls[volani][1].body)
}

const panel = () => document.querySelector<HTMLElement>('[data-push-notifikace]')!
const tlacitko = () => document.querySelector<HTMLButtonElement>('[data-push-tlacitko]')!
const stav = () => document.querySelector('[data-push-stav]')!.textContent

beforeEach(() => {
  posluchaci = []
  const pridej = document.addEventListener.bind(document)
  vi.spyOn(document, 'addEventListener').mockImplementation((typ, posluchac, volby) => {
    posluchaci.push([typ, posluchac!])
    pridej(typ, posluchac, volby)
  })
  fetchMock = vi.fn(() => odpovedServeru({ stav: 'vypnuto', zrusitVProhlizeci: false }))
  vi.stubGlobal('fetch', fetchMock)
  document.head.innerHTML = ''
  document.body.innerHTML = ''
  sessionStorage.clear()
})

afterEach(() => {
  posluchaci.forEach(([typ, posluchac]) => document.removeEventListener(typ, posluchac))
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

describe('podpora prohlížeče', () => {
  const okno = (navigator: object, dalsi: object = {}) => ({ navigator, ...dalsi })
  const sPush = { PushManager: function () {}, Notification: {}, Promise, fetch: () => {} }

  it('prohlížeč se service workerem, Push API a notifikacemi je podporovaný', async () => {
    const { zjistiPodporu } = await nactiSkript()
    expect(zjistiPodporu(okno({ serviceWorker: {}, userAgent: 'Chrome' }, sPush))).toBe('ano')
  })

  it('Safari na iPhonu mimo plochu Push API nemá a potřebuje web přidat na plochu', async () => {
    const { zjistiPodporu } = await nactiSkript()
    const iphone = { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) Safari/604.1', serviceWorker: {} }
    expect(zjistiPodporu(okno(iphone, { Notification: {}, Promise, fetch: () => {} }))).toBe('ios-na-plochu')
  })

  it('iPad se hlásí jako Mac, pozná se podle dotykové obrazovky', async () => {
    const { zjistiPodporu } = await nactiSkript()
    const ipad = { userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15', platform: 'MacIntel', maxTouchPoints: 5 }
    expect(zjistiPodporu(okno(ipad))).toBe('ios-na-plochu')
  })

  it('web na ploše starého iOS bez Push API je nepodporovaný, ne „přidej na plochu“', async () => {
    const { zjistiPodporu } = await nactiSkript()
    const iphoneNaPlose = { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X)', standalone: true }
    expect(zjistiPodporu(okno(iphoneNaPlose))).toBe('ne')
  })

  it('web na ploše iOS s Push API je podporovaný', async () => {
    const { zjistiPodporu } = await nactiSkript()
    const iphoneNaPlose = { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X)', standalone: true, serviceWorker: {} }
    expect(zjistiPodporu(okno(iphoneNaPlose, sPush))).toBe('ano')
  })

  it('starý prohlížeč na počítači je nepodporovaný', async () => {
    const { zjistiPodporu } = await nactiSkript()
    expect(zjistiPodporu(okno({ userAgent: 'Mozilla/5.0 (Windows NT 6.1) MSIE 11' }))).toBe('ne')
  })
})

describe('stav, který uvidí uživatel', () => {
  const zaklad = { podpora: 'ano', opravneni: 'granted', stavServeru: 'zapnuto', maSubscription: true }

  it.each<[string, object, Stav]>([
    ['zapnuto na serveru i v prohlížeči', {}, 'zapnuto'],
    ['server subscription nezná', { stavServeru: 'vypnuto' }, 'vypnuto'],
    ['prohlížeč subscription nemá', { maSubscription: false }, 'vypnuto'],
    ['uživatel se ještě nerozhodl', { opravneni: 'default', stavServeru: 'vypnuto', maSubscription: false }, 'vypnuto'],
    ['oprávnění zamítnuté', { opravneni: 'denied', stavServeru: 'vypnuto', maSubscription: false }, 'zamitnuto'],
    ['oprávnění odebrané po zapnutí', { opravneni: 'denied' }, 'zamitnuto'],
    ['admin přepnutý na cizí účet', { stavServeru: 'prepnuto' }, 'prepnuto'],
    ['přepnutý admin se zamítnutým oprávněním', { stavServeru: 'prepnuto', opravneni: 'denied' }, 'prepnuto'],
    ['bez trvalého přihlášení', { stavServeru: 'bez-trvaleho-prihlaseni', maSubscription: false }, 'bez-trvaleho-prihlaseni'],
    ['nepodporovaný prohlížeč', { podpora: 'ne' }, 'nepodporovano'],
    ['iPhone mimo plochu', { podpora: 'ios-na-plochu' }, 'ios-na-plochu'],
  ])('%s', async (_popis, zmena, ocekavany) => {
    const { urciStav } = await nactiSkript()
    expect(urciStav({ ...zaklad, ...zmena })).toBe(ocekavany)
  })

  it('ke každému stavu má stránka text, tlačítko jen tam, kde jde něco přepnout', async () => {
    const { TEXTY } = await nactiSkript()
    const sTlacitkem = Object.entries(TEXTY).filter(([, texty]) => texty.tlacitko).map(([klic]) => klic)
    expect(sTlacitkem.sort()).toEqual(['vypnuto', 'zapnuto'])
    Object.values(TEXTY).forEach((texty) => expect(texty.stav).not.toBe(''))
    expect(TEXTY['ios-na-plochu'].napoveda.join(' ')).toContain('Přidat na plochu')
    expect(TEXTY.zamitnuto.napoveda.join(' ')).toContain('Povolit')
  })
})

describe('veřejný VAPID klíč', () => {
  it('se z base64url převede na bajty, ať má jakoukoli délku', async () => {
    const { naBajty } = await nactiSkript()
    expect(Array.from(naBajty('AQID'))).toEqual([1, 2, 3])
    expect(Array.from(naBajty('AQI'))).toEqual([1, 2])
    expect(Array.from(naBajty('AQ'))).toEqual([1])
    expect(Array.from(naBajty('-_-_'))).toEqual([251, 255, 191])
  })
})

describe('ovládání v Nastavení', () => {
  it('bez nastavení v hlavičce skript nic nedělá (notifikace vypnuté)', async () => {
    document.body.innerHTML = PANEL
    prohlizecSPodporou(null)
    await nactiSkript()
    await dobehni()

    expect(fetchMock).not.toHaveBeenCalled()
    expect(stav()).toBe('Zjišťuji…')
  })

  it('nepodporovaný prohlížeč dostane hlášku a serveru se neptá', async () => {
    document.head.innerHTML = KONFIGURACE
    document.body.innerHTML = PANEL
    vi.stubGlobal('navigator', { userAgent: 'Mozilla/5.0 (Windows NT 6.1) MSIE 11' })
    await nactiSkript()
    await dobehni()

    expect(stav()).toContain('neumí')
    expect(tlacitko().hidden).toBe(true)
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('o oprávnění nežádá při načtení stránky, až po kliknutí', async () => {
    document.head.innerHTML = KONFIGURACE
    document.body.innerHTML = PANEL
    const { notification, serviceWorker, pushManager } = prohlizecSPodporou(null)
    await nactiSkript()
    await dobehni()

    expect(stav()).toContain('vypnuté')
    expect(tlacitko().textContent).toBe('Zapnout notifikace')
    expect(tlacitko().hidden).toBe(false)
    expect(notification.requestPermission).not.toHaveBeenCalled()
    expect(serviceWorker.register).not.toHaveBeenCalled()
    expect(teloPozadavku(0)).toEqual({ akce: 'stav', endpoint: null })

    fetchMock.mockImplementation((_adresa, volby) =>
      odpovedServeru(JSON.parse(volby.body).akce === 'zapnout' ? { stav: 'zapnuto' } : { stav: 'zapnuto', zrusitVProhlizeci: false }),
    )
    const subscription = falesnaSubscription()
    pushManager.subscribe.mockResolvedValue(subscription)
    notification.requestPermission.mockImplementation(() => {
      notification.permission = 'granted'
      return Promise.resolve('granted')
    })
    tlacitko().click()
    await dobehni()
    pushManager.getSubscription.mockResolvedValue(subscription)
    serviceWorker.getRegistration.mockResolvedValue({ pushManager })
    await dobehni()

    expect(notification.requestPermission).toHaveBeenCalledOnce()
    expect(serviceWorker.register).toHaveBeenCalledWith('https://gamecon.test/sw.js', { scope: 'https://gamecon.test/' })
    expect(pushManager.subscribe.mock.calls[0][0].userVisibleOnly).toBe(true)
    const zapnout = fetchMock.mock.calls.map((_volani, i) => teloPozadavku(i)).find((telo) => telo.akce === 'zapnout')
    expect(zapnout.subscription.endpoint).toBe(ENDPOINT)
    expect(fetchMock.mock.calls[0][1].headers['Content-Type']).toBe('application/json')
    expect(fetchMock.mock.calls[0][1].credentials).toBe('same-origin')
  })

  it('zamítnuté oprávnění nic nezaregistruje a ukáže návod', async () => {
    document.head.innerHTML = KONFIGURACE
    document.body.innerHTML = PANEL
    const { notification, serviceWorker } = prohlizecSPodporou(null)
    notification.requestPermission.mockImplementation(() => {
      notification.permission = 'denied'
      return Promise.resolve('denied')
    })
    await nactiSkript()
    await dobehni()

    tlacitko().click()
    await dobehni()

    expect(serviceWorker.register).not.toHaveBeenCalled()
    expect(panel().getAttribute('data-push-notifikace')).toBe('zamitnuto')
    expect(document.querySelector('[data-push-napoveda]')!.textContent).toContain('Povolit')
    expect(tlacitko().hidden).toBe(true)
  })

  it('subscription, kterou server pro uživatele nezná, v prohlížeči zruší', async () => {
    document.head.innerHTML = KONFIGURACE
    document.body.innerHTML = PANEL
    const subscription = falesnaSubscription()
    prohlizecSPodporou(subscription, 'granted')
    fetchMock.mockImplementation(() => odpovedServeru({ stav: 'vypnuto', zrusitVProhlizeci: true }))
    await nactiSkript()
    await dobehni()

    expect(teloPozadavku(0)).toEqual({ akce: 'stav', endpoint: ENDPOINT })
    expect(subscription.unsubscribe).toHaveBeenCalledOnce()
    expect(panel().getAttribute('data-push-notifikace')).toBe('vypnuto')
  })

  it('zapnuté notifikace jde vypnout: nejdřív na serveru, pak v prohlížeči', async () => {
    document.head.innerHTML = KONFIGURACE
    document.body.innerHTML = PANEL
    const subscription = falesnaSubscription()
    prohlizecSPodporou(subscription, 'granted')
    fetchMock.mockImplementation(() => odpovedServeru({ stav: 'zapnuto', zrusitVProhlizeci: false }))
    await nactiSkript()
    await dobehni()
    expect(tlacitko().textContent).toBe('Vypnout notifikace')

    fetchMock.mockImplementation(() => odpovedServeru({ stav: 'vypnuto', zrusitVProhlizeci: false }))
    tlacitko().click()
    await dobehni()

    expect(fetchMock.mock.calls.map((_volani, i) => teloPozadavku(i).akce)).toContain('vypnout')
    expect(subscription.unsubscribe).toHaveBeenCalled()
  })

  it('chybu serveru při zapnutí ukáže a subscription v prohlížeči nenechá', async () => {
    document.head.innerHTML = KONFIGURACE
    document.body.innerHTML = PANEL
    const { pushManager } = prohlizecSPodporou(null, 'granted')
    const subscription = falesnaSubscription()
    pushManager.subscribe.mockResolvedValue(subscription)
    await nactiSkript()
    await dobehni()

    fetchMock.mockImplementation((_adresa, volby) =>
      JSON.parse(volby.body).akce === 'zapnout'
        ? odpovedServeru({ chyba: 'Notifikace jdou zapnout jen na zařízení, kde jsi přihlášen(a) trvale.' }, 409)
        : odpovedServeru({ stav: 'bez-trvaleho-prihlaseni', zrusitVProhlizeci: false }),
    )
    tlacitko().click()
    await dobehni()

    expect(subscription.unsubscribe).toHaveBeenCalledOnce()
    expect(document.querySelector('[data-push-chyba]')!.textContent).toContain('trvale')
  })
})

describe('kontrola na ostatních stránkách', () => {
  it('bez subscription v prohlížeči se serveru neptá', async () => {
    document.head.innerHTML = KONFIGURACE
    prohlizecSPodporou(null, 'granted')
    await nactiSkript()
    await dobehni()

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('se subscription se zeptá a cizí zruší, pak se chvíli neptá', async () => {
    document.head.innerHTML = KONFIGURACE
    const subscription = falesnaSubscription()
    prohlizecSPodporou(subscription, 'granted')
    fetchMock.mockImplementation(() => odpovedServeru({ stav: 'vypnuto', zrusitVProhlizeci: true }))
    await nactiSkript()
    await dobehni()

    expect(fetchMock).toHaveBeenCalledOnce()
    expect(subscription.unsubscribe).toHaveBeenCalledOnce()

    await nactiSkript()
    await dobehni()
    expect(fetchMock).toHaveBeenCalledOnce()
  })

  it('jiný přihlášený uživatel se ověří hned, i když se předchozí ptal před chvílí', async () => {
    document.head.innerHTML = KONFIGURACE
    prohlizecSPodporou(falesnaSubscription(), 'granted')
    await nactiSkript()
    await dobehni()
    expect(fetchMock).toHaveBeenCalledOnce()

    document.head.innerHTML = KONFIGURACE.replace('data-uzivatel="42"', 'data-uzivatel="43"')
    await nactiSkript()
    await dobehni()
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('přepnutému adminovi jeho vlastní subscription nezruší', async () => {
    document.head.innerHTML = KONFIGURACE
    const subscription = falesnaSubscription()
    prohlizecSPodporou(subscription, 'granted')
    fetchMock.mockImplementation(() => odpovedServeru({ stav: 'prepnuto', zrusitVProhlizeci: false }))
    await nactiSkript()
    await dobehni()

    expect(subscription.unsubscribe).not.toHaveBeenCalled()
  })
})
