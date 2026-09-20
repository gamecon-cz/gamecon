import { chromium } from '@playwright/test';

// Porty přiděluje slot worktree a login je konkrétní účet z nahraného dumpu — nic z toho
// neplatí na jiném stroji. Bez proměnných by se skript připojil tam, kde nic neběží, a
// vypadalo by to jako rozbitá větev.
const { GAMECON_PW_NOVY, GAMECON_PW_LEGACY } = process.env;
if (!GAMECON_PW_NOVY || !GAMECON_PW_LEGACY) {
  console.error('✗ Chybí GAMECON_PW_NOVY / GAMECON_PW_LEGACY (např. http://localhost:18020).');
  console.error('  Port větve zjistíš: docker compose --project-directory <worktree> port web 80');
  process.exit(1);
}

const VETVE = [
  { jmeno: 'novy',   url: GAMECON_PW_NOVY },
  { jmeno: 'legacy', url: GAMECON_PW_LEGACY },
];
const LOGIN = process.env.GAMECON_PW_LOGIN ?? 'Bouchi';
const HESLO = process.env.GAMECON_PW_HESLO ?? 'admin';
const KAM = process.env.GAMECON_PW_VYSTUP ?? '/work/playwright';

let neprihlaseno = false;
const browser = await chromium.launch();
// Bez `finally` by výjimka uprostřed (typicky nedostupná větev) nechala běžet Chromium
// až do zabití kontejneru.
try {
for (const { jmeno, url } of VETVE) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 2400 } });
  const page = await ctx.newPage();
  const chyby = [];
  page.on('console', (m) => { if (m.type() === 'error') chyby.push(m.text()); });
  page.on('pageerror', (e) => chyby.push('PAGEERROR: ' + e.message));

  // Rovnou na přihlášení, ne přes /prihlaska: nepřihlášený požadavek si do session uloží
  // hlášku „Tato stránka vyžaduje přihlášení“, která se pak vykreslí i po přihlášení a ve
  // screenshotu vypadá jako chyba aplikace.
  await page.goto(url + '/web/prihlaseni', { waitUntil: 'networkidle' });
  await page.fill('input[name="login"]', LOGIN);
  await page.fill('input[name="heslo"]', HESLO);
  // Altcha je proof-of-work, ne test pro lidi — prohlížeč si ho spočítá sám,
  // jen to chvíli trvá a bez vyřešení je skryté pole prázdné.
  await page.waitForFunction(
    () => document.querySelector('input[name="altcha"]')?.value?.length > 0,
    null, { timeout: 30000 },
  ).catch(() => chyby.push('altcha se nevyřešila'));
  await page.click('input[type="submit"]');
  await page.waitForLoadState('networkidle');

  // Bez tohohle skončí neúspěšné přihlášení screenshotem přihlašovací stránky a hlášením
  // „žádné preact sekce“ — což se čte jako nevykreslená komponenta, ne jako nepřihlášení.
  if (page.url().includes('prihlaseni')) {
    console.error(`✗ ${jmeno}: přihlášení neprošlo (${page.url()})`);
    console.error('   ', chyby.length ? chyby.join(' | ') : 'bez chyby v konzoli');
    neprihlaseno = true;
    await ctx.close();
    continue;
  }

  await page.goto(url + '/prihlaska', { waitUntil: 'networkidle' });

  await page.waitForTimeout(2000);
  await page.screenshot({ path: `${KAM}/prihlaska-${jmeno}.png`, fullPage: true });

  const sekce = await page.locator('[id^="preact-"]').evaluateAll(
    (els) => els.map((e) => `${e.id}=${e.children.length ? 'OK' : 'PRÁZDNÉ'}`));
  console.log(`--- ${jmeno} → ${page.url()}`);
  console.log('    preact sekce:', sekce.length ? sekce.join(' ') : '(žádné)');
  console.log('    chyby:', chyby.length ? chyby.slice(0, 3).join(' | ') : 'žádné');
  await ctx.close();
}
} finally {
  await browser.close();
}

if (neprihlaseno) process.exit(1);
