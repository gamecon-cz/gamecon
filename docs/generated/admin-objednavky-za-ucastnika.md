# Admin: objednávání ubytování a jídla za účastníka

TL;DR: dvě admin obrazovky, kde obsluha objednává **za účastníka**. **Ubytování je hotové** —
obě obrazovky jedou na mřížce přes `/admin/customer-accommodation`. Zbývá **jídlo**, které
v nové vrstvě nemá zápisovou cestu vůbec. Dokument drží, co bylo potřeba vyřešit a proč to
nebylo jen „namontovat existující Preact".

## Vstupní body v kódu

- `admin/scripts/modules/uzivatel.php`, `infopult/infopult.php` — obě obrazovky, obě už na mřížce
- `symfony/src/State/Admin/SetCustomerAccommodationProcessor.php`, `CustomerAccommodationProvider.php` — zápis a čtení za účastníka
- `symfony/src/State/Cart/AccommodationProvider.php::forCustomer()` — společné jádro mřížky
- `ui/src/pages/ubytovani/UbytovaniMřížka.tsx` — mřížka, bere volitelné `customerId`
- `ui/src/pages/index.tsx` — montáž, předává props z `data-customer-id`

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

## Co bylo potřeba vyřešit

Zbylo z toho jen 6 (snídaně). Ostatní je hotové a drží se tu proto, že to jsou pasti, na které
narazí každý, kdo bude dělat totéž pro jídlo.

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
| vykreslení | mřížka (`UbytovaniMřížka.tsx`) | mřížka (`UbytovaniMřížka.tsx`) — legacy renderery obou stran smazány |
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

V legacy o přeprodeji fakticky rozhodovalo **UI, ne zápis**: obě obrazovky volaly zápis
s vypnutou kontrolou kapacity, takže ji nehlídal nikomu — tlačítko se přitom nabízelo jen
šéfovi infopultu. (Ty legacy metody už neexistují, zápis dělá `AccommodationWriter`.) Ručně poskládaný POST tedy
přeplnil noc komukoliv; legacy si toho bylo vědomo (`// není zabezpečeno`).

**Vyřešeno:** `AccommodationWriter::save()` bere `$mayOverbook` a `SetCustomerAccommodationProcessor`
ho dává jen šéfovi infopultu. Invariant se obrátil — rozhoduje server, UI je jen nápověda.

Pozor, nesouvisející nález: nápověda `docs/napoveda/infopult.md` tvrdí, že „zrušit jiné ubytování
než neděli může pouze šéf Infa". Takové pravidlo **v kódu nikdy nebylo** — ani v legacy zápisové
cestě, ani nikde jinde; všechna nedělní pravidla se týkají nabízení, ne rušení. Buď je ta věta
k smazání, nebo je to nenaimplementovaný záměr *(nejisté)* — chce to rozhodnutí vlastníka
produktu, ne odhad.

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

## Stav a co zbývá

Ubytování je hotové, v tomhle pořadí:

1. ✅ čtecí endpoint + payload na POST
2. ✅ parametrizace `UbytovaniMřížka` volitelným `customerId` (včetně montážního helperu, který
   dosud props nepředával vůbec)
3. ✅ obrazovka Uživatel
4. ✅ obrazovka Infopult — tam se vyměnila **jen editovatelná tabulka**; číslo pokoje, soupis
   spolubydlících s telefony a souhrn ubytování zůstaly, protože je mřížka nemá

Otevřené:

- **Snídaně.** Admin cesta je umí zrušit, ale ne vrátit — `restoreBreakfasts` má jen účastnické
  DTO. Mřížka proto obsluze to tlačítko vůbec nenabízí, aby nevypadalo funkčně a nedělalo nic.
- **Jídlo.** `/cart/meals` je jen čtecí a matice nakupuje přes obecné `/cart/items` kus po kuse,
  kdežto admin formulář posílá celou mřížku naráz. Převod tedy začíná návrhem zápisové cesty,
  ne napojením.

Kroky, které měnily živé obrazovky obsluhy, si vyžádaly ruční ověření — zelené testy na to
nestačí, viz [[eshop-diferencni-overeni]].

**Proč přeprodej musel předcházet UI:** mřížka musí umět tři stavy buňky — volno, plno a
odmítnuto, plno ale tahle obsluha smí. Ten třetí existuje teprve ve chvíli, kdy má názor server.
Kdyby se UI napojilo dřív, postavilo by se na `locked` počítaném podle pravidel účastníka a pak
by se muselo rozplétat.

## Co se po převodu nedá smazat

`UbytovaniTabulka` **smazána** — nikdo ji nevykresloval. `Shop::ubytovaniHtml()` už
neexistuje vůbec, odešla s legacy storefrontem; účastnická přihláška dnes vykresluje
mřížku (`prihlaska.php` → `prihlaskaPreactSekceHtml('preact-ubytovani', …)`).

Její snídaňový test nebyl zrušen, ale přepsán na novou cestu:
`AccommodationWriterTest::testHotelNightCancelsTheNextMorningNotItsOwn()` tvrdí totéž
pravidlo (noc kryje ráno následujícího dne) přes `BreakfastCanceller`, ne přes HTML atribut
smazané šablony.

Legacy shopové JS (`shop-jidlo.js`, `shop-ubytovani.js`, `shop-svrsky.js`,
`shop-vstupne.js`) je **smazané** — obsluhovalo formuláře, které zmizely se storefrontem.

**`.less` soubory ve stejném adresáři ale smazat nejde**, i když stylují mrtvé třídy:
`web/index.php:96` je bere globem `perfectcache('soubory/blackarrow/*/*.less')`, takže se
do stylopisu kompiluje **každý** soubor v adresáři, aniž by ho kdokoli jmenoval. Grep proto
u stylů nic nedokazuje. Totéž platí pro obrázky vedle nich (`checkbox.svg`, `kostka.png`,
`radio*.svg`) — odkazuje je zkompilovaný `.less`, ne smazané JS.

Rozdíl proti JS je v tom, jak se načítá: skripty se registrují ručně přes
`Modul::pridejJsSoubor()` a v celém repu to dělá jediné místo
(`web/moduly/aktivity.php` pro `zachovej-scroll.js`). Co se nezaregistruje, se nenačte.

## Proč netřeba migrace dat

`AccommodationWriter::saveAccommodationDetails()` zapisuje dvojmo — do Doctrine objednávky i do
`uzivatele_hodnoty.ubytovan_s` / `nechce_ubytovani`. Legacy čtení tedy zůstávají platná po celou
dobu převodu. Je to záměr, ne shoda okolností, a proto v plánu žádný backfill není.

## Co ověřit ručně

Sekce ubytování se v obou obrazovkách vykreslí jen pro účastníka s `gcPrihlasen()`. Tester tedy
potřebuje: obsluhu s právem 100 nebo 101, vybraného pracovního uživatele přes omnibox a účastníka
přihlášeného na letošní ročník. Bez toho sekce prostě není vidět a nález zní „mřížka chybí".
