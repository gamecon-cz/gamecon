# archiv-rekonstrukce-z-wayback

TL;DR: Jak postavit `NNNN.gamecon.cz` jako **statický archiv rekonstruovaný z Internet Archive (Wayback)** pro ročník, jehož DB i router jsou nenávratně ztracené (≤2011). Liší se od skillu `dockerize-gamecon-year-archive`, který přenáší **živý PHP ročník** do Dockeru — tady žádný funkční backend neexistuje, servírujeme zachycené ploché HTML. Postaveno na precedentu 2011 a 2010; 2009 a starší půjdou stejně.

Pro plně dynamické ročníky (2012+) **nepoužívej tento dokument** → použij skill `dockerize-gamecon-year-archive`.

## Kdy tudy
- Ročník nemá zálohu DB ani kódu (predates `d16779_*` éru — tj. ≤2011) a na hostu `/srv/ftp/gamecon.cz/www/gamecon.cz/` pro něj **není** adresář se stromem webu (jen 2011+ tam jsou).
- Web ale **byl** zachycen Waybackem (ověř: `curl "http://web.archive.org/cdx/search/cdx?url=gamecon.cz&from=NNNN0101&to=NNNN1231&fl=timestamp,statuscode&limit=5"`).

## Větev a její tvar
- Větev `archive/NNNN`, ale **NE odbočená z `main`** ve smyslu obsahu. `main` tahá celý živý app (`web/ model/ vendor/ nastaveni/` se secrets) — ten se nesmí zapéct do veřejného image. Cílový strom = jen **archivní payload**: zachycené HTML + `system_styly/ lightbox/` + scaffolding. Viz tvar `git ls-tree archive/2011 --name-only`.
- Prakticky: odboč z `main`, vygeneruj obsah, pak `git read-tree --empty` + přidej zpět jen keep-list (HTML + theme + scaffolding) a **smaž zbytek z working tree** (kvůli `COPY .` v Dockerfile.archive — `.dockerignore` app NEvylučuje). Gotcha: `git rm --cached` + ponechání souborů nestačí, `COPY .` bere i untracked → fyzicky smazat `web model vendor admin symfony nastaveni .env .docker .github/workflows/*` (kromě `archive-push-redeploy.yml`).

## Scaffolding, který musí na větvi být
Zkopíruj z `archive/2011` (vše year-agnostic):
- `Dockerfile.archive`, `.dockerignore`
- `.github/workflows/archive-push-redeploy.yml` — dispatcher, na push fírne `deploy-year-archive.yml` na `main`
- `preview/apache-https.conf`, `preview/archive-mpm.conf` — **Dockerfile.archive je COPYuje** (`Dockerfile.archive` řádky ~159/168); když chybí → build padá `"/preview/archive-mpm.conf": not found`
- `favicon.ico`, `.gitignore`, `404.html` (přegeneruj pro daný rok), `README-archive-NNNN.md`

## Rekonstrukce obsahu (Wayback)
1. **Seznam stránek**: jeden CDX dotaz `url=gamecon.cz*&collapse=urlkey&from=NNNN0101&to=(NNNN+1)0430&filter=statuscode:200&filter=mimetype:text/html&fl=timestamp,original` (`collapse=urlkey` = první zachycení per URL; vrací rychle, ~sekundy). **Per-URL latest-snapshot dotazy jsou neúnosně pomalé** (Wayback throttluje, ~30 s/dotaz) — nepoužívat.
2. **Stažení**: pro každý řádek stáhni `http://web.archive.org/web/<ts>id_/<original>` — sufix **`id_`** = surová kopie bez Wayback toolbaru. Pool ~6 vláken, retry na 5xx (Wayback má výpadky). Strip GA tracker (`_gaq`/`google-analytics`).
3. **`.htaccess`**: jedno pravidlo `RewriteRule ^<pretty>/?$ <file>.html [L]` per stránka + homepage/`novinky` → `index.html` + catch-all `^.*$ - [R=404,L]`. Stejná struktura jako 2011 `.htaccess`.
4. **`404.html`**: odvoď z reálné zachycené stránky (autentické menu/banner roku), nahraď obsah `main-middle-in` hláškou „Stránka nebyla archivována".
5. **Theme**: `system_styly/ lightbox/` zkopíruj z `archive/2011` (sdílí bázi `styl1.css`); ~219/222 referencovaných assetů tam je, zbytek jsou nezachytitelné `side/*` thumbnaily (akceptovatelné mezery).

## Tři landmines, které jsme reálně chytili

### 1. Logo ukazuje špatný rok
Hostový `system_styly/pics_system_styl1/logo.gif` byl při zachycení (úno 2012) už **přepsaný na 2012** → masthead píše špatný rok. Starší logo je v `logo.gif.bak` (na 2011 = správné „2011 Česká Třebová"). Wayback historická loga **nezachytil** (jediné capture logo.gif je z 12/2011 = 2012). Pro 2010 ani 2011 pravé logo neexistuje → použili jsme `.bak` (2011) jako nejbližší. (záměr: ověřit s uživatelem, jaký rok logo má mít.)

### 2. Absolutní odkazy utíkají na živý web
Zachycené HTML má odkazy `http://www.gamecon.cz/…` (logo, drobečky, in-page) → klik **opustí archiv** pro živý web. Přepiš všechny `https?://(www\.)?gamecon\.cz/<path>` → `/<path>` (bare host → `/`). Pozor: regex match jen uvnitř `"..."`; holé URL v textu odkazu (`<a href="/x">http://gamecon.cz/x</a>`) jsou neškodné (href je relativní).

### 3. `/forum` a `/novinky` vracejí 403
Pokud existuje **adresář** `novinky/` (drží substránky), `.htaccess` `RewriteCond -d` ho short-circuitne a Apache vrátí **403** (žádný DirectoryIndex). Fix: pravidlo `^novinky/?$ index.html [L]` (resp. `^forum/?$ forum.html`) dej **PŘED** `-f/-d` blok. Bez adresáře problém není.

## Year-guards — TŘI místa (všechna na `2011..2099`, rozšířit dolů)
Nový rok pod 2011 musí projít **třemi** guardy, jinak deploy padne:
1. **gamecon repo** `.github/workflows/deploy-year-archive.yml` — range guard + `case` mapy `base_image` (`php:5.6-apache` pro statický) a `php_exts` (`""` pro statický). (gamecon PR #892)
2. **ansible repo** `roles/year_archive_deployer/files/deploy-year-archive.sh` — `year < 2011` guard. (ansible PR #40)
3. **ansible repo** `roles/preview_deployer/files/ci-preview-wrapper.sh` — **forced-command wrapper pro CI SSH klíč**, vlastní guard ve DVOU větvích (deploy + `--remove`). Tohle se snadno přehlédne — CI deploy přes SSH jde přes tenhle wrapper, ne přímo na skript. Chyba: `ci-preview-wrapper: year NNNN outside 2011..2099`. (ansible PR #41)

Po merge ansible PRs je nutný **`make deploy`** (uživatel, interaktivní prompt) aby se wrapper i skript dostaly na host.

## Operační sekvence
1. Merge obou repo guard-PRs → **`make deploy`** (uživatel) → ověř na hostu `grep 2010 /usr/local/sbin/ci-preview-wrapper /usr/local/sbin/deploy-year-archive.sh`.
2. `git push origin archive/NNNN` → dispatcher → `deploy-year-archive.yml` build + deploy. (push až PO `make deploy`, host skript musí znát nový rok)
3. Kontejner `gamecon-archive-NNNN`, DB `gamecon_NNNN`, Caddy vhost se zapíše automaticky, DNS je wildcard `*.gamecon.cz` → není potřeba řešit.
4. **Cross-link ze sousedního ročníku**: starší `archive/2011` linkoval `/archiv/<sekce>-2010` (nezachycené) → přesměruj na `NNNN.gamecon.cz` sekční stránku (`RewriteRule ^archiv/rpg-2010/?$ https://2010.gamecon.cz/rpg/o-rpg-na-gc [R=301,L]`).
5. **Ověř živě** přes gate cookie (`?gate=<token>` → cookie `gc_gate`, je `.gamecon.cz`-scoped, sdílený napříč archiv subdoménami). Crawler: BFS od `/`, posílej cookie hlavičkou (Pythonní `MozillaCookieJar` neumí `#HttpOnly_` řádek → nastav `Cookie:` hlavičku ručně).

## Co zůstane mrtvé (legitimní mezery, ne chyba)
- Nezachycené stránky (Wayback je prostě nemá) → catch-all 404.
- `X o sobě` / `herní profil` pseudo-URL = placeholder href toggle odkazů `onclick="ukaz(N)"`, ne stránky. Nemapuj; `href="#"`. `ukaz()` byl v ztraceném `/java.js` → reimplementuj inline na org stránkách (toggle `display` na `#objektN`).
- `/download/*`, `/galerie/*` — u 2011 přežily na hostu a jsou **bind-mountnuté** (viz README-archive-2011.md); u 2010 neexistují a Wayback je nezachytil.

## Vstupní body
- Precedent: `README-archive-2011.md`, `README-archive-2010.md`
- Dockerfile: `Dockerfile.archive` (year-agnostic, `BASE_IMAGE`/`PHP_EXTS` build-args)
- Host skript: ansible `roles/year_archive_deployer/files/deploy-year-archive.sh` (mount guards jsou dir-based → year-agnostic)
- Skill pro dynamické ročníky (jiný případ): `dockerize-gamecon-year-archive`
