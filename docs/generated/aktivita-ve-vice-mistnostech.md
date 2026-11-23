TL;DR: aktivita může být ve více místnostech a jedna z nich je hlavní. Hlavní místnost je nepovinná: `akce_seznam.id_hlavni_lokace` drží jen výslovně zvolenou, NULL znamená „automaticky“ a každý čtenář ho vyhodnotí stejně, jako první z místností aktivity podle pořadí místností. Dokument popisuje pravidlo, kdo zapisuje, kdo čte a na co si dát pozor v editoru.

## Vstupní body

- `model/Aktivita/HlavniLokace.php` (`HlavniLokace::vyber()`, `HlavniLokace::getSqlId()`): jediné místo s pravidlem, jak se hlavní místnost vyhodnotí, v PHP i v SQL.
- `model/Aktivita/Lokace.php` (`Lokace::porovnejPodlePoradi()`): pořadí místností (`poradi`, při shodě `id_lokace`).
- `model/Aktivita/Aktivita.php`: `Aktivita::nastavLokacePodleIds()` (jediný zápis), `Aktivita::seznamLokaci()`, `Aktivita::hlavniLokace()`, `Aktivita::getIdZvoleneHlavniLokace()`, editor `Aktivita::parseUpravyTabulkaLokace()` a `Aktivita::editorZpracuj()`.
- `model/Aktivita/templates/editor-aktivity.xtpl` (`#vyberLokaci`, `#hlavniLokace` a skript pod ní).
- `admin/scripts/modules/aktivity/_Import/Activities/ActivityImporter.php` (`ActivityImporter::getChosenMainLocationId()`).

## Datový model

- `akce_lokace(id_akce, id_lokace)` je vazební tabulka (PK na obou sloupcích), `lokace` je číselník místností s `poradi` a `poznamka`.
- `akce_seznam.id_hlavni_lokace` je výslovně zvolená hlavní místnost, NULL = „automaticky“. Dřív to byl sloupec `akce_lokace.je_hlavni`, přesunula ho migrace `migrace/2026-03-07-191044-presun-je-hlavni-do-akce-seznam.php`.
- Cizí klíč na `lokace` sloupec v DB nemá: migrace `migrace/2026-03-07-191128-clean-up-foreign-key-on-update-cascade.sql` ho zrušila a znovu nepřidala, `ON DELETE SET NULL` je jen v mapování Doctrine (`Activity::$mainLocation`). Smazání místnosti proto smaže kaskádou jen řádek v `akce_lokace` a v `id_hlavni_lokace` může zůstat ID, které mezi místnostmi aktivity není. Vadí to málo: každý čtenář ověřuje, že je zvolená mezi místnostmi (`HlavniLokace`), takže platí „automaticky“, a další uložení aktivity tam dá NULL.
- Místnosti jsou vlastnost instance (¹ v editoru): `Aktivita::uloz()` je u rodiny instancí nastavuje jen upravované instanci.

## Pravidla

- Vyhodnocená hlavní místnost je výslovně zvolená, pokud je mezi místnostmi aktivity, jinak první z místností podle (`lokace.poradi`, `lokace.id_lokace`), bez místností žádná. Totéž dělá PHP (`HlavniLokace::vyber()`) i SQL (`HlavniLokace::getSqlId()`), jinde se pravidlo nepíše znovu.
- Zápis (`Aktivita::nastavLokacePodleIds()`) uloží `id_hlavni_lokace` jen tehdy, když je zvolená místnost mezi ukládanými místnostmi, jinak NULL. Zvolenou hlavní místnost nikdy nepřidá mezi místnosti (dřív to editor dělal: odebraná hlavní místnost se potichu vrátila, Trello 1471 a 1438).
- „Automaticky“ se vyhodnocuje při každém čtení, takže přidání místnosti s nižším pořadím hlavní místnost posune. Výslovně zvolená zůstává, dokud ji někdo z místností neodebere, pak je zase „automaticky“.
- Zápis je v jedné transakci (`dbBegin()` uvnitř testové transakce udělá savepoint) a před smazáním čehokoli ověří, že všechny místnosti existují.
- (záměr) Hlavní místnost je nepovinná a organizátoři ji nechtějí vybírat pokaždé: záleží na ní hlavně u aktivit se záložní místností (hraje se třeba venku), u larpu ve třech místnostech najednou je jedno, která je hlavní. Pořadí odpovídá číslování místností na stránce Místnosti (RPG 1, RPG 2, …).

## Kdo zapisuje

- Editor aktivity: `Aktivita::editorZpracuj()` → `Aktivita::uloz()` → `Aktivita::nastavLokacePodleIds()`; prázdná volba hlavní místnosti je NULL.
- Import aktivit: v souboru je hlavní místnost první v buňce (export ji tak píše, `Aktivita::seznamLokaci()` dává hlavní první). `ActivityImporter::getChosenMainLocationId()` ji uloží jako zvolenou jen tehdy, když se liší od té automatické, jinak NULL. Export a zpětný import tak automatickou nezmění na zvolenou. Zvolená, která je zároveň první podle pořadí, se importem změní na automatickou (soubor je nerozliší). Prázdná buňka převezme místnosti zdrojové aktivity.
- Nová instance: `Aktivita::instancuj()` zkopíruje místnosti a výslovně zvolenou hlavní místnost (NULL zůstane NULL).

## Kdo čte

- `Aktivita::seznamLokaci()`: hlavní první, zbytek v pořadí místností. Z toho export (`admin/scripts/modules/aktivity/_Export/ActivitiesExporter.php`), API (`web/moduly/api/aktivityUzivatel.php`, `mistnosti[0]` je v UI programu hlavní), popisy místností.
- `Aktivita::hlavniLokace()` / `Aktivita::idHlavniLokace()`: první z `seznamLokaci()`, u aktivity s místnostmi nikdy null. API `web/moduly/api/aktivitaAkce.php`.
- Reporty `admin/scripts/zvlastni/reporty/zazemi-a-program-casy-a-umisteni-aktivit.php` a `zazemi-a-program-zarizeni-mistnosti.php` vkládají `HlavniLokace::getSqlId()`.
- Stránka Přihlášení (`admin/scripts/modules/aktivity/prihlaseni.php`) skládá místnosti v SQL (`GROUP_CONCAT`) se stejným pořadím jako `Aktivita::seznamLokaci()`: `ORDER BY lokace.id_lokace <=> akce_seznam.id_hlavni_lokace DESC` dá zvolenou hlavní místnost první, jinak platí pořadí místností a první je ta automatická.
- Doctrine entita `symfony/src/Entity/Activity.php` (`Activity::$mainLocation`) mapuje surový sloupec, tedy jen zvolenou místnost. Dnes ji nikdo nečte.

## Editor

- Místnosti: `#vyberLokaci`, stejný widget jako vypravěči (jQuery MultiSelect 2.4.16 s `search: true`, hledá se psaním do „Hledej místnost“). Skupiny (`optgroup`) jsou podle `lokace.poznamka`, místnosti bez poznámky jsou mimo skupinu.
- Hlavní místnost: `#hlavniLokace` skládá jen JS v šabloně, při načtení stránky, při změně místností a při `pageshow` (stránka vrácená z mezipaměti prohlížeče). PHP pošle JSON všech místností v pořadí a v `data-zvolena` výslovně zvolenou hlavní místnost; pro NULL je vybraná volba „automaticky: <první vybraná místnost>“.

## Gotchas

- Proměnná XTemplate na konci řádku uvnitř JS: zkompilovaná šablona za ní má `?>` a PHP spolkne konec řádku, další řádek JS se přilepí. Proto `const lokaceVPoradi = {lokaceProHlavniLokaciJson};` se středníkem.
- Test, který šablonu editoru vykresluje sám, ji musí otevřít stejnou cestou jako `Aktivita` (`dirname(ReflectionClass::getFileName()) . '/templates/…'`): zkompilovaná `.xtpc` je jedna vedle šablony, ale jméno její třídy se počítá z cesty, jinak „Class … not found“.
- `chyba(…, true)` v `Aktivita::editorZpracuj()` přesměruje zpět a zahodí celou neuloženou úpravu. Kontroly, které mají úpravu zastavit, patří do `Aktivita::editorChyby()`, která běží AJAXem před odesláním formuláře.
- `DELETE … NOT IN ($1)` s prázdným polem je `NOT IN (NULL)` a nesmaže nic, proto má `Aktivita::nastavLokacePodleIds()` pro žádné místnosti vlastní větev.
