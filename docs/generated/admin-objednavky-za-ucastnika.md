# Admin: objednávání ubytování a jídla za účastníka

TL;DR: poslední legacy zápisová cesta e-shopu jsou dvě admin obrazovky, kde obsluha objednává
**za účastníka**. Zápisová půlka ubytování je hotová (`POST /admin/customer-accommodation`),
zbytek ne. Dokument drží, co ještě chybí a proč to není jen „namontovat existující Preact".

## Vstupní body v kódu

- `admin/scripts/modules/_uzivatel_ovladac.php:39` a `admin/scripts/modules/uzivatel.php:76` — obrazovka Uživatel
- `admin/scripts/modules/infopult/_infopult_ovladac.php:185` a `infopult.php:215` — obrazovka Infopult
- `admin/scripts/modules/_submoduly/ubytovani_tabulka.php` — vlastní renderer infopultu (105 řádků)
- `symfony/src/State/Admin/SetCustomerAccommodationProcessor.php` — hotová zápisová cesta
- `symfony/src/State/Cart/AccommodationProvider.php:58,68` — čtecí cesta, zatím vázaná na session
- `ui/src/pages/ubytovani/UbytovaniMřížka.tsx` — Preact mřížka (205 řádků), psaná pro účastníka
- `ui/src/pages/index.tsx:35` — montáž podle `id` elementu, bez props

## Co už hotové je

`POST /admin/customer-accommodation` bere zákazníka v payloadu a práva čte **pro něj**, ne pro
obsluhu. Oprávnění obsluhy se kontroluje na `ADMINISTRACE_UBYTOVANI` / `ADMINISTRACE_INFOPULT`,
tedy na stejná práva, jaká si obě obrazovky deklarují v hlavičce modulu.

`ROLE_ADMIN` pro to použít nejde: `User::getRoles()` ho přiděluje podle **přesné shody** kódu
role, jenže reálné kódy jsou ročníkové (`gc2026_infopult`). Fakticky ho tak dostanou jen role
`ADMIN` (16) a `CFO` (20) — a ani jedna nemá právo 100 ani 101.

## Co chybí, a proč to není triviální

### 1. Čtecí endpoint

Mřížka musí zobrazit noci **zákazníka**. `GET /cart/accommodation` ale servíruje přihlášeného
uživatele, takže admin mřížka by ukazovala noci *obsluhy* a zapisovala noci *zákazníka*.

Dobrá zpráva: `AccommodationProvider` sahá na session jen na dvou místech (`:58` `$user`,
`:68` `$legacyUzivatel`) a dál si obojí předává parametrem. Stačí je vytáhnout výš — ne přepsat
provider.

### 2. POST nic nevrací

Admin zápis je `output: false`, kdežto účastnický vrací stejný payload jako GET. Mřížka na tom
staví: po uložení se překreslí z odpovědi a **po chybě reloaduje**, aby neukazovala výběr, který
server nemá (`UbytovaniMřížka.tsx:63-70`). Bez payloadu tahle logika nefunguje.

### 3. Dva různé renderery

| | Uživatel | Infopult |
|---|---|---|
| vykreslení | `Shop::ubytovaniHtml()` | `UbytovaniTabulka` (vlastní, 105 ř.) |
| spolubydlící | ukládá | **neukládá** (`vcetneSpolubydliciho: false`) |
| přes kapacitu | tlačítko jen pro `jeSefInfopultu()` | nenabízí |

Rozdíl u spolubydlícího je past, ne kosmetika: `AccommodationWriter` bere `null` jako „smaž ho".
Naivní převod infopultu by tedy mazal spolubydlící, které si účastníci vyplnili sami. Zápisové
DTO to už řeší (`null` = nesahat, `''` = smazat), ale UI to musí respektovat taky.

### 4. Přeprodej kapacity

Zápis kapacitu nehlídá u žádné z obrazovek (`hlidatKapacituUbytovani: false`), ale **nabídne** ji
jen šéfovi infopultu (`shop-ubytovani.xtpl:77`). Fakticky tedy o přeprodeji rozhoduje UI, ne
zápis. Nová vrstva nic takového nemá a `AccommodationWriter` plnou noc odmítne vždycky.

Souvisí s [issue #1114](https://github.com/gamecon-cz/gamecon/issues/1114): ubytování ignoruje
`reserved_for_organizers`. Je pravděpodobné *(nejisté)*, že přeprodej je náhražka právě za to —
kdyby šlo část postelí podržet pro interní potřebu, obcházení kapacity by nebylo potřeba.
Než se to rozhodne, převod musí přeprodej zachovat, jinak obsluha přijde o schopnost, kterou dnes má.

## Jídlo je pozadu za ubytováním

Ubytování má vlastní writer a set-based endpoint. Jídlo nemá **žádnou zápisovou cestu v nové
vrstvě**: `/cart/meals` je jen `GetCollection` a matice jídel nakupuje přes obecné `/cart/items`
+ `DELETE /cart/items/{id}`, kus po kuse, vždy pro přihlášeného uživatele.

Admin formulář přitom odesílá celou mřížku naráz. Převod jídla tedy nejdřív potřebuje rozhodnout,
jestli vznikne set-based endpoint jako u ubytování, nebo se bude diffovat na klientovi.

## Doporučené pořadí

1. Čtecí endpoint + payload na POST (1 a 2 výše) — bez toho UI nejde napojit.
2. Parametrizovat `UbytovaniMřížka` volitelným `customerId`, stejně jako se dělilo
   `MerchMřížka`/`SvrškyMřížka`.
3. Napojit obrazovku Uživatel, ověřit klikáním, teprve pak Infopult (má vlastní renderer
   a jiné chování u spolubydlícího).
4. Rozhodnout přeprodej — zachovat jako dnes, nebo vyřešit přes `reserved_for_organizers`.
5. Teprve potom jídlo, které začíná návrhem zápisové cesty.

Každý krok je samostatný PR. Kroky 3 a dál mění živé obrazovky obsluhy, takže je potřeba
ruční ověření, ne jen zelené testy.
