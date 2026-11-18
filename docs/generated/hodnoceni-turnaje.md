# Hodnocení turnaje D&D

TL;DR: stránka adminu `hodnoceni-turnaje`, na které vypravěči kol turnaje D&D hodnotí skupiny (týmy) podle kritérií, která nahraje org turnaje textovým souborem; org turnaje vidí výsledky (body po kategoriích, celkem, pořadí) a stáhne je jako XLSX. Karta Trello 1513.

## Vstupní body v kódu

- `model/HodnoceniTurnaje/HodnoceniTurnaje.php`: všechna pravidla. Co kdo smí, rozhoduje `getDuvodZakazu()` (stránka podle něj zamkne formulář) a totéž pod zámkem kontroluje `ulozHodnoceni()`. Výsledky `getVysledky()`, exporty `getExportVysledku()` a `getExportHodnoceni()`.
- `model/HodnoceniTurnaje/ParserSablonyHodnoceni.php`: čtení souboru s kritérii, chyby s čísly řádků (`ChybaSablonyHodnoceni`).
- `admin/scripts/modules/hodnoceni-turnaje.php` + `.xtpl`, styly `admin/files/design/hodnoceni-turnaje.css`, ikona klasického menu `hodnoceni-turnaje.avif`.
- Odkaz „Hodnocení skupin“ na kartě kola v Moje aktivity: `OnlinePrezenceHtml::getUrlHodnoceniTurnaje()`.
- `migrace/2026-11-18-112815_hodnoceni-turnaje.php`: tabulky, právo 117, role 32.
- Testy: `tests/HodnoceniTurnaje/`.

## Datový model

- `turnaj_hodnoceni_sablona`: jedna na (ročník, typ aktivity), `skala` (hodnoty oddělené mezerou), `zdroj` (nahraný text, jde upravit a nahrát znovu), `uzavreno`.
- `turnaj_hodnoceni_kategorie` → `turnaj_hodnoceni_kriterium` (`poradi` podle souboru).
- `turnaj_hodnoceni`: unikátní (šablona, tým, kolo), `id_akce` = aktivita, na které skupina kolo hrála, `id_hodnotitele`.
- `turnaj_hodnoceni_hodnota`: hodnota u kritéria.
- Kolo je `akce_seznam.turnaj_kolo` aktivity typu `TypAktivity::DND` v turnaji (`id_turnaje`), skupina je tým na ní (`akce_tym_akce`). Běh turnaje (1. a 2. běh jsou dva řádky `turnaje`) se pozná podle týmu: tým hraje jen v jednom.

## Pravidla

- (záměr) V kole D&D je jedna aktivita s několika vypravěči a stejným počtem týmů a kdo vedl kterou skupinu, se neukládá. Vypravěč proto skupinu vybírá sám a smí kteroukoli z aktivity kola, kterou organizuje (`akce_organizatori`).
- (záměr) Hodnocení je jedno na skupinu a kolo. Kdo ho zadal, ho upravuje; jiný vypravěč téhož kola ho nepřepíše a uvidí, kdo hodnotil. Org turnaje přepíše i smaže cokoli a hodnotitel u přepsaného zůstane původní.
- (záměr) Vypravěč hodnotí až od začátku kola (aktivita bez začátku nejde) a dokud org hodnocení neuzavře. Org turnaje kdykoli.
- (záměr) Každé kritérium musí mít hodnotu ze škály, jinak se neuloží nic.
- (záměr) Soubor: `Škála: 1 2 3 4 5`, kategorie `# `, kritérium `## `. Snese BOM, CRLF, Windows-1250, prázdné řádky, „Skala“ bez diakritiky. Duplicitní kritérium v kategorii a duplicitní kategorie (bez ohledu na velikost písmen) jsou chyba s číslem řádku; stejné kritérium v různých kategoriích je v pořádku. Ukázka na kartě má v kategorii 3 dvakrát „Kritérium 2“, to parser odmítne.
- (záměr) Nové nahrání se stejným tvarem (škála a počty kritérií v kategoriích) jen přejmenuje, hodnocení zůstanou. Jiný tvar smaže všechna hodnocení, a když nějaká jsou, jen po zaškrtnutí.
- (záměr) Výsledky: součet za kategorii přes všechna kola a kritéria, celkem, pořadí podle celku, stejný součet dělí místo („2.–3.“). Jedno pořadí přes oba běhy se sloupcem běh. Skupina, které chybí hodnocení kola, je označená ⚠️, pořadí dostane taky.
- Kdo co smí: hlavička modulu `prava: 109, 117`. Vypravěči mají 109 (Moje aktivity), stránka jim nabídne jen kola, která vedou. Org turnaje má právo 117 `Pravo::ADMINISTRACE_HODNOCENI_TURNAJE` přes trvalou roli 32 „Organizátor turnaje D&D“ (`Role::ORGANIZATOR_TURNAJE_DND`, kategorie běžná, přidělí ji kdokoli s Právy). 115 a 31 si bere větev 1510 (půjčovna kostýmů).
- Účastníci nevidí nic.

## Gotchas

- 117 je v `PristupDoAdminu::PRAVA_JEN_STRANKY`: org turnaje nemusí být org GameConu, stránka API nepotřebuje.
- Vypravěč s jen 109 nevidí rám adminu (admin ho pošle na Moje aktivity), stránku najde odkazem z karty kola. Položku v menu uvidí každý s 109, kdo kolo D&D nevede, dostane hlášku.
- Všech pět tabulek je v `AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY`: hodnocení ukazují na `akce_tym`, který se do dumpu nedostane, a kritéria jsou interní text orga, dokud je turnaj nezveřejní.
- Tým bez názvu se ukazuje jako „Tým č. {id}“, ne kódem: kód pouští do nezamčeného týmu.
- Stránku testy vykreslují přímo, bez webového testu (`HodnoceniTurnajeTest::vykresliStranku()`): modul čte `$u` a `$systemoveNastaveni` z rozsahu, ve kterém ho spouští `admin/index.php`. Odeslání formulářů testy neprochází, `oznameni()` přesměruje a skončí.

## Otevřené otázky (ví jen org turnaje)

- Váhy kategorií či kritérií a kritéria pro rozhodnutí shody („kritéria dodám“). Zatím prostý součet a sdílené místo, nic z toho nejde nastavit.
- Termín, do kdy hodnotit. Zatím jen ruční uzavření orgem.
- Pořadí společné pro oba běhy, nebo zvlášť. Zatím společné se sloupcem běh.
- Co padlo v „předchozí debatě“ zmíněné v komentáři karty.
