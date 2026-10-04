# Playwright pro diferenční scénáře

Scénáře se nejdřív ověřují programově (rychlé, přesné na výpočty), **ale nakonec musí
projít i prohlížečem** — kód neověří, že se komponenta vykreslí a reaguje na klikání.

```bash
S=<scratch>          # kam se uloží screenshoty
# Porty obou větví (slot worktree, ne pevná čísla). `vetve.sh` cesty odvodí z umístění
# skriptu, takže pokud pouštíš harness z jiného worktree, než kde běží nová větev,
# nastav si `GAMECON_DIFF_NOVY` (a případně `GAMECON_DIFF_LEGACY`) na ty se stackem:
source bin-diff/vetve.sh
N=$(docker compose --project-directory "$NOVY"   port web 80 | cut -d: -f2)
L=$(docker compose --project-directory "$LEGACY" port web 80 | cut -d: -f2)

docker run --rm --network host --user "$(id -u):$(id -g)" -e HOME=/tmp -v "$S":/work \
    -e GAMECON_PW_NOVY="http://localhost:$N" -e GAMECON_PW_LEGACY="http://localhost:$L" \
    pw-shot:latest bash -lc 'ln -sfn /opt/pw/node_modules /work/playwright/node_modules \
        && cd /work/playwright && node prihlaska.mjs; rm -f /work/playwright/node_modules'
```

Účet se dá přebít přes `GAMECON_PW_LOGIN` / `GAMECON_PW_HESLO` (výchozí je účet
z nahraného dumpu) a cíl screenshotů přes `GAMECON_PW_VYSTUP`.

## Co se naučilo cestou

- **Bez posunutého času není co testovat.** Na skutečné datum je GC dávno po konci a
  `/prihlaska` ukazuje jen „GameCon už proběhl". Nejdřív `bin-diff/cas.sh 2026-06-01`.
- **Přihlášení je na `/web/prihlaseni`**, pole `login` a `heslo`, heslo `admin`
  (`UNIVERZALNI_HESLO` v `docker-compose.override.yml`).
- **Altcha není CAPTCHA pro lidi**, ale proof-of-work — prohlížeč si ji spočítá sám, jen
  to chvíli trvá. Musí se počkat, až se naplní skryté `input[name="altcha"]`, jinak
  odeslání projde bez řešení a server ho odmítne.
- **Chodit na `/prihlaska` před přihlášením si uloží do session hlášku „Tato stránka
  vyžaduje přihlášení"**, která se pak zobrazí i po přihlášení a vypadá jako chyba.
  Přihlašovat se rovnou přes `/web/prihlaseni`.
- **`localhost:3000` u obrázku** je `GAMECON_KONSTANTY_DEFAULT.BASE_PATH_PAGE` z
  `ui/src/env.ts` — přihlašovací stránka nedostává serverové konstanty. Na přihlášené
  stránce se to neprojeví.
- **`ERR_ABORTED` na `google-analytics.com`** je jen offline headless prohlížeč, ne chyba.

## Co je a co není důkaz

- **HTTP status není výsledek.** `POST` na kolekci vrací `201 Created` vždycky — neříká,
  kolik položek se objednalo ani jestli správně. Dva `201` znamenají dva požadavky, nic víc.
  Nahlásil jsem „dva `201` = obě jídla se propsala", což byla shoda náhod, ne důkaz.
- **Důkazem je tělo požadavku a stav v DB.** U zápisů, které posílají *celý výběr*
  (`MealWriter`, `AccommodationWriter`), je klíčové, co nese **druhý** požadavek: musí to
  být stávající výběr plus nový kus. Když nese jen ten nový, znamená to „nech mu jen tohle"
  a předchozí zmizí.
- **Odchytávat obojí:**
  ```js
  page.on('request',  (r) => { if (r.method() === 'POST') console.log('→', r.postData()); });
  page.on('response', (r) => console.log('←', r.status()));
  ```
  a po doklikání se podívat do `shop_nakupy`, kolik řádků reálně vzniklo.
