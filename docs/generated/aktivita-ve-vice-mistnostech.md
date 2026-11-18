TL;DR: aktivita může být ve více místnostech a jedna z nich je hlavní. Hlavní místnost je nepovinná, vždy je jednou z místností aktivity a když ji nikdo nevybere (nebo ji z místností odebere), vybere se sama podle pořadí místností. Dokument popisuje, kde se to zapisuje, kdo to čte a na co si dát pozor v editoru.

## Datový model

- `akce_lokace(id_akce, id_lokace)` je vazební tabulka (PK na obou sloupcích), `lokace` je číselník místností s `poradi` a `poznamka`.
- Hlavní místnost je `akce_seznam.id_hlavni_lokace` (FK na `lokace`, `ON DELETE SET NULL`). Dřív to byl sloupec `akce_lokace.je_hlavni`, přesunula ho migrace `2026-03-07-191044-presun-je-hlavni-do-akce-seznam.php`.
- Místnosti jsou vlastnost instance (¹ v editoru): `Aktivita::uloz()` je u rodiny instancí nastavuje jen upravované instanci.

## Pravidla

- Hlavní místnost je vždy jednou z místností aktivity, bez místností je `NULL`. Hlídá to jediné místo, kudy se místnosti zapisují: `Aktivita::nastavLokacePodleIds()` → `Aktivita::vyberHlavniLokaci()`.
- Nezvolená hlavní místnost, nebo zvolená, která mezi místnostmi není: hlavní je první z místností podle `lokace.poradi`, při shodě podle `id_lokace`. Ukládá se do `id_hlavni_lokace` napevno (ne `NULL`), aby ji všichni čtenáři viděli stejně.
- Zvolená hlavní místnost se mezi místnosti nikdy nepřidá. Dřív to editor dělal, a když organizátor hlavní místnost z místností odebral, uložení ji tam potichu vrátilo (Trello 1471 a 1438).
- (záměr) Hlavní místnost je nepovinná a organizátoři ji nechtějí vybírat pokaždé: záleží na ní hlavně u aktivit se záložní místností (hraje se třeba venku), u larpu ve třech místnostech najednou je jedno, která je hlavní. Výchozí volba „první podle pořadí“ odpovídá tomu, že místnosti jsou v Místnostech číslované podle pořadí (RPG 1, RPG 2, …).

## Kdo zapisuje

- Editor aktivity: `Aktivita::editorZpracuj()` předá místnosti a zvolenou hlavní (může být prázdná) do `Aktivita::uloz()`.
- Import aktivit: `ActivityImporter::saveActivity()` bere jako hlavní první místnost v buňce. Export ji tam také píše první (`ActivitiesExporter`, `Aktivita::seznamLokaci()` řadí hlavní první), takže export a zpětný import hlavní místnost zachovají. Prázdná buňka převezme místnosti zdrojové aktivity.
- Nová instance: `Aktivita::instancuj()` zkopíruje místnosti i hlavní místnost.

## Kdo čte

- `Aktivita::seznamLokaci()`: hlavní první, zbytek podle pořadí. Z toho program (`mistnosti[0]` v UI je hlavní), API a export.
- `Aktivita::idHlavniLokace()` a reporty `zazemi-a-program-*` mají pro `id_hlavni_lokace IS NULL` náhradu „místnost s nejnižším `id_lokace`“. Rozpor: zápis vybírá podle pořadí, čtení starých řádků s `NULL` podle ID. Nové zápisy `NULL` s místnostmi nenechávají, takže se to týká jen starých dat.
- `Aktivita::hlavniLokace()` hází `LogicException`, když uložená hlavní místnost mezi místnostmi není (jen poškozená data).

## Editor

- Místnosti: `#vyberLokaci` v `model/Aktivita/templates/editor-aktivity.xtpl`, stejný widget jako vypravěči (jQuery MultiSelect 2.4.16 s `search: true`, hledá se psaním do „Hledej místnost“). Skupiny (`optgroup`) jsou podle `lokace.poznamka`, místnosti bez poznámky jsou mimo skupinu.
- Hlavní místnost: `#hlavniLokace` nabízí „automaticky: <první podle pořadí>“ a jen vybrané místnosti. JS pod ní nabídku přepočítá při každé změně místností a odebranou hlavní místnost vrátí na „automaticky“. Server (`Aktivita::parseUpravyTabulkaHlavniLokace()`) vykreslí totéž.

## Gotchas

- Proměnná XTemplate na konci řádku uvnitř JS: zkompilovaná šablona za ní má `?>` a PHP spolkne konec řádku, další řádek JS se přilepí. Proto `const lokaceVPoradi = {lokaceProHlavniLokaciJson};` se středníkem.
- Test, který šablonu editoru vykresluje sám, ji musí otevřít stejnou cestou jako `Aktivita` (`dirname(ReflectionClass::getFileName()) . '/templates/…'`): zkompilovaná `.xtpc` je jedna vedle šablony, ale jméno její třídy se počítá z cesty, jinak „Class … not found“.
- `chyba(…, true)` v `Aktivita::editorZpracuj()` přesměruje zpět a zahodí celou neuloženou úpravu. Kontroly, které mají úpravu zastavit, patří do `Aktivita::editorChyby()`, která běží AJAXem před odesláním formuláře.
