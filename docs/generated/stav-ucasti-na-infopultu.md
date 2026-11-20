**TL;DR:** Účast na GameConu má tři kroky, každý je ročníková role: přihlášen → přítomen (prošel infopultem, dostal materiály) → odjel. Dokument pokrývá, kdo který krok na Infopultu odklikne a vrátí a co stav „přítomen“ zamyká.

## Vstupní body v kódu

- Tlačítka a jejich povolení: `admin/scripts/modules/infopult/infopult.php` (hledej `prijelADatMaterialyDisabled`, `isOpravnenVracetStavNaGc`), šablona `infopult.xtpl` (bloky `zrusitOdjezd`, `zrusitPrijezd`, `zrusitPrihlaseni`, `odhlasitZGc`).
- Obsluha formulářů: `admin/scripts/modules/infopult/_infopult_ovladac.php` (`gcPrihlas`, `prijelADatMaterialy`, `gcOdjed`, `gcZrusOdjezd`, `gcZrusPrijezd`, `gcZrusPrihlaseni`, `gcOdhlas`).
- Model: `Uzivatel::gcPrihlas()`, `gcOdjed()`, `gcZrusOdjezd()`, `gcZrusPrijezd()`, `gcZrusPrihlaseni()`, `getPrekazkyZruseniPrihlaseni()`, `odhlasZGc()`, dotazy `gcPrihlasen()`, `gcPritomen()`, `gcOdjel()` v `model/uzivatel.php`.
- Role: `Role::PRIHLASEN_NA_LETOSNI_GC`, `PRITOMEN_NA_LETOSNIM_GC`, `ODJEL_Z_LETOSNIHO_GC` (`model/Role/Role.php`), id odvozené z ročníku.

## Kroky a kdo je dělá

| krok | tam | zpět |
| --- | --- | --- |
| přihlášen | „Přihlásit“, kdokoli s Infopultem (nebo účastník sám přihláškou) | „Zrušit přihlášení“: jen šéf infopultu, jen dokud není přítomen a letos nemá ani nevede aktivity, nemá objednávky ani platby; bez mailu. Jinak „Odhlásit z GC“: jen správce financí (`Role::CFO`), jen dokud není přítomen; zruší aktivity a objednávky, pošle maily |
| přítomen | „Přijel a Dát materiály“; infopulťák, který není šéf, potvrzuje dialog s chybějícími věcmi | „Zrušit příjezd“: jen šéf infopultu, jen když neodjel |
| odjel | „Odjel z GC“ s potvrzovacím dialogem | „Zrušit odjezd“: jen šéf infopultu |

- Vrátit jde jen poslední krok; šéf vidí vždy jedno tlačítko podle stavu (`gcOdjel()` má přednost před `gcPritomen()`).
- Vracení je jen odebrání role (`odeberRoli()`), zapíše se do `uzivatele_role_log` s tím, kdo vracel, a přepočte se historie rolí. Vydané materiály, stravenky ani klíč se tím nevrací.
- Oprávnění hlídá model (`gcZrusOdjezd()` / `gcZrusPrijezd()` / `gcZrusPrihlaseni()` vyhodí `Chyba`), stránka jen schová tlačítko. Kdo smí, říká jediné místo: `Uzivatel::isOpravnenVracetStavNaGc()`.
- Mimo Infopult jdou role sundat i v adminu přes Práva (tak se to řešilo dřív), bez kontroly pořadí (nejisté, podle komentáře na kartě).

## Pravidla (záměr)

- Vracet smí jen šéf infopultu (záměr, Trello 961: obava, aby někdo omylem nevrátil, co vrátit neměl; v praxi je vracení potřeba zřídka).
- Zrušit přihlášení smí šéf jen tehdy, když je to čisté vrácení omylem kliknutého „Přihlásit“: letos nemá žádný řádek v `akce_prihlaseni` ani `akce_prihlaseni_spec` (přihlášené, sledované, pozdě zrušené, nedorazil), v `akce_organizatori` (vypravěč přihlášený z webu není omyl infopultu), v `shop_nakupy` ani v `platby` (`getPrekazkyZruseniPrihlaseni()`). Cokoli z toho = odhlášení z GC se vším, co ruší, a to zůstává správci financí (záměr, schváleno ke kartě 961). Platby kvůli tomu, že odhlášení plátce hlásí info@, tiché zrušení by to obešlo. Šéf tlačítko vidí zašedlé i s tím, co na účastníkovi visí.

## Co zamyká stav „přítomen“

- Odhlášení z GC (`odhlasZGc()` vyhodí `Chyba`) i ruční hromadné odhlašování neplatičů (`admin/scripts/modules/finance/hromadne-odhlasovani.php` ho nahlásí jako potíž).
- Veřejnou přihlášku za běhu GC (`web/moduly/prihlaska/prihlaska.php`, blok `prihlaskaUzavrena.proselInfopultem`).
- Reporty a statistiky počítají přítomné podle role (`BfgrReport`, `BfsrReport`, `Statistiky`, `infopult-ucastnici-bez-infopultu`).

Po „Zrušit příjezd“ se tohle všechno zase otevře, proto jde vrátit jen nejdřív odjezd a pak příjezd.

## Gotchas

- `gcOdjel()` pro letošek vrací `false`, když účastník není přítomen, i kdyby roli odjel měl (třeba po ručním sundání role v Právech). Pořadí v `gcZrusPrijezd()` na tom stojí.
- Potvrzovací dialog u „Odjel z GC“ dlouho neměl `return`, takže Zrušit v dialogu nic nezastavilo (opraveno spolu s vracením kroků).
