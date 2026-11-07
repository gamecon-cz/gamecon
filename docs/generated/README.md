# docs/generated — index a pravidla

Dokumenty v `docs/generated/` jsou **vstupní bod pro Claude** při práci s většími koncepty GameConu. Záměr: místo opakovaného prohledávání kódu má Claude připravený destilovaný souhrn.

Pravidla **kdy** konzultovat / vytvořit dokument jsou v kořenovém `CLAUDE.md`. Tento soubor popisuje **jak** ho napsat a udržuje index.

## Index

<!-- Formát: `- [nazev-souboru](nazev-souboru.md) — jedna věta o čem to je` -->

- [sledovani-aktivit](sledovani-aktivit.md) — watchlist aktivity (mail při uvolnění místa); kde se zobrazuje „sledovat/zrušit", jediný trigger mailu v `odhlas()` podle rozdílu `volnoPro()` před/po odhlášení; tři chyby u genderově rozdělených aktivit (volno jen pro opačné pohlaví) — všechny opravené
- [prepnuti-na-uzivatele](prepnuti-na-uzivatele.md) — přihlášení v adminu jako libovolný uživatel; dřív hardcoded „superadmin“, nově dočasné ročníkové právo udělované radou
- [prihlasovani-na-skryte-technicke-aktivity](prihlasovani-na-skryte-technicke-aktivity.md) — posazování na nepublikované (NOVA) technické/brigádnické aktivity přes příznak `Aktivita::INTERNI`; výjimka vázaná na typ (`jeInterni()`), ne na roli; dnes gated přes prezenčního admina (Pravo 103); proč to přestalo fungovat po migraci programu na Preact (ztracený `Program::INTERNI`); časová brána registrace
- [odkazy-do-archivnich-rocniku](odkazy-do-archivnich-rocniku.md) — proklik z ostrého adminu na tentýž objekt v archivním ročníku; archivy jedou na `ROK.gamecon.cz/admin` (ne `admin.ROK.gamecon.cz`), URL potřebuje tři parametry naráz (`gate` na bránu, `gcsso` na přihlášení, akční parametr), proč `gcsso` + `pracovni_uzivatel` na jedné URL funguje, a tři degradační stupně podle stáří ročníku (`pracovni_uzivatel` až od 2022, `gcsso` od 2014, živý admin od 2011, starší bez odkazu); také stránka Previews, kde odkaz na web i na admin přihlásí přes `gcsso` a obsluhu `gcsso` sdílí admin a veřejný web
- [archiv-rekonstrukce-z-wayback](archiv-rekonstrukce-z-wayback.md) — postavení `NNNN.gamecon.cz` jako statického archivu z Internet Archive pro ročníky bez DB/kódu (≤2011, hotovo 2005–2011); kde hledat zdroj (2006–2008 = `gamecon.altar.cz`, 2005− = path `altar.cz/gamecon/`, jiný CMS, ISO-8859-2), tři year-guardy, landmines (logo, absolutní odkazy, 403, jiný CMS, `[R]` redirect, CDX okno přeteče do dalšího roku, chybějící charset → mojibake), operační sekvence
- [nahravani-obrazku](nahravani-obrazku.md) — nahrávání obrázků z administrace místo FTP: kde leží fotky vypravěčů, obrázky linií, e-shopu, textů a log, kdo je čte a který modul je spravuje; společná služba `NahravaniObrazku` (typ z obsahu, limity, cesty jen uvnitř kořene, varianty přípon, blokace mazání použitého obrázku); ruční převod stávajících obrázků z textů webu do AVIF příkazem `obrazky:prevest-na-avif` (co se převádí a co ne, přepis odkazů, pojistky); gotchas (`post_max_size` zahodí celý POST, proměnné modulu ve scope `admin/index.php`, `loginToAdmin()` v testech nepřihlásí)
- [prihlaska-odeslani-objednavky](prihlaska-odeslani-objednavky.md) — co se stane po odeslání veřejné přihlášky: **nezapisuje už nic**, všechny sekce jdou přes košíkové API a formulář je jen noscript fallback; kde zbyl legacy zápis (`Shop::prodat()`, ruční prodej v adminu) a co tím zmizelo (pořadí sekcí, transakce přes celou přihlášku); past degradované větve, kde se sekce vykreslí zapnutá a POST nikdo nezpracuje; gotchas pro testy (vlastní transakce, statická cache `Uzivatel`, konstanty termínů, XTemplate cache)
- [vzhled-adminu](vzhled-adminu.md) — klasický a moderní vzhled administrace nad stejným obsahem modulů i v online prezenci (Moje aktivity, bez rámu adminu); kde je volba uživatele (tabulka `uzivatele_vzhled_adminu`, odložení v session), proč `moderni.css` začíná `:where(.vzhled-moderni)` a kopíruje selektory `styl.css`, proč moderní kostra nese třídy klasické, gotchas XTemplate (bloky v obou šablonách, nerekurzivní `{FILE}`); tmavé barvy (`barvy_adminu.js`, proměnné `--m-*`), kompaktní panel na malém monitoru, schovaný panel jako úzký s ikonami (rozbalí se najetím myší), záznam a reporty používání vzhledů
- [admin-menu-moduly](admin-menu-moduly.md) — položky podmenu administrace se čtou z hlavičkového komentáře souboru modulu (`nazev:`, `ikona:`, `pravo:`, `submenu_*`); ikona z Font Awesome je povinná a hlídá ji test, emoji v `nazev:` a `submenu_nazev:` zakázané (název je i název stránky), emoji mimo menu zůstávají
- [nasazeni-rsync](nasazeni-rsync.md) — nasazení ostré a bety přes rsync (dřív FTP): ignore masky a jak se překládají (pořadí, `!`, kotvení), proč `--delete` potřebuje seznam chráněných cest (ručně nahrané soubory mimo git, uploady, cache), pojistka `--max-delete`, jak se při přechodu ověřilo porovnáním s FTP přes `file://` a suchým během proti serveru
- [udrzbovy-rezim](udrzbovy-rezim.md) — soubor `maintenance.html` zavře web, admin i `cache/public` odpovědí `503` se stránkou `maintenance.off.html`; proč má stránka jiný název než přepínač (Alias `/maintenance.html` na serveru), proč admin na vlastním jménu hledá přepínač o úroveň výš a má vlastní odkaz na stránku, proč blok údržby začíná každý `.htaccess` s `RewriteEngine`, co za údržby projde (`admin/deploy`), workflow pro ostrou a proč nahrává soubor, ne odkaz (`rrsync -munge`)
- [medailonky-vypravecu](medailonky-vypravecu.md) — medailonek vypravěče na `/<id>`, sdílená karta medailonku (Kontakty, widgety D&D/JaD a Pánů jeskyně, přehled) a přehled vypravěčů ročníku na `/vypraveci`: vypravěč = ročníková role i bez aktivit, nebo vedoucí netechnické aktivity i bez role, sekce = linie z menu s jeho viditelnou aktivitou, filtr `/vypraveci/<linie>`, odkaz z hlavičky programu (skrytý v adminu a v minulých ročnících) a ze stránky linie na její sekci
- [emailove-sablony](emailove-sablony.md) — předmět a text e-mailů v tabulce `emailova_sablona`, upravitelné v adminu stejným právem jako Editace stránek; zápis symbolů (`%malé%` proměnné, `%VELKÉ%` systémové hodnoty, `%%`), příznak „veřejné“ u nastavení a proč se neveřejná hodnota nedosadí (e-mail přesto odejde), zpětná kontrola na třech stránkách, jak přidat další e-mail, které e-maily jsou schválně mimo šablony a co se proti původnímu kódu změnilo; gotchas (proměnné modulu vs. `admin/index.php`, `„$x“` v PHP řetězci, sdílená instance nastavení v testech)
- [preview-vstup-pres-adminu](preview-vstup-pres-adminu.md) — sdílená URL preview: brána na serveru se serverově ptá adminu (`/api/preview-vstup`), jestli relace patří organizátorovi, a místo basic auth ho pustí a přihlásí; admin po přihlášení vrací na preview (`?returnTo=`, jen URL preview); kdo smí, proč endpoint smí volat jen brána (`X-Gate-Auth`), na čem to stojí (cookie `PS0gamecon0cz` pro `.gamecon.cz`) a co dělat, když přestane fungovat
- [filtry-aktivit-na-strankach-linii](filtry-aktivit-na-strankach-linii.md) — lišta filtrů (hledání, štítky, vypravěči, dny, volná místa), přepínač Karty / Seznam a ročníku („wayback“, `?rocnik=`) nad aktivitami na stránce linie (`/rpg`); PHP posílá data karet, Preact jen schovává; den = programový den jako v programu, „volné místo“ podle `isVolnoKPrihlaseni()`, filtr v adrese; gotchas: obnova scrollu po přihlášení, drahá transliterace dne, `&ensp;` jako anonymní flex položka, `<img src="">` u starých ročníků
- [obnova-hesla](obnova-hesla.md) — zapomenuté heslo přes odkaz platný hodinu; limity mailů na účet a žádostí na IP (u IPv6 na síť /64, generovaný sloupec `ip_rozsah`) v `obnova_hesla_log` (proč: Altcha přijme jednu výzvu opakovaně; proč se žádost zapisuje před počítáním: souběh), co se kde loguje a co schválně ne (token v logu mailů, hash hesla v logu změn), IP na preview za Caddy, účet bez hesla mail nedostane
- [anonymizovana-databaze](anonymizovana-databaze.md) — databáze, ze které startuje čerstvý checkout (`migrace/pomocne/gc_anonymizovana_databaze.sql`, nahrává ji `nastaveni/db-migrace.php`); odkud dump je (noční anonymizace na ostré, `bin/stahni-anonymizovanou-databazi.sh`), které tabulky se do něj schválně nedostanou (`PRILIS_CITLIVE_TABULKY`, repo je veřejné), kdy ho obnovit (po zredukování migrací, na přelomu roku — stáří N+2 shodí `ROCNIK`) a proč nejde „přehrát“ starý dump přes starou řadu migrací
- [indexace-vyhledavacu](indexace-vyhledavacu.md) — kanonická adresa (`rel="canonical"` i `og:url`) každé stránky veřejného webu z `URL_WEBU` + cesty bez dotazu a koncového lomítka; parametr měnící obsah (`rocnik`, `start`) musí modul přidat sám přes `setKanonickyParametr()`; `/robots.txt` generuje aplikace (ostrá povolí vše kromě stránek pro přihlášené, beta/preview/lokál `Disallow: /`); proč 401 u archivů a preview je záměr
- [testovani-zabezpeceneho-api](testovani-zabezpeceneho-api.md) — jak se testují endpointy za firewallem `^/symfony/api` (JWT, role); testuje se přes `ApiTestCase::createClient()` s transakčním obalem (`AbstractDatabaseKernelTestCase`); proč `createClient()` nesmí bootovat vlastní kernel, klíč kolekce `member` místo `hydra:member`, `product_tag.created_at` bez defaultu; invariant „entita nikdy není veřejně dostupná, jen DTO" a jak ho hlídá `ApiSecurityTest`; a co jde převzít z `3brs/PiercingApp` (matice rolí s deny-listem jako doplňkem, oddělené testy 401/403) versus co je blokované
- [nakup-produkt-a-varianta](nakup-produkt-a-varianta.md) — nákup ukazuje jen na variantu (koupenou velikost nebo noc), produkt je produkt varianty a lidem se ukazuje jako `product_id`; jak legacy SQL čte katalog z tabulek (typ, ročník, název) a proč záleží na jménech migrací
- [import-a-export-eshopu](import-a-export-eshopu.md) — report e-shopu je šablona importu a jde naimportovat beze změny zpátky; řádek = varianta, sloupce produktu se musí shodovat, stav i na variantě, archivace ze souboru, co dělá se zbylými legacy řádky velikostí a nocí
- [letosni-model-predmetu](letosni-model-predmetu.md) — letošní kostku/placku zdarma (a letošní placku v BFSR) jmenuje přesným kódem pravidlo slevy daného ročníku; nastavuje ho import e-shopu ze sloupce `je_letosni_hlavni`, chybějící kód hlásí stránka importu; proč ne heuristika ani příznak na produktu
- [terminy-prodeje-a-ruseni](terminy-prodeje-a-ruseni.md) — po termínu jde merch zrušit, ale jídlo ne: do cateringu jde počet porcí, kdežto účastnických triček se bere zásoba navíc a doprodává se na pultu; historicky byl merch zamčený obousměrně („objednat **a měnit**“ v názvu nastavení); termín se ladí na třech místech (datum kategorie, `nabizet_do` produktu, zásoba) a **které kategorie sdílejí datum, se mezi ročníky mění**
- [tricka-velikosti-a-varianty](tricka-velikosti-a-varianty.md) — velikost trička je v legacy samostatný produkt (477 z 486 má velikost v `kod_predmetu`, ne spolehlivě v názvu); jak seskupovat (base kód + ročník = 112 skupin), že cena i kapacita jsou per-varianta, a proč vedle sized produktů existuje bezvelikostní řádek pro interní prodej na festivalu; taky že reporty nechtějí barvu, ale hodnost (org/vypravěč/účastník) a berou ji z `kod_predmetu` — dřív se hádala z barvy v názvu, jenže orgovská trička byla 2009 a 2010 oranžová
- [phpstan-scope-legacy-tree](phpstan-scope-legacy-tree.md) — PHPStan analyzuje jen `symfony/`, takže konstruktory volané z `model/` (Cenik → Discount\*) a z testů hlásí jako mrtvé; dočasné potlačení `__construct` nálezů a úkol přidat `model/` do `paths:` než e-shop dojede do `main`; proč se nálezy objevily až po rebase (Rectorův autoloader je maskoval, a neexistující baseline cesta shodila celý běh před analýzou)
- [sql-mode-strict](sql-mode-strict.md) — cíl zapnout `STRICT_TRANS_TABLES`: dnes server běží nestriktně, takže zúžení sloupce i běžný zápis **tiše ořezávají data a hlásí úspěch** (ověřeno: `12345.67` → `99.99`); migrace si striktní režim už zapínají samy, aplikace zatím ne, protože padají testy
- [prodej-na-pultu-kfc](prodej-na-pultu-kfc.md) — pokladna na infopultu: kupující je sdílený účet `ANONYM`, ne `SYSTEM` ani NULL, anonymní prodej se musí připsat do `platby`, jinak na něm narůstá fiktivní dluh, a infopult s vybraným uživatelem prodává jemu na účet (bez připsání hotovosti a bez zaokrouhlení), a že `poznamka` „anonymní prodej" je jen popisek řádku ve finančním přehledu, ne příznak — plus proč se příznak zavede až pro obě zapisující cesty naráz
- [admin-objednavky-za-ucastnika](admin-objednavky-za-ucastnika.md) — poslední legacy zápisová cesta e-shopu: dvě admin obrazovky, kde obsluha objednává za účastníka; zápis ubytování už přes `/admin/customer-accommodation` hotový (a proč `ROLE_ADMIN` nemohl být bránou — ročníkové kódy rolí), co ještě chybí (čtecí endpoint, payload na POST, dva různé renderery, přeprodej kapacity, který legacy nabízelo jen šéfovi infopultu a nevynucovalo nikomu — nově hlídáno na serveru; JWT a identita pracovního uživatele; snídaně, které admin umí zrušit, ale ne vrátit)
- [chyby-v-nove-vrstve](chyby-v-nove-vrstve.md) — jak v `symfony/` odmítnout požadavek: typ z `symfony/src/Exception/` (400/403/409 z jedné mapy v `api_platform.yaml`), text z katalogu `errors.cs.yaml`; proč nechytat `\RuntimeException` ani nepřebalovat na `BadRequestHttpException` (chytí i poruchy a pošle je jako 400 bez logu) a proč porucha končí na produkci jako „Internal Server Error"
- [eshop-diferencni-overeni](eshop-diferencni-overeni.md) — než e-shop uvidí první člověk, porovnat ho bok po boku s legacy: dva worktree (`main` vs. eshop větev), obě DB ze stejného ostrého dumpu, scénáře přes celý životní cyklus ročníku od neotevřené registrace po zmrazená data po GC; porovnává se zápis do DB, chování GUI i adminní reporty — a proč to zelené testy nenahradí (schémata se liší o 23 migrací, takže `SELECT *` nestačí); vedle scénářů kontrola přes všechny uživatele (`bin-diff/porovnej-role.sh`) pro chyby, které se týkají jen části lidí
- [audit-zmen-nakupu](audit-zmen-nakupu.md) — každá změna řádku `shop_nakupy` (vznik, změna, smazání) jde do `shop_order_item_log` jedním Doctrine listenerem ve stejné transakci, s kým a odkud; log vidí jen zápisy přes entity manager, proto se do tabulky nesmí psát ručním SQL (hlídá test) a hromadné rušení i slučování uživatelů jdou přes Doctrine; bez cizích klíčů, selhání logu shodí nákup, Doctrine nuluje id dřív než `postRemove`; naměřená cena na nákup a co log nevidí (DQL, kaskáda při smazání uživatele)

## Povinné minimum dokumentu

1. **TL;DR** na prvním řádku souboru (1–2 věty: co to je, co dokument pokrývá).
2. **Vstupní body v kódu** přes `path/file.php:123` — ne kopírované bloky.
3. **Pravidla / invarianty** s označením zdroje, pokud není triviálně ověřitelný z kódu:
   - `(záměr)` — sděleno uživatelem, nemusí odpovídat implementaci
   - `(nejisté)` — neověřeno, odhad
   - bez značky = ověřeno čtením kódu
4. **Rozpor** mezi záměrem a implementací zaznamenej explicitně — je to cenná informace, ne chyba v dokumentu.

Zbylé sekce piš jen pokud dávají smysl, žádná rigidní šablona. Běžné sekce (volitelné): Rozsah · Datový model · Jak to funguje · Gotchas · Otevřené otázky.

## Pravidla efektivity

- **Kebab-case** názvy souborů, česky, výmluvné: `prihlasovani-na-aktivity.md`, `tymy-a-zamykani.md`.
- **Odkazuj, nekopíruj** — `path:line` je levnější než blok kódu pro čtenáře i pro udržování. Kód vkládej jen když je klíčový a nesnadno dohledatelný.
- **Krátce, skim-friendly** — bullet points, krátké odstavce, žádné úvody, opakování ani meta-komentáře.
- **Nezaduplikuj** `CLAUDE.md` ani existující `docs/` — raději odkaz (`viz docs/aktivita-instance.md`).
- **Neopisuj** kompletní DB schémata — jen sloupce a vztahy podstatné pro koncept.
- Když dokument přeroste ~300 řádků nebo pokrývá víc konceptů, **rozděl**.
- **Stabilita názvu** — na dokument lze odkazovat z kódu/PR, nepřejmenovávej bez důvodu.

## Údržba

- **Update > create**: pokud existuje blízký dokument, rozšiř / oprav ho místo vytváření nového.
- **Stale info přepiš**, nepřidávej rozporný text vedle.
- **Oprava od uživatele** → zaznamenej do dokumentu, ne jen do odpovědi v chatu.
- **Index v tomto souboru aktualizuj** při každém vytvoření / přejmenování / smazání dokumentu.

## Ochrana proti driftu

Kód se mění rychleji než docs — bez aktivní ochrany se dokumenty stanou zavádějícími a dělají víc škody než užitku.

### Při konzultaci dokumentu
- Čti dokument jako **hypotézu**, ne pravdu. Před rozhodnutím ověř klíčová tvrzení proti aktuálnímu kódu.
- Ověřuj primárně podle **jména symbolu** (třída, metoda, konstanta, tabulka) — čísla řádků driftují s každou editací, symboly přežijí.
- Pokud klíčové referenční soubory neexistují / byly přejmenovány → dokument je podezřelý jako celek, ne jen v tom jednom místě.

### Když najdeš drift (doc ≠ kód)
- **Neopravuj in-place jedinou větu.** Drift v jednom místě = silný signál, že dokument je nespolehlivý i jinde.
- Prověř celý dokument proti aktuálnímu kódu.
- Pokud je **přes polovinu obsahu zastaralé**, raději **smaž** a v případě potřeby napiš znovu — čistý restart je levnější a srozumitelnější než záplata na záplatě.
- Index v tomto souboru po smazání / přepsání aktualizuj.

Pokud drift-detekce začne být nákladná a opakovaná, **napiš příště menší a abstrahovanější dokumenty** — méně implementačních detailů = méně míst, kde může dokument ztratit přesnost.

### Prevence při psaní
- Preferuj `path.php::MetodaNebo::symbol` nebo `path.php + popis funkce` před `path.php:123` — symbolické odkazy jsou robustnější.
- `path:line` používej jen jako sekundární pomůcku, ne jako jediný ukazatel.
- Pro **rozpracovanou / nestabilní část kódu** drž dokument na **úrovni konceptu a business pravidel**, ne implementačních detailů. Implementace odteče, koncept zůstane.
- Pokud zaznamenáváš business pravidlo získané z promptu, napiš ho tak, aby mu rozuměl čtenář i po refactoru kódu (nepřipoutávej k aktuální struktuře tříd).
