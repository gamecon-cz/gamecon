# Hodnocení turnaje D&D

TL;DR: stránka adminu `hodnoceni-turnaje`, na které vypravěči kol turnaje D&D hodnotí skupiny (týmy) podle kritérií, která org turnaje naklikal v editoru na stránce (nebo do něj načetl z XLSX, ODS, CSV či textu); org turnaje vidí výsledky (body po kategoriích, celkem, pořadí) a stáhne je jako XLSX. Karta Trello 1513.

## Vstupní body v kódu

- `model/HodnoceniTurnaje/HodnoceniTurnaje.php`: všechna pravidla. Co kdo smí, rozhoduje `getDuvodZakazu()` (stránka podle něj zamkne formulář) a totéž pod zámkem kontroluje `ulozHodnoceni()`. Kritéria ukládá `ulozSablonu()` (podle id), soubor páruje `sparujPodleNazvu()`. Výsledky `getVysledky()`, exporty `getExportVysledku()` a `getExportHodnoceni()`.
- `model/HodnoceniTurnaje/FormularKriterii.php`: co je v editoru (odeslaný formulář, uložená šablona, prázdný), kontrola před uložením s chybami podle názvů.
- `model/HodnoceniTurnaje/ParserSablonyHodnoceni.php`: textový zápis (`rozeber()`) a tabulka (`rozeberTabulku()`), chyby s čísly řádků (`ChybaSablonyHodnoceni`). `SouborSablonyHodnoceni.php`: čtení XLSX/ODS (OpenSpout), CSV (oddělovač podle prvního řádku) a textu podle přípony původního názvu.
- `ZmenaKriteriiChcePotvrzeni`: uložení, které by smazalo hodnoty, s popisem a klíčem potvrzení.
- `admin/scripts/modules/hodnoceni-turnaje.php` + `.xtpl`, editor `admin/files/hodnoceni-turnaje.js`, styly `admin/files/design/hodnoceni-turnaje.css`, ikona klasického menu `hodnoceni-turnaje.avif`.
- Odkaz „Hodnocení skupin“ na kartě kola v Moje aktivity: `OnlinePrezenceHtml::getUrlHodnoceniTurnaje()`.
- `migrace/2026-11-18-112815_hodnoceni-turnaje.php`: tabulky, právo 117, role 32.
- Testy: `tests/HodnoceniTurnaje/`.

## Datový model

- `turnaj_hodnoceni_sablona`: jedna na (ročník, typ aktivity), `skala` (hodnoty oddělené mezerou, VARCHAR(255)), `zdroj` (kritéria v textovém zápisu, jak je org naposledy uložil, jen pro dohledání; stránka ho nečte), `nahrano`/`id_nahral` (kdy a kdo kritéria naposledy uložil), `uzavreno`.
- `turnaj_hodnoceni_kategorie` → `turnaj_hodnoceni_kriterium` (`poradi` podle souboru).
- `turnaj_hodnoceni`: unikátní (šablona, tým, kolo). `id_hodnotitele` = vypravěč, kterému hodnocení patří, `id_zadal` = kdo ho naposledy zadal nebo změnil. `id_akce` = aktivita, na které skupina kolo hrála, jen pro informaci: nullable, se smazanou aktivitou se vynuluje a hodnocení zůstane (patří ke skupině a kolu).
- `turnaj_hodnoceni_hodnota`: hodnota u kritéria.
- Kolo je `akce_seznam.turnaj_kolo` aktivity typu `TypAktivity::DND` v turnaji (`id_turnaje`), skupina je tým na ní (`akce_tym_akce`). Běh turnaje (1. a 2. běh jsou dva řádky `turnaje`) se pozná podle týmu: tým hraje jen v jednom.

## Pravidla

- (záměr) V kole D&D je jedna aktivita s několika vypravěči a stejným počtem týmů a kdo vedl kterou skupinu, se neukládá. Vypravěč proto skupinu vybírá sám a smí kteroukoli z aktivity kola, kterou organizuje (`akce_organizatori`).
- (záměr) Hodnocení je jedno na skupinu a kolo a patří vypravěči (`id_hodnotitele`), ten ho upravuje. Jiný vypravěč téhož kola ho nepřepíše a vidí jen, kdo hodnotil, ne hodnoty. Org turnaje vidí, přepíše i smaže cokoli; u přepsaného zůstane původní hodnotitel.
- (záměr) Nové hodnocení zadává org za vypravěče kola (výběr „Hodnotí za“ z organizátorů aktivity kola): hodnocení patří tomu vypravěči a ten ho pak upravuje jako své. Kdo ho skutečně zadal, je v `id_zadal`; stránka i export ukazují „Jana (zadal(a) org Petr)“.
- (záměr) Vypravěč hodnotí až od začátku kola (aktivita bez začátku nejde) a dokud org hodnocení neuzavře. Org turnaje kdykoli.
- (záměr) Každé kritérium musí mít hodnotu ze škály, jinak se neuloží nic.
- (záměr) Kritéria org naklikává v editoru na stránce (škála jako text „1 2 3 4 5“, kategorie a kritéria přidat, odebrat, šipkami přeřadit; šipka z kraje kategorie přesune kritérium do vedlejší, JS mu přejmenuje pole na klíč cílové kategorie). Soubor jen naplní editor, uloží se až tlačítkem. Původně (karta) šlo o textový soubor; editor a XLSX/CSV chtěl uživatel, text zůstal jako jeden z formátů souboru.
- (záměr) Škála: 2 až 20 celých čísel od -1000 do 1000 (víc se do výběru nevejde; spojené mezerami se vejdou do VARCHAR(255) a hodnoty do SMALLINT). Limity 50 kategorií a 200 kritérií drží formulář pod `max_input_vars` a pořadí pod posunem při přeřazení. Dvě kategorie stejného názvu a dvě stejná kritéria v kategorii (bez ohledu na velikost písmen) jsou chyba, stejné kritérium v různých kategoriích je v pořádku. Ukázka na kartě má v kategorii 3 dvakrát „Kritérium 2“, to neprojde.
- (záměr) Soubory: tabulka XLSX/ODS/CSV má v 1. sloupci kategorii, ve 2. kritérium; prázdná kategorie = stejná jako o řádek výš, stejná kategorie dál pokračuje, řádek „Škála | 1 2 3“ volitelný (jinak zůstane škála z editoru), hlavička „Kategorie | Kritérium“ se přeskočí, další sloupce taky, čte se jen první list a nejvýš 1000 vyplněných řádků (prázdné se nepočítají, sešit je mívá za koncem dat). CSV z českého Excelu (středník, Windows-1250) i odjinud (čárka, UTF-8 s BOM); oddělovač se odhaduje z prvních 10 řádků mimo uvozovky. Text: `Škála: …`, `# ` kategorie, `## ` kritérium; snese BOM, CRLF, Windows-1250, „Skala“. Stažení kritérií (nebo vzoru, když nejsou) jako XLSX/CSV jde načíst zpátky.
- (záměr) Uložení jde podle id: řádky editoru nesou id uložených kategorií a kritérií. Přejmenování, přeřazení i přesun kritéria do jiné kategorie hodnocení nechá a nic nepotvrzuje. Co by smazalo zadané hodnoty, stránka vypíše a chce potvrzení: odebrané kritérium (hodnocení zůstanou bez jeho hodnoty), nové kritérium nebo škála bez použité hodnoty (smažou se všechna hodnocení, protože hodnocení má hodnotu u každého kritéria). Klíč potvrzení nese jen to, co se smaže (všechna a kolik, nebo id odebraných), takže úprava názvů ho nezruší, odebrání dalšího ano. Id, které šablona nemá (jiný org mezitím uložil), je chyba a editor ukáže uložená kritéria.
- (záměr) Soubor se do editoru páruje podle názvů: kategorie podle názvu, kritérium nejdřív podle názvu ve stejnojmenné kategorii, pak v jiné kategorii, když je takové mezi zbylými uloženými jen jedno (přesun; „Kritérium 1“ ve více kategoriích zůstane nové). Spárované dostanou id, ostatní mají štítek „nové“. Přejmenované v souboru je tedy nové kritérium, přejmenovávat bez ztráty jde jen v editoru (stránka to říká).
- (záměr) Výsledky: součet za kategorii přes kritéria a kola, která skupina teď hraje (`akce_tym_akce`), celkem, pořadí. Pořadí podle pravidel orga turnaje (`getKlicPoradi()`), vše od největšího: 1. celkový součet, 2. nejvyšší součet kategorie, 3. nejnižší součet kategorie (vyšší nejslabší kategorie je lepší); kdo se shoduje ve všech třech, dělí místo („2.–3.“). Kategorie se porovnávají součtem za všechna počítaná kola. Se dvěma kategoriemi 3. pravidlo nic nerozhodne (při stejném celku a maximu je stejné i minimum). Jedno pořadí přes oba běhy se sloupcem běh. Skupina, které chybí hodnocení kola, je označená ⚠️, pořadí dostane taky.
- (záměr) Hodnocení kola, které skupina už nehraje (odhlášená z aktivity kola, smazaná aktivita), se nesmaže, ale do výsledků se nepočítá: v přehledu hodnocení má „⚠️ skupina už v kole není“ a nejde upravit, v exportu všech hodnocení poznámku. Když skupina kolo zase hraje (párování podle čísla kola), počítá se znovu.
- Kdo co smí: hlavička modulu `prava: 109, 117`. Vypravěči mají 109 (Moje aktivity), stránka jim nabídne jen kola, která vedou. Org turnaje má právo 117 `Pravo::ADMINISTRACE_HODNOCENI_TURNAJE` přes trvalou roli 32 „Organizátor turnaje D&D“ (`Role::ORGANIZATOR_TURNAJE_DND`, kategorie běžná, přidělí ji kdokoli s Právy). 115 a 31 si bere větev 1510 (půjčovna kostýmů).
- Účastníci nevidí nic.

## Gotchas

- 117 je v `PristupDoAdminu::PRAVA_JEN_STRANKY`: org turnaje nemusí být org GameConu, stránka API nepotřebuje.
- Vypravěč s jen 109 nevidí rám adminu (admin ho pošle na Moje aktivity), stránku najde odkazem z karty kola. Položku v menu uvidí každý s 109, kdo kolo D&D nevede, dostane hlášku.
- Všech pět tabulek je v `AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY`: hodnocení ukazují na `akce_tym`, který se do dumpu nedostane, a kritéria jsou interní text orga, dokud je turnaj nezveřejní.
- Tým bez názvu se ukazuje jako „Tým č. {id}“, ne kódem: kód pouští do nezamčeného týmu.
- Návody na stránce (`<details class="hodnoceni-navod">` v `.xtpl`: orgovi nahoře a u editoru, otevřené, dokud nejsou kritéria; vypravěči u výběru skupiny; formáty souborů pod načtením) opakují pravidla odsud. Změna pravidla = změna návodu. Do Nápovědy (`docs/napoveda/`) kapitola nepatří: org s jen právem 117 ji nevidí (`prava:` Nápovědy jsou 100–113) a vypravěči přicházejí z Moje aktivity.
- Stránku testy vykreslují přímo, bez webového testu (`HodnoceniTurnajeTest::vykresliStranku()`): modul čte `$u` a `$systemoveNastaveni` z rozsahu, ve kterém ho spouští `admin/index.php`. Úspěšné odeslání formulářů testy neprochází, `oznameni()` přesměruje a skončí; odeslání editoru, které skončí chybou nebo potvrzením, ano (`$post`). Načtení souboru stránkou ne (`is_uploaded_file()`), čtení souborů testuje `SouborSablonyHodnoceniTest`.
- Editor (JS) nové řádky skládá z `<template>` vzorů v `.xtpl` (`__KATEGORIE__`/`__KRITERIUM__` → klíč `j1`…; počítadlo začíná za nejvyšším `j` na stránce, protože po chybě server vrátí odeslané řádky i s jejich klíči a stejný klíč by PHP pole dvou řádků spojil); změna řádku editoru = změna na dvou místech šablony. Pořadí řádků dorazí na server pořadím polí ve formuláři (PHP ho v poli drží).

## Otevřené otázky (ví jen org turnaje)

- Váhy kategorií či kritérií. Zatím prostý součet, nejde nastavit. Kritéria pro rozhodnutí shody org dodal (nejlepší, pak nejslabší kategorie), viz Pravidla.
- Termín, do kdy hodnotit. Zatím jen ruční uzavření orgem.
- Pořadí společné pro oba běhy, nebo zvlášť. Zatím společné se sloupcem běh.
- Co padlo v „předchozí debatě“ zmíněné v komentáři karty.
