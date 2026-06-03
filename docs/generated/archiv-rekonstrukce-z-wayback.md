# archiv-rekonstrukce-z-wayback

TL;DR: Jak postavit `NNNN.gamecon.cz` jako **statický archiv rekonstruovaný z Internet Archive (Wayback)** pro ročník, jehož DB i router jsou nenávratně ztracené (≤2011). Liší se od skillu `dockerize-gamecon-year-archive`, který přenáší **živý PHP ročník** do Dockeru — tady žádný funkční backend neexistuje, servírujeme zachycené ploché HTML. Hotovo a živě: **1998–2011** (kompletní). **1998 = absolutní podlaha** — nejstarší ročník, který Wayback vůbec zachytil (starší web GameConu neexistuje). Podlaha deploy guardů (gamecon workflow + ansible deploy script + ci-preview-wrapper) je proto `1998..2099`.

**Éra 1998–2000 — jediná průběžně přepisovaná homepage:** GameCon byl tehdy sekce `altar.cz/gamecon/` a Wayback zachytil jen **jednu** ranou homepage (prosinec 1998, titulek „Gamecon 1998") + samostatné výsledkové stránky `vysledky98/99/00`. Samostatná homepage ročníku 1999/2000 **nikdy zvlášť nezůstala** (web byl jeden přepisovaný oddíl). Řešení (potvrzeno uživatelem): **1998** = pravá zachycená homepage + `vysledky98`; **1999/2000** = dobově věrná Altar-layout homepage (postavená podle 1998 capture) odkazující na výsledky daného roku + předchozí ročníky. Obsah shrnutí 2000 (datum 13.–16. 7. 2000, Olomouc DDM, „mapka") přežil v capture z roku 2001. Logo-thumbnail `small.logo1998.gif` Wayback nezachytil → ukázat na plné `logo1998.jpg` zmenšené. Všechny tři sdílí stejný strom assetů (vychází z `archive/2001` stromu, jen prohozený per-year `index.html`/`.htaccess`/README).

Pro plně dynamické ročníky (2012+) **nepoužívej tento dokument** → použij skill `dockerize-gamecon-year-archive`.

## Kdy tudy
- Ročník nemá zálohu DB ani kódu (predates `d16779_*` éru — tj. ≤2011) a na hostu `/srv/ftp/gamecon.cz/www/gamecon.cz/` pro něj **není** adresář se stromem webu (jen 2011+ tam jsou).
- Web ale **byl** zachycen Waybackem — viz následující sekce, kde hledat.

## Kde hledat zdroj (POZOR: pre-2009 = jiná doména!)
Než cokoli stavíš, najdi reálný zdroj. Pořadí:
1. **Wayback na `gamecon.cz`** (2009+): `curl "http://web.archive.org/cdx/search/cdx?url=gamecon.cz*&collapse=urlkey&from=NNNN0101&to=(NNNN+1)0430&filter=statuscode:200&filter=mimetype:text/html&fl=timestamp,original&limit=300"`. Pokud vrátí jen pár řádků / jen placeholder „web v přípravě", ročník na téhle doméně **nebyl**.
2. **2006–2008 = subdoména `gamecon.altar.cz`**. GameCon do 2008 pořádal **Altar** (vydavatel DrD); předal ho fanouškům mezi 2008→2009. Web 2006–2008 žil na `gamecon.altar.cz` — **úplně jiný CMS, jiný theme, kódování ISO-8859-2** (viz landmine #4). Ověř: `curl "http://web.archive.org/cdx/search/cdx?url=gamecon.altar.cz*&collapse=urlkey&from=NNNN0101&to=NNNN1231&filter=statuscode:200&filter=mimetype:text/html&fl=original&limit=300"`.
3. **2005 a starší = PATH `altar.cz/gamecon/`** (ještě před subdoménou). GameCon byl tehdy **sekce celého publisher webu Altar**, ne samostatný web. Důsledky: stránky servíruj v rootu (`/gamecon/X.html`→`/X.html`, přepiš in-page prefix `/gamecon/`→`/`); globální Altar navigace linkuje po celém parent webu (`/altar/*`, `/drd/`, `/kontakt/`, `/cgi/*`, shop). **SCOPE (aktualizováno červen 2026 — agresivní režim):** uživatel rozhodl tahat **VŠECHNO, na co nav odkazuje** — celé publisher sekce Altaru i `/cgi/doc/*` katalog, ne jen GameCon stránky. Dřívější pravidlo „jen GameCon, zbytek 404" je tím **přebito**. Stránky bez capture zůstávají 404. Viz „Agresivní crawl" níže. Assety (`/stylesheets/default.css`, `/img/gamecon/*`) drž na absolutních cestách. Bonus: archiv výsledků MČR v DrD `vysledky95..NN`. Charset jen v HTTP hlavičce → **viz landmine #7**. Ověř: `curl "http://web.archive.org/cdx/search/cdx?url=altar.cz/gamecon*&collapse=urlkey&from=NNNN0101&to=(NNNN+1)0131&filter=statuscode:200&filter=mimetype:text/html&fl=original&limit=300"`.
4. **Host `/srv`** — má stromy jen **2011+** (`ssh root@gamecon.cz ls /srv/ftp/gamecon.cz/www/gamecon.cz/`). Pro ≤2010 tam nic není.
5. **Google Drive** — jen DB dumpy (nejstarší ~2018), žádné webové stromy starých ročníků. Není zdroj pro statický web.

Reálná zkušenost: **2008 nešel najít na `gamecon.cz`** (jediné capture 2006–2008 byl 807 B placeholder „GameCon 2009 — web v přípravě"). Teprve `gamecon.altar.cz` měl plný 2008 web. Datum/místo festivalu bere z těla stránek, ne z loga (Altar logo žádné datum nemělo). Místo se stěhovalo: **2005–2006 Olomouc**, **2007+ Pardubice**.

## Větev a její tvar
- Větev `archive/NNNN`, ale **NE odbočená z `main`** ve smyslu obsahu. `main` tahá celý živý app (`web/ model/ vendor/ nastaveni/` se secrets) — ten se nesmí zapéct do veřejného image. Cílový strom = jen **archivní payload**: zachycené HTML + `system_styly/ lightbox/` + scaffolding. Viz tvar `git ls-tree archive/2011 --name-only`.
- **Nejpraktičtější (osvědčené):** odboč z **existující sousední `archive/MMMM`** (ne z `main`!) — zdědíš čisté scaffolding bez živého appu, pak `git rm -rq` veškerý obsah souseda a nakopíruj svůj. Tím odpadá ruční mazání `web/model/vendor/...`. (2008 odbočeno z 2009, 2009 z 2011.)
- Pokud přesto odbočíš z `main`: `git read-tree --empty` + přidej zpět jen keep-list a **smaž zbytek z working tree** (kvůli `COPY .` v Dockerfile.archive — `.dockerignore` app NEvylučuje; `git rm --cached` nestačí, `COPY .` bere i untracked → fyzicky smazat `web model vendor admin symfony nastaveni .env .docker .github/workflows/*` kromě `archive-push-redeploy.yml`).

## Scaffolding, který musí na větvi být
Year-agnostic, zdědí se odbočením ze sousední `archive/*`:
- `Dockerfile.archive`, `.dockerignore`
- `.github/workflows/archive-push-redeploy.yml` — dispatcher, na push fírne `deploy-year-archive.yml` na `main`
- `preview/apache-https.conf`, `preview/archive-mpm.conf` — **Dockerfile.archive je COPYuje** (`Dockerfile.archive` řádky ~159/168); když chybí → build padá `"/preview/archive-mpm.conf": not found`
- `.gitignore`, `404.html` (přegeneruj pro daný rok), `README-archive-NNNN.md`
- `favicon.ico` — **2009–2011 sdílí**; 2008 (Altar) má vlastní → použij ten z Waybacku, ne zděděný

Theme se NEdědí jako scaffolding — řiď se sekcí Rekonstrukce bod 6 (2009–2011 kopíruj `system_styly/ lightbox/` z 2011; 2008 stáhni vlastní z Waybacku).

## Rekonstrukce obsahu (Wayback)
1. **Seznam stránek**: jeden CDX dotaz `url=<doména>*&from=NNNN0101&to=<horní mez>&filter=statuscode:200&fl=urlkey,timestamp,original,mimetype` — stáhni VŠECHNY řádky (bez `collapse`) a vyber **latest-per-urlkey** v Pythonu. **Per-URL latest-snapshot dotazy přes CDX jsou neúnosně pomalé** (Wayback throttluje, ~30 s/dotaz) — nepoužívat. **Horní mez okna kriticky urči podle landmine #6** (NE paušálně `(NNNN+1)0430`).
2. **Stažení**: pro každý řádek stáhni `http://web.archive.org/web/<ts>id_/<original>` — sufix **`id_`** = surová kopie bez Wayback toolbaru. Pool ~6 vláken, retry na 5xx **a na `Connection refused`** (Wayback throttluje pod zátěží — to NENÍ chybějící stránka; serial retry s delším backoffem to dobere). Strip GA tracker (`_gaq`/`google-analytics`).
3. **Kódování**: 2009+ (`gamecon.cz`) je UTF-8. **Pre-2009 (`gamecon.altar.cz`) je ISO-8859-2** → dekóduj `iso-8859-2`, přepiš `charset=`/`encoding=` meta na utf-8, ulož UTF-8 (viz landmine #4).
4. **`.htaccess`**: závisí na URL schématu ročníku:
   - **Pretty-URL ročníky (2009–2011)**: jedno pravidlo `RewriteRule ^<pretty>/?$ <file>.html [L]` per stránka + homepage/`novinky` → `index.html` + catch-all `^.*$ - [R=404,L]`.
   - **Flat-file ročníky (2008 Altar)**: odkazy jsou holé `info.html` → soubory se servírují přímo přes `-f/-d` short-circuit, **žádné per-page mapování netřeba**. Jen `DirectoryIndex`, pár `.php`→`.html` redirectů (kde existuje snapshot) a catch-all 404.
5. **`404.html`**: odvoď z reálné zachycené stránky (autentické menu/banner roku), nahraď obsah hláškou „Stránka nebyla archivována" (2009–2011: blok `main-middle-in`; 2008: mezi `gradient-top` a `gradient-bottom`).
6. **Theme**:
   - **2009–2011** sdílí bázi `system_styly/styl1.css` → zkopíruj `system_styly/ lightbox/` z `archive/2011` (superset; ~219/222 assetů, zbytek nezachytitelné `side/*` thumbnaily).
   - **2008 (Altar) má vlastní theme** (`stylesheets/{content,layout,print}.css`, `layout/*.gif`, `img/org/*.gif`) — **NELZE recyklovat z 2011**, stáhni z Waybacku. Pozor: dekorativní `layout/back*.gif`/`gradient-*.gif` Wayback většinou nezachytil (jen `logotype.gif`), ale CSS má solid-color fallbacky → layout drží, jen bez gradientů.

## Agresivní crawl (rozšíření o širší publisher web) — červen 2026

Uživatel rozhodl tahat **všechno, na co nav odkazuje** (celé Altar sekce + `/cgi/doc/*` katalog), ne jen GameCon stránky. Pipeline (path éra 1998–2005, hotová a živá) — skripty v `symfony/var/` (gitignored, drží se i raw cache):

1. **`crawl_aggressive.py <rok>`** — CDX list přes **VŠECHNY** Altar sekce (`altar.cz/{gamecon,altar,arena,battletech,drd,drdplus,dech,hexaedr,akce,odkazy,kontakt,prog,help,cgi/doc}*`), latest-per-urlkey, okno per landmine #6, výstup `captures.json`. **Pozor: použij `https://` endpoint** — `http://` přes Python `urllib` v tomhle prostředí visí (host `curl` přes http funguje, Python ne).
2. **`fetch_transcode.py <rok>`** — stáhne `id_` snapshoty, transcoduje, ukládá do `agg/<rok>/site/`. Klíčové:
   - **`?kosik=NNN` (cart-session) varianty zahoď** — duplicitní shop snapshoty pod throwaway session URL, ne obsah. (2003: 2463 z 3376 captures byly tyhle dupy.)
   - **Reklama blok**: strip všeho mezi `<!-- Reklama - start -->` a `<!-- Reklama - konec -->` (uvnitř žije `adrenaline.cz`/billboard/Mr.Lin(x) banner script; markery nech prázdné). Tohle je **hlavní** ad formát path éry, ne jen BBSTART/Mr.Lin(x) komentáře. **Strip podle MARKERŮ bloku, ne podle konkrétního ad-hostu** — hostname se liší: `ad.linx.cz` **i** `ad.linxcz.cz`, `Mr.Linx.cz` **i** `Mr.linxcz.cz` (varianta `linxcz`!), `ad.billboard.cz`, `adrenaline.cz`, `ereklama.cz`. Per-host regex tu `linxcz` variantu minul → bannery přežily do commitnutých stránek a musely se dočišťovat zvlášť. Když strippeš celý blok, je jedno který z hostů je uvnitř.
   - **Charset (landmine #7) dvojím způsobem**: (a) přepiš existující `charset=windows-1250`→`utf-8` (jinak deklarace lže o utf-8 bajtech → mojibake), (b) navíc **vlož** `<meta charset>` když žádná deklarace v hlavičce není.
   - **Extension-less cesty** (`/cgi/doc/katalog`, `/drd/faq/`) = CGI-doc router → ulož jako `<path>/index.html`.
   - **Raw cache** (`agg/<rok>/raw/*.raw`): ukládej surové bajty PŘED transcodem, ať oprava transcode logiky nevyžaduje re-fetch z Waybacku.
   - **Throttle**: pool 4 vlákna; Wayback odmítá část souběžných reqů `Connection refused` (HTTP 000) — **to NENÍ 404**. Serial retry pass s delším backoffem to dobere (reálně 142 failů → 0 po serial passu). Skutečné 404 (CDX hlásí 200, ale `id_` je pryč — typicky dynamické `prihlaska.html` formuláře) zůstanou 404; to je OK.
3. **Integrace `integrate.sh <rok>`** — **ADITIVNÍ ONLY**: zkopíruj jen stránky, které větev ještě NEMÁ; existující commitnuté (homepage se switcherem, ověřené výsledky) **nikdy nepřepisuj**. Stage přes `git add -- . ':!.envrc' ':!symfony' ':!var'`, abort když by se cokoli **modifikovalo** (additive = jen `A` statusy) nebo když by se stageovalo `symfony`/`.envrc`.

Reálné výsledky path éry: 1998=712, 1999=749, 2000=79 (Wayback má málo), 2001=610, 2002=241, 2003=1000, 2004=584, 2005=587 souborů.

**Pozdější éry (hotovo):** 2006–2008 (`gamecon.altar.cz`, **ISO-8859-2** — byte-ověřeno: 0xb9→š, 0xbe→ž, 0xa9→Š; cp1250 by to rozbilo) byly samostatné weby a už byly kompletní (0 nových). 2009–2011 (`gamecon.cz`, UTF-8, pretty-URL přes per-page RewriteRule) měly **mezery** doplněné: 2009 +26 (profily organizátorů), 2011 +59 (RPG/larp/přednášky popisy). Crawler je éra-aware (`crawl_aggressive.py` HOST list + `meta.json` encoding).

**Restaurace obrázků (`restore_images.py`):** stránky se servírovaly s rozbitými `<img>` — crawl tahal jen HTML/CSS, ne obrázky. Skript sebere image refs z commitnutých HTML/CSS, nechá jen ty, které větev NEMÁ, namatchuje na Wayback CDX a stáhne `id_` **binárně (žádný transcode)**. Klíčové: **CDX okno pro obrázky musí být ŠIROKÉ** (path éra `1998..2006`, ne per-rok) — sdílené `altar.cz/img/*` assety žijí napříč érou, takže glyph zachycený 2005 patří i do 2003. Integrace aditivní; `.gif/.jpg/...` se servírují přímo (žádné RewriteRule). Reálně doplněno path éra 1998–2005 (+8–32 obrázků/rok); 2006–2011 už obrázky měly.

### Landmine #8: literal space v RewriteRule pattern → Apache 500 na VŠECHNO
2009–2011 mají pretty-URL s **mezerami** (`organizatori/Diskor - herní profil`). `RewriteRule ^organizatori/Diskor - herní profil/?$  ...` — Apache dělí direktivu na whitespace, takže mezera v patternu = **syntax error v .htaccess → 500 na každý request** (i homepage). Fix: obě části **uvozovkovat** a v patternu mezery jako `\s`: `RewriteRule "^organizatori/Diskor\s\-\sherní\sprofil/?$"  "organizatori/Diskor - herní profil.html" [L]`.

## Landmines, které jsme reálně chytili

### 1. Logo ukazuje špatný rok (jen `gamecon.cz`-éra, 2009–2011)
Hostový `system_styly/pics_system_styl1/logo.gif` byl při zachycení (úno 2012) už **přepsaný na 2012** → masthead píše špatný rok. Wayback historická loga **nezachytil**. Logo 2009–2011 má **datum/místo zapečené v obrázku**, takže ho musíš zrekonstruovat: vezmi wordmark+kostku z 2011 `logo.gif.bak`, smaž starý podtitul (pruh y≥87), vykresli nový text (Liberation Sans ~23px, cap ~16px, barva `#111`, vystředěné) — viz git historie 2010/2009 logo commitů. Datum/místo **ověř s uživatelem** (2009 = Pardubice, NE Česká Třebová — venue se stěhoval; 2010 = Česká Třebová). **2008 (Altar) logo žádné datum nemá** → ber `layout/logotype.gif` jak je, nic nerekonstruuj.

### 2. Absolutní odkazy utíkají na živý web
Zachycené HTML má odkazy `http://www.gamecon.cz/…` (logo, drobečky, in-page) → klik **opustí archiv** pro živý web. Přepiš všechny `https?://(www\.)?gamecon\.cz/<path>` → `/<path>` (bare host → `/`). Pozor: regex match jen uvnitř `"..."`; holé URL v textu odkazu (`<a href="/x">http://gamecon.cz/x</a>`) jsou neškodné (href je relativní).

### 3. `/forum` a `/novinky` vracejí 403
Pokud existuje **adresář** `novinky/` (drží substránky), `.htaccess` `RewriteCond -d` ho short-circuitne a Apache vrátí **403** (žádný DirectoryIndex). Fix: pravidlo `^novinky/?$ index.html [L]` (resp. `^forum/?$ forum.html`) dej **PŘED** `-f/-d` blok. Bez adresáře problém není. (Platí i 2008: `/forum`… analogicky, kde adresář existuje.)

### 4. Pre-2009 = jiná doména, jiný CMS, jiné kódování (Altar éra)
2008 a starší **nejsou na `gamecon.cz`** (viz „Kde hledat zdroj"). Žil na `gamecon.altar.cz` jako **úplně jiný web**: ploché ručně psané `.html` v rootu, vlastní theme (`stylesheets/*.css` + `layout/*.gif`), **kódování ISO-8859-2**. Důsledky: (a) nerecykluj `styl1` theme; (b) každou stránku i CSS transcoduj `iso-8859-2`→UTF-8 a přepiš charset meta; (c) `.htaccess` je flat-file (žádné pretty-URL mapy); (d) přepisuj absolutní odkazy `gamecon.altar.cz`, ne `gamecon.cz`; (e) dynamické `.php` (přihláška, kniha návštěv, dotazníky, on-line seznamy) nemají backend → skip, catch-all 404 (kde přežil statický snapshot `.php` stránky, redirectni `.php`→`.html`).

### 5. `[R]` redirect s relativním cílem → `/var/www/...` 404
`RewriteRule ^x\.php$ x.html [R=301,L]` (relativní cíl) Apache při **externím** redirectu rozvine proti **filesystem DocumentRootu** → `Location: https://host/var/www/html/gamecon/x.html` = 404. Fix: cíl **root-relativní s lomítkem** — `RewriteRule ^x\.php$ /x.html [R=301,L]`. (Interní `[L]` rewrite relativní cíl snese; problém je jen u `[R]`.)

### 6. Okno CDX přetekne do dalšího ročníku (latest-per-URL chytne špatný rok)
Doména (`gamecon.cz` i `gamecon.altar.cz`) se recykluje rok po roce na stejných URL. „Latest-per-urlkey" v širokém okně proto chytne **už překlopený obsah dalšího ročníku** — typicky homepage, kterou přepíšou na „save the date" pro příští rok dřív, než zmizí zbytek letošního webu. Reálně: 2007 s oknem do `2008-04-30` dostal homepage z capture 2008-03-17 = datum „10.–13." (2008) místo „12.–15." (2007), přičemž novinky ještě byly 2007 → tichý mix. **Fix:** než zafixuješ horní mez, projdi homepage capture timeline (`url=<doména>&from=…&to=…&fl=timestamp,length`, příp. stáhni pár `id_` a koukni na datum/`<h1>`) a **najdi bod překlopení** na příští ročník; okno ukonči **těsně před ním** (2007 → `to=20080131`). Po fetchi vždy ověř, že `index.html` ukazuje datum/novinky **správného roku**.

### 7. Stránka deklaruje charset jen v HTTP hlavičce → statický servis ho ztratí → mojibake
Staré weby (zejm. `altar.cz/gamecon/` éra, 2005 a starší) **nemají `<meta charset>` v `<head>`** — charset posílal jen živý server v `Content-Type` hlavičce. Zachycený `id_` soubor tu hlavičku ztratí a archivní servis pošle `Content-Type: text/html` **bez charsetu**. Bajty jsou přitom správně (UTF-8 i po transcodu), ale prohlížeč bez deklarace spadne na legacy default (Win-1250/Latin-1) → **mojibake** (`Äervence` místo `července`), i když `file -i` i `curl | iconv` ukazují validní text. Pozor: `re.sub('charset=iso-8859-2', 'charset=utf-8')` tady **nic neudělá** (žádný meta není). **Fix:** vlož `<meta charset="utf-8">` jako **první prvek hned za `<head>`** na každou stránku (musí být v prvních ~1024 B, před `<title>`). Reálně 2005 (commit `bbd4a5811`). 2006–2008 to neměly — měly `http-equiv` meta v `<head>`, takže stačil rewrite na utf-8. **Vždy ověř živě** `curl -sI` / grep `<meta charset` ve served HTML, ne jen `file -i` (ten čte bajty, ne deklaraci).

### 8. Mrtvé reklamy a CGI-generovaná navigace = pomalé/poskakující načítání (Altar éra)
Altar stránky vkládaly do těla **dvě věci, které na statickém archivu blokují/zpomalují načtení** a vypadají jako bug („stránka se divně dlouho načítá, levé menu poskakuje po dávkách" — divné u statiky):
- **Mrtvé reklamní embedy.** `<DIV class="rbl reklama">` s `document.write` iframe/img na **dávno mrtvé** ad domény (`adx1.adrenaline.cz`, u 2003 navíc `ad2.billboard.cz` v blocích `<!-- BBSTART -->`…`<!-- BBEND -->`). Prohlížeč na ně **visí až do timeoutu** → pomalé načtení. **Jen path éra `altar.cz/gamecon/` (2003–2005)** — to byla reklama publisher webu Altar. **Subdoména `gamecon.altar.cz` (2006–2008) je čistá** (jiný/fanouškovský CMS, žádné publisher bannery) — prověřeno sweepem všech stránek, nic strippovat netřeba. Fix (path éra): smaž celý `rbl reklama` div (+ billboard BBSTART/BBEND blok) — regex `<DIV\s+CLASS="rbl reklama">.*?<!--\s*Reklama - konec\s*-->\s*</DIV>` (DOTALL). Reálně: 2003 (`9b3899734`), 2004 (`18ac802c6`), 2005 (`a7b680a96`).
- **CGI-renderovaná navigace** (jen **starší** captures, viděno u 2003, NE u 2004/2005). Menu = ~22× `<a href="/altar/…"><img src="/cgi/text?Label" alt="Label"></a>` — každý label byl **obrázek generovaný CGI**. Na statice všech ~22 `/cgi/text?` **404 přes catch-all** (a catch-all vrací **celé `404.html` tělo** na každý!) → menu se dosazuje po dávkách, layout poskakuje. Fix: nahraď každý `<img src="/cgi/text?…" alt="X">` jeho `alt` textem → menu jako textové odkazy (jako 2004/2005). Regex: zachovej obklopující `<a>` (zůstane themed-404 odkaz), jen vyřízni `<IMG SRC="/cgi/text\?[^"]*"[^>]*>` → `alt`.

**Po fixu ověř requesty**: homepage by měla tahat jen pár same-origin `/img/*` (2003 spadlo z 24+ requestů na 3). Tohle se snadno přehlédne, protože `index.html` jako bajty vypadá OK — problém je až v běhu v prohlížeči (síťové requesty). Strip skript: viz `symfony/var/strip_ads.py` šablona v historii.

## Year-guards — TŘI místa (rozšířit dolní mez)
Spodní mez se s každým ročníkem posouvá dolů (`2011`→…→**2005**). Nový rok pod aktuální mezí musí projít **třemi** guardy, jinak deploy padne. Najdi aktuální `year < NNNN` ve všech třech a sniž:
1. **gamecon repo** `.github/workflows/deploy-year-archive.yml` — range guard + `case` mapy `base_image` (`php:5.6-apache` pro statický) a `php_exts` (`""` pro statický → přidej řádek `NNNN) ... ;;`).
2. **ansible repo** `roles/year_archive_deployer/files/deploy-year-archive.sh` — `year < NNNN` guard + doc komentáře (`range 2009..2099` apod.).
3. **ansible repo** `roles/preview_deployer/files/ci-preview-wrapper.sh` — **forced-command wrapper pro CI SSH klíč**, vlastní guard ve DVOU větvích (deploy + `--remove`) + doc komentář. Tohle se snadno přehlédne — CI deploy přes SSH jde přes tenhle wrapper, ne přímo na skript. Chyba: `ci-preview-wrapper: year NNNN outside ...`.

Precedent PRs: 2011 (gamecon #892, ansible #40/#41), 2009 (gamecon #894, ansible #42), 2008 (gamecon #895, ansible #43).

Po merge ansible PRs je nutný **`make deploy`** (uživatel, interaktivní prompt) aby se wrapper i skript dostaly na host. Občasný CI flake při merge: „Run tests job" spadne na Docker Hub timeoutu (`registry-1.docker.io … Client.Timeout`) — nesouvisí se změnou, `gh run rerun <id> --failed`.

## Operační sekvence
1. Merge obou repo guard-PRs → **`make deploy`** (uživatel) → ověř na hostu `grep NNNN /usr/local/sbin/ci-preview-wrapper /usr/local/sbin/deploy-year-archive.sh`.
2. `git push origin archive/NNNN` → dispatcher → `deploy-year-archive.yml` build + deploy. (push až PO `make deploy`, host skript musí znát nový rok)
3. Kontejner `gamecon-archive-NNNN`, DB `gamecon_NNNN`, Caddy vhost se zapíše automaticky, DNS je wildcard `*.gamecon.cz` → není potřeba řešit.
4. **Cross-link ze sousedního ročníku** — jen pokud soused linkuje **nezachycenou** retrospektivu na tenhle rok. 2011 linkoval `/archiv/<sekce>-2010` (nezachycené) → redirect na `2010.gamecon.cz` (`RewriteRule ^archiv/rpg-2010/?$ https://2010.gamecon.cz/rpg/o-rpg-na-gc [R=301,L]`). **2009 ani 2008 cross-link nepotřebovaly** — sousedi své „…-2009"/„…-2008" retrospektivy servírují interně z vlastního stromu. Ověř `git grep 'href=.*/archiv/.*-NNNN' archive/<soused>` než něco přidáš.
5. **Ověř živě** přes gate cookie (`?gate=<token>` → cookie `gc_gate`, je `.gamecon.cz`-scoped, sdílený napříč archiv subdoménami; token z libovolné archiv subdomény platí i pro novou). Crawler: BFS od `/`, posílej cookie hlavičkou (Pythonní `MozillaCookieJar` neumí `#HttpOnly_` řádek → nastav `Cookie:` hlavičku ručně). Pozor na false-positive 404 — odděl legitimně mrtvé (`.php`, nezachycené stránky/assety) od reálných chyb.

## Co zůstane mrtvé (legitimní mezery, ne chyba)
- Nezachycené stránky (Wayback je prostě nemá) → catch-all 404.
- `X o sobě` / `herní profil` pseudo-URL = placeholder href toggle odkazů `onclick="ukaz(N)"`, ne stránky. Nemapuj; `href="#"`. `ukaz()` byl v ztraceném `/java.js` → reimplementuj inline na org stránkách (toggle `display` na `#objektN`).
- `/download/*`, `/galerie/*` — u 2011 přežily na hostu a jsou **bind-mountnuté** (viz README-archive-2011.md); u 2010 neexistují a Wayback je nezachytil.

## Vstupní body
- Precedent (na příslušné `archive/NNNN` větvi): `README-archive-2011.md`, `README-archive-2010.md`, `README-archive-2009.md` (pretty-URL éra); `README-archive-2008.md` (Altar éra — jiná doména/CMS/kódování)
- Dockerfile: `Dockerfile.archive` (year-agnostic, `BASE_IMAGE`/`PHP_EXTS` build-args)
- Host skript: ansible `roles/year_archive_deployer/files/deploy-year-archive.sh` (mount guards jsou dir-based → year-agnostic)
- Skill pro dynamické ročníky (jiný případ): `dockerize-gamecon-year-archive`
