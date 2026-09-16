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

**Termín prodeje se na admin cestě záměrně nekontroluje** *(záměr)*. Odvozeno z toho, že obě
legacy obrazovky posílají `muzeEditovatUkoncenyProdej: true` a `ubytovaniBezZamku`, ne
z explicitního zadání. Je to nejvýraznější rozdíl proti účastnické cestě a nejspíš první věc,
kterou někdo „opraví" jako chybějící kontrolu.

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
| spolubydlící | edituje (1. argument `true`) | **jen zobrazuje** seznam lidí na pokoji, needituje |
| „nechce ubytování" | jen zobrazuje `ano`/`ne` | jen zobrazuje `ano`/`ne` |
| přes kapacitu | tlačítko jen pro `jeSefInfopultu()` | nenabízí |

Pozor na dvě místa, kde by převod **tiše přidal schopnost**, ne ji zachoval. Preact mřížka
nabízí editovatelné pole spolubydlícího i zaškrtávátko „nechci ubytování". Infopult ale
spolubydlícího jen vypisuje — a jde o něco jiného než u účastníka: je to **soupis lidí reálně na
pokoji** (jméno, id, telefon, odvozeno z `$pokoj`), ne volný text „s kým chci bydlet". Nasadit
tam mřížku beze změny by soupis pokoje proměnilo v editovatelné textové pole. U „nechce
ubytování" je to totéž v menším: obě obrazovky ho jen ukazují.

Rozdíl u spolubydlícího je past, ne kosmetika: `AccommodationWriter` bere `null` jako „smaž ho".
Naivní převod infopultu by tedy mazal spolubydlící, které si účastníci vyplnili sami. Zápisové
DTO to už řeší (`null` = nesahat, `''` = smazat), ale UI to musí respektovat taky.

### 4. Přeprodej kapacity

V legacy o přeprodeji fakticky rozhoduje **UI, ne zápis**: obě obrazovky volají
`zpracujUbytovani()` s druhým argumentem `false` (`_uzivatel_ovladac.php:40`,
`_infopult_ovladac.php:186`), takže `ShopUbytovani::ulozObjednaneUbytovaniUcastnika()` kapacitu
nekontroluje nikomu — tlačítko se přitom nabízí jen šéfovi infopultu. Ručně poskládaný POST tedy
přeplnil noc komukoliv; legacy si toho je vědomo (`// není zabezpečeno`).

**To je už opravené na straně serveru:** `AccommodationWriter::save()` bere `$smiPresKapacitu` a
`SetCustomerAccommodationProcessor` ho dává jen šéfovi infopultu. Invariant se tím obrátil —
rozhoduje server, UI je jen nápověda. Zbývá dotáhnout to do UI a srovnat s legacy obrazovkami,
které pořád jedou volně.

Souvisí s [issue #1114](https://github.com/gamecon-cz/gamecon/issues/1114): ubytování ignoruje
`reserved_for_organizers`. Je pravděpodobné *(nejisté)*, že přeprodej je náhražka právě za to —
kdyby šlo část postelí podržet pro interní potřebu, obcházení kapacity by nebylo potřeba.
Než se to rozhodne, převod musí přeprodej zachovat, jinak obsluha přijde o schopnost, kterou dnes má.

### 5. Jak se mřížka v adminu autentizuje a koho vlastně edituje

Dvě věci, bez kterých UI napojit nejde, a ani jedna není o mřížce samotné.

**JWT.** Preact bundle se autentizuje tokenem z `GAMECON_KONSTANTY.JWT`; API firewall je
`stateless`, cookie cesta do něj nevede vůbec. Recept v adminu existuje —
`admin/scripts/modules/penize/_kfcMrizkovyProdej.php` přes `jwtKonstantyJs()`. Dvě věci
k zapamatování: token se razí pro **obsluhu** (což je správně, zákazník jde v payloadu), a
`jwtKonstantyJs()` polyká `\Throwable` a vrací prázdný řetězec — selhání ražby je tedy neviditelné,
projeví se až jako 401 na každém volání.

**Identita účastníka.** Admin pracuje nad `$uPracovni`, což je **jiný klíč v session**
(`Uzivatel::UZIVATEL_PRACOVNI`), kdežto `LegacySessionService::getCurrentUser()` čte klíč výchozí,
tedy obsluhu. Nová vrstva se tak o pracovním uživateli nemá jak dozvědět: `customerId` musí
doputovat z PHP do stránky a odtud do API. A protože `renderComponent()` montuje komponenty
**bez props**, znamená to zásah do sdíleného montážního helperu (data-atribut nebo další pole
v `GAMECON_KONSTANTY`), ne lokální změnu mřížky.

#### Proč pracovní uživatel nepatří do JWT

Nabízí se dát `$uPracovni` rovnou do tokenu a mít klid. Na PiercingApp se to tak dělá — token
tam nese `store` (`JwtService::getAdditionalPayload()`), takže otázka „proč ne u nás" přijde
znovu. Rozdíl je v tom, co se do tokenu dává:

- **`homeStore` je vlastnost uživatele.** Je uložená na entitě (`AdminUser::$homeStore`), měnit
  ji smí jen admin nebo area manager (`OnlyAdminOrAreaManagerCanChangeIt`) a změna má vlastní
  validaci. Je to údaj **o člověku**, stejně jako jeho id nebo role.
- **`$uPracovni` je okamžitý výběr v UI.** Obsluha ho mění i několikrát za minutu psaním do
  omniboxu, nikde se neukládá a nikdo ho neschvaluje.

Dělicí čára tedy nevede mezi „identita a kontext", ale mezi **pomalu se měnícím faktem, který
někdo spravuje**, a **výběrem, který si držitel tokenu mění sám**. To první do tokenu patří,
druhé ne.

U nás to má konkrétní důsledek: token platí **hodinu a nejde odvolat** (`JwtService`, žádné
`jti` ani blocklist). Token vydaný nad účastníkem A by tak hodinu zůstal platný i poté, co
obsluha přepnula na B — buď by se musel razit znovu při každém přepnutí, nebo by zápisy končily
u špatného člověka. A stálý token se zapečeným účastníkem je navíc hodinu použitelná oprávnění
k úpravě jeho objednávky. S `customerId` v payloadu tohle nehrozí: server si práva obsluhy ověří
při každém requestu a zákazník je ten, kterého říká **tenhle** request.

(Kdyby token byl krátkodobý a razil se pro jednu akci, zúžení na jednoho účastníka by naopak
dávalo smysl — to je ale opak dnešního stavu.)

Co z PiercingApp stojí za převzetí, je tvar `getAdditionalPayload()` jako rozšiřovacího bodu;
gamecon má dnes ve `JwtService::extractUserData()` čtyři napevno zadrátovaná pole a žádný šev.

### 6. Snídaně: admin je umí zrušit, ale ne vrátit

`AccommodationWriter::save()` volá `breakfastCanceller->cancelCovered()` pro **všechny** volající,
admin nevyjímaje. Vrátit je ale umí jen účastnický endpoint (`restoreBreakfasts`); admin DTO nic
takového nemá.

Obsluha, která účastníkovi objedná hotelovou noc, mu tedy **tiše zruší zaplacené snídaně** a
vrátit je jde pouze z účastnického storefrontu. Čtecí payload přitom `restorableBreakfasts` nese,
takže admin mřížka postavená nad ním vykreslí tlačítko, které nemá co zavolat.

## Jídlo je pozadu za ubytováním

Ubytování má vlastní writer a set-based endpoint. Jídlo nemá **žádnou zápisovou cestu v nové
vrstvě**: `/cart/meals` je jen `GetCollection` a matice jídel nakupuje přes obecné `/cart/items`
+ `DELETE /cart/items/{id}`, kus po kuse, vždy pro přihlášeného uživatele.

Admin formulář přitom odesílá celou mřížku naráz. Převod jídla tedy nejdřív potřebuje rozhodnout,
jestli vznikne set-based endpoint jako u ubytování, nebo se bude diffovat na klientovi.

## Doporučené pořadí

1. Čtecí endpoint + payload na POST — bez toho UI nejde napojit. Payload musí nést i oprávnění
   obsluhy k přeprodeji.
2. Snídaně: dát admin cestě možnost je vrátit, ne jen zrušit.
3. Parametrizovat `UbytovaniMřížka` volitelným `customerId` — včetně zásahu do montážního
   helperu, protože ten dnes props nepředává vůbec.
4. Napojit obrazovku Uživatel (JWT jako v KFC), ověřit klikáním, teprve pak Infopult — má vlastní
   renderer a spolubydlícího jen zobrazuje.
5. Teprve potom jídlo, které začíná návrhem zápisové cesty.

Každý krok je samostatný PR. Kroky 3 a dál mění živé obrazovky obsluhy, takže je potřeba
ruční ověření, ne jen zelené testy.

**Proč přeprodej (4) před napojením UI (3):** mřížka musí umět tři stavy buňky — volno, plno a
odmítnuto, plno ale tahle obsluha smí. Ten třetí existuje teprve ve chvíli, kdy má názor server.
Kdyby se UI napojilo dřív, postavilo by se na `locked` počítaném podle pravidel účastníka a pak
by se muselo rozplétat. Z toho plyne i požadavek na krok 1: **čtecí payload musí nést oprávnění
obsluhy k přeprodeji**, protože `jeSefInfopultu()` si klient spočítat nemůže.

## Co se po převodu nedá smazat

`UbytovaniTabulka` je jen pro infopult, ta odejde s ním. `Shop::ubytovaniHtml()` ale volá i
účastnický storefront (`web/moduly/prihlaska/prihlaska.php`), takže ta zůstává.

Chování infopultové tabulky navíc hlídá test
`ShopUbytovaniRocnikAFiltraceTest::adminUbytovaniTabulkaPredavaDataProHoteloveSnidane()` — ověřuje,
že tabulka předává do JS data o hotelových snídaních. Než se tabulka smaže, potřebuje ten test
náhradu, ne odstranění; kryje totiž přesně tu snídaňovou logiku z bodu 6.

## Proč netřeba migrace dat

`AccommodationWriter::ulozUdajeOUbytovani()` zapisuje dvojmo — do Doctrine objednávky i do
`uzivatele_hodnoty.ubytovan_s` / `nechce_ubytovani`. Legacy čtení tedy zůstávají platná po celou
dobu převodu. Je to záměr, ne shoda okolností, a proto v plánu žádný backfill není.

## Co ověřit ručně

Sekce ubytování se v obou obrazovkách vykreslí jen pro účastníka s `gcPrihlasen()`. Tester tedy
potřebuje: obsluhu s právem 100 nebo 101, vybraného pracovního uživatele přes omnibox a účastníka
přihlášeného na letošní ročník. Bez toho sekce prostě není vidět a nález zní „mřížka chybí".
