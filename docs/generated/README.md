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
- [prihlaska-odeslani-objednavky](prihlaska-odeslani-objednavky.md) — co se stane po odeslání veřejné přihlášky; pořadí zpracování (ubytování musí předcházet jídlu kvůli snídaním v ceně hotelu), názvy POST polí a jejich nezávislost, počet kusů jako počet řádků; kde se hlídá vyprodání (`Shop::prodat()` vs. vlastní cesta ubytování) a rozpor, že pozastavený předmět zmizí z formuláře, ale ručně poskládaný POST ho koupí; gotchas pro testy (vlastní transakce, statická cache `Uzivatel`, konstanty termínů, XTemplate cache)
- [vzhled-webu](vzhled-webu.md) — moderní vzhled veřejného webu vedle klasického (ukázka na titulce a stránkách linií); volba v cookie přes `?vzhled=`, moderní šablona modulu v `sablony/moderni/`, ostatní stránky v moderní kostře s klasickým obsahem; varianty A–D s rozcestníkem `/vzhledy` a `?nahledVzhledu=`; bloky, které musí mít všechny šablony titulky, a absolutní URL obrázků v CSS proměnných
- [admin-menu-moduly](admin-menu-moduly.md) — položky podmenu administrace se čtou z hlavičkového komentáře souboru modulu (`nazev:`, `ikona:`, `pravo:`, `submenu_*`); ikona z Font Awesome je povinná a hlídá ji test, emoji v `nazev:` a `submenu_nazev:` zakázané (název je i název stránky), emoji mimo menu zůstávají
- [nasazeni-rsync](nasazeni-rsync.md) — nasazení ostré a bety přes rsync místo FTP; jedna sada ignore masek pro oba způsoby a jak se překládá (pořadí, `!`, kotvení), proč `--delete` potřebuje seznam chráněných cest (FTP mazalo jen to, co samo nahrálo; ručně nahraný `styl-rocnikovy`, uploady, cache), pojistka `--max-delete`, ověření porovnáním s FTP přes `file://` a suchým během proti serveru
- [emailove-sablony](emailove-sablony.md) — předmět a text e-mailů v tabulce `emailova_sablona`, upravitelné v adminu stejným právem jako Editace stránek; zápis symbolů (`%malé%` proměnné, `%VELKÉ%` systémové hodnoty, `%%`), příznak „veřejné“ u nastavení a proč se neveřejná hodnota nedosadí (e-mail přesto odejde), zpětná kontrola na třech stránkách, jak přidat další e-mail, které e-maily jsou schválně mimo šablony a co se proti původnímu kódu změnilo; gotchas (proměnné modulu vs. `admin/index.php`, `„$x“` v PHP řetězci, sdílená instance nastavení v testech)
- [preview-vstup-pres-adminu](preview-vstup-pres-adminu.md) — sdílená URL preview: brána na serveru se serverově ptá adminu (`/api/preview-vstup`), jestli relace patří organizátorovi, a místo basic auth ho pustí a přihlásí; admin po přihlášení vrací na preview (`?returnTo=`, jen URL preview); kdo smí, proč endpoint smí volat jen brána (`X-Gate-Auth`), na čem to stojí (cookie `PS0gamecon0cz` pro `.gamecon.cz`) a co dělat, když přestane fungovat
- [filtry-aktivit-na-strankach-linii](filtry-aktivit-na-strankach-linii.md) — lišta filtrů (hledání, štítky, vypravěči, dny, volná místa), přepínač Karty / Seznam a ročníku („wayback“, `?rocnik=`) nad aktivitami na stránce linie (`/rpg`); PHP posílá data karet, Preact jen schovává; den = programový den jako v programu, „volné místo“ podle `isVolnoKPrihlaseni()`, filtr v adrese; gotchas: obnova scrollu po přihlášení, drahá transliterace dne, `&ensp;` jako anonymní flex položka, `<img src="">` u starých ročníků
- [oznameni-a-motivy-webu](oznameni-a-motivy-webu.md) — lišta s oznámením na všech stránkách webu (oba vzhledy, zavření v cookie, naléhavé nejde zavřít), barvy moderního vzhledu pro ročník od grafika (návrh → náhled `?nahledMotivu=` jen s právem 105 → zveřejnění, jen odlišné barvy v JSON, kontrola kontrastu) a speciální motivy v kódu plánované z adminu (apríl); jak přidat motiv

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
