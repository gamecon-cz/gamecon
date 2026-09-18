# Nálezy z diferenčního ověření

Jeden řádek na rozdíl. **Rozdíl není automaticky chyba** — ale nezařazený rozdíl je dluh.

Klasifikace: **záměrné** (víme a chceme) · **chyba** (na opravu) · **nezjištěno** (dořešit)

| # | scénář | co se liší | A (legacy) | B (nový) | klasifikace |
|---|---|---|---|---|---|
| N1 | setup | anonymní nákupy přeřazené z uživatele | `id_uzivatele = 1` (SYSTEM), 24 řádků | `id_uzivatele = 0` (ANONYM) | **záměrné** — migrace `anonymous-buyer`; `porovnej.sh` to srovnává |
| N2 | setup | uživatel ANONYM (id 0) | není | je | **záměrné** — tatáž migrace |
| N3 | 1 | den před otevřením registrace | „Přihlašování bude spuštěno 17. 9. 2026 v 20:26." | totéž, znak po znaku | **shoda** — účastník se dozví, kdy se vrátit |
| N4 | 2 | mřížka ubytování: legacy 0 buněk, nový 32 | nevykreslí | vykreslí | **nezjištěno** — `ubytovani_den` je prázdný už v původním dumpu, migrace za to nemůže; viz rozbor |
| N6 | 6 | nákup jídla po termínu vrací **HTTP 500 se stack trace** | klik vůbec nenabídne | klik projde, server odpoví 500 | **chyba** — logika je správná, doručení ne; viz [issue #1118](https://github.com/gamecon-cz/gamecon/issues/1118) |
| N7 | 6 | po termínu zůstávají v e-shopu aktivní ovládací prvky | jen „nechci ubytování" | 2 checkboxy ubytování + 6 jídla | **nezjištěno** — souvisí s N6, ale je to otázka na GUI |
| N8 | 5 | **brigádník platí za jídlo plnou cenu** | Oběd/Večeře 110, Snídaně 150 | 140 / 180 — jako člověk bez role | **chyba** — chybí sleva 30 Kč (`SLEVA_ORGU_NA_JIDLO_CASTKA`) |
| N9 | 5 | předmět „Nicknack" (80 Kč) nová větev vůbec nenabízí | nabízí u všech rolí | chybí u všech rolí | **nezjištěno** — ověřit, zda je to záměr |
| N5 | 2 | `findByTag()` neaplikuje `nabizet_do` | — | ubytování i jídlo se nabízí i po termínu produktu | **nezjištěno** — u ubytování záměr, u jídla k potvrzení |

## Mimo e-shop, ale zjištěno cestou

| # | co | detail | co s tím |
|---|---|---|---|
| M1 | `TRETI_VLNA_KDY` je `2024-07-01 20:24` | ostatní vlny mají 2026; třetí vlna je o dva ročníky pozadu | **nezjištěno** — netýká se e-shopu, ale vypadá jako zapomenuté nastavení. Ověřit s pořadateli, případně karta na Trello |

## Poznámky k metodice

- **Zelené testy nic nenahrazují.** Suite ověřuje náš záměr, ne shodu s legacy. Během
  přepisu prošlo několik rozdílů, které odhalilo až tohle porovnání.
- **Výchozí stav je ověřený jako shodný** (68 732 řádků nákupů, 665 řádků ubytování),
  takže každý rozdíl od téhle chvíle způsobil scénář, ne sestava.
- **Reset trvá ~9 s** (`bin-diff/reset.sh`), takže mezi scénáři není důvod ho vynechávat.

## N4 — uzavřeno: ani chyba, ani migrace. Chyba v mém závěru.

Postupně jsem tvrdil tři věci, z toho dvě špatně. Pro pořádek, protože ten omyl je poučný:

1. ~~„Nová verze opravila storefront."~~ Ne — velká část rozdílu byl posun času, který
   neuměl `nabizet_do` u produktů.
2. ~~„Migrace vyprázdnila `ubytovani_den` a rozbila tím legacy."~~ **Taky ne.**
3. Skutečnost: `ubytovani_den` je u ubytování prázdný **už v původním dumpu ostré**,
   před jakoukoli migrací. Ověřeno načtením dumpu do dočasné DB:

   | typ | 2024 | 2025 | 2026 |
   |---|---|---|---|
   | ubytování (3) | 0 z 35 | 0 z 36 | **0 z 21** |
   | jídlo (4) | 11 z 11 | 12 z 12 | 12 z 12 |

Migrace tedy nic nerozbila. Moderní ubytování ten sloupec nepoužívá — legacy si den
odvozuje z názvu položky, kdežto nová vrstva má `product_variant.accommodation_day`
(367 z 367 naplněno), protože varianty vznikly právě kvůli tomu.

**Proč legacy v scénáři 2 nevykreslila mřížku, zůstává neuzavřené** — vysvětlení přes
`ubytovani_den` neplatí. Na dořešení, ale už bez hypotézy o migraci.

**Poučení do metodiky:** dokud rozdíl není ověřený proti **původnímu dumpu**, není to
nález o kódu. Dvakrát jsem tady vydal za nález něco, co bylo v datech od začátku.

## N5 — `findByTag()` nefiltruje `nabizet_do`

Vedlejší zjištění při rozboru N4, **platné nezávisle na něm**:

| dotaz | filtruje `nabizet_do`? | koho se týká |
|---|---|---|
| `findPublic()` | ano (`availableUntil IS NULL OR > :now`) | merch |
| `findByTag()` | **ne**, jen `archivedAt` | ubytování **a jídlo** |

U ubytování je to záměr — legacy noc zamyká jen stavem POZASTAVENY (viz
`ProductRepository:208` + `ProductRepositoryNabizetDoTest`). Že stejná výjimka platí i pro
**jídlo**, vyplývá ze sdíleného dotazu, ne z rozhodnutí.

**Zařazení: nezjištěno** — otázka na produkt: má se jídlo po vlastním `nabizet_do` pořád
nabízet (jako ubytování), nebo ne (jako merch)?

## N6 — reprodukce (scénář 6, po termínu prodeje)

Účastník, „dnes" 2026-07-20 (prodej jídla skončil 2026-07-19), klikne v matici na jídlo:

```
POST /symfony/api/cart/items   {"variantId":1113}
→ 500

{"@type":"Error","title":"An error occurred",
 "detail":"Produkt \"Snídaně čtvrtek\" není dostupný.",
 "status":500, "trace":[{"file":"...\/CartService.php","line":89, ...}]}
```

Uživatel v GUI uvidí `Chyba: Produkt "Snídaně čtvrtek" není dostupný.` — text je správný,
**obchodní pravidlo drží** a nic se nezapíše.

Problém je doručení:

- **500 místo 4xx.** Odmítnutí prodeje po termínu je očekávaný stav, ne chyba serveru.
  Takhle to navíc zapadne do monitoringu jako incident.
- **`trace` v odpovědi** vyzrazuje cesty v souborovém systému.
- `CartService:171` a `:262` házejí `\RuntimeException`, což API Platform mapuje na 500.
  Chce to typovanou doménovou výjimku a mapování na 409/422.

Legacy sem účastníka vůbec nepustí (checkbox není klikatelný), takže tahle cesta je nová.

**Toto je přesně [issue #1118](https://github.com/gamecon-cz/gamecon/issues/1118)** —
teď s reprodukcí a konkrétním dopadem na účastníka, ne jen jako poznámka z review.

## N8 — uzavřeno: sleva funguje, měření bylo špatné

Scénář 5, „dnes" 2026-06-01, uživatel s rolí `GC2026_BRIGADNIK`:

| | legacy | nový | plná cena |
|---|---|---|---|
| Snídaně | **150** | 180 | 180 |
| Oběd | **110** | 140 | 140 |
| Večeře | **110** | 140 | 140 |

Rozdíl je přesně 30 Kč = `SLEVA_ORGU_NA_JIDLO_CASTKA`. Na nové větvi platí brigádník
tolik co člověk úplně bez role, takže se sleva neuplatní vůbec.

Sleva jako taková v nové vrstvě existuje — `DiscountSetting::OrganizerMealDiscount` míří
na týž klíč nastavení a `Cenik` i nová vrstva ho sdílejí. Chyba tedy nebude v částce, ale
v tom, **komu se přizná**: brigádník má práva `1004, 1008, 1016, 1025, 1028, 1037`.
Ověřit, na které z nich legacy slevu váže a proč ji nová vrstva nepřizná.

**Dopad:** účastník s touhle rolí zaplatí za jídlo o 30 Kč víc, než má. Tiše — nikde se
nic nerozbije.

**Ověřit i ostatní role:** vypravěč, partner a organizátor měly ceny shodné, ale brigádník
ukazuje, že přiznávání slev není samozřejmé. Stojí za samostatnou kontrolu každé role,
která má na jídlo slevu mít.

### Neplatí — ověřeno nad reálnou databází

Sleva se přiznává správně v obou vrstvách. Brigádník (`GC2026_BRIGADNIK`, práva
`1004,1008,1016,1025,1028,1037`) platí za snídani **150 místo 180**, tedy přesně o
`SLEVA_ORGU_NA_JIDLO_CASTKA` míň:

```
--- GC2026_BRIGADNIK (uzivatel 1134) ---
  1004 jidlo se slevou: ANO   1005 jidlo zdarma: ne
  Snídaně čtvrtek   plna 180.00 ->  150.00  (sleva 30.00)
--- GC2026_VYPRAVEC (uzivatel 61) ---
  1004 jidlo se slevou: ne
  Snídaně čtvrtek   plna 180.00 ->  180.00  (sleva  0.00)
```

**Rozejít se ani nemůžou:** `Cenik::cena()` i `DiscountCalculator::calculateDiscount()`
počítají týmž `DiscountCalculation` nad pravidly z `discount_rule` a nad týmiž právy
uživatele. Legacy nemá vlastní cestu, kterou by slevu přiznalo navíc.

Storefront pravidla filtruje (`neomezenaPravidla()` nechá jen ta bez `maxQuantity`), ale
`jidlo_se_slevou` žádný `maxQuantity` nemá, takže filtrem projde. `DiscountCalculatorBrigadnikTest`
hlídá **data** — že pravidlo `maxQuantity` nemá; kdyby ho dostalo, zmizí sleva ze
storefrontu, zatímco legacy ji dál přiznává. Samotný filtr je privátní a test na něj
nesahá.

Původní měření tedy porovnávalo něco jiného — pravděpodobně jiného uživatele nebo jiný
ročník. **Poučení: než z rozdílu udělám nález, ověřit, že obě strany měří touž roli** —
`GC2026_PRIHLASEN`, `PRITOMEN` a `ZKONTROLOVANE_UDAJE` má každý účastník, takže samy o
sobě o roli nic neříkají.

Vypravěč právo 1004 **nemá** a slevu tedy dostat nemá — to není chyba.

## N9 — „Nicknack" chybí

Předmět za 80 Kč, který legacy nabízí u **všech** pěti testovaných rolí a nová větev
u žádné. Může jít o záměrné stažení z prodeje (tag, archivace), ale stejně tak o propadlý
produkt. Ověřit dřív, než to zjistí účastník.

## Metodická chyba, na kterou jsem narazil (a co z ní plyne)

Ve scénáři 4 jsem porovnával stav, kde legacy mělo u uživatele 6586 čtyři noci „2L koleji"
za 500 Kč a nová větev úplně jiné pokoje za 900–1000 Kč. Vypadalo to jako zásadní nález.

**Nebyl.** Byly to nasčítané zápisy z předchozích pokusů — testy v předchozích scénářích
klikaly do e-shopu a já mezi nimi nesahnul po resetu. Po `bin-diff/reset.sh` jsou obě
strany znak po znaku shodné.

**Pravidlo, které z toho plyne:** `bin-diff/reset.sh` se pouští **před každým scénářem, i
před opakováním téhož scénáře**. Devět sekund je levnější než hodina honby za přeludem.
Každý scénář, který něco zapisuje, kontaminuje ten další.

**N8 jsem po tomhle zjištění přeověřil nad čistým resetem — platí.** Brigádník má na
legacy 110/110/150, na nové 140/140/180.

---

## Scénář 5 znovu po rebase na main (2026-09-17)

Obě větve přerovnány na týž základ (`origin/main` včetně opravy úklidu testovacích DB),
obě DB resetovány ze společného snapshotu. Ceny porovnány **programově**, ne klikáním:
skript pustí na obou větvích tentýž `Cenik::cena()` pro 6 rolí × 40 předmětů (po osmi
z každého typu — tričko, ubytování, vstupné, jídlo, merch) a výsledky se diffnou.

**Výsledek: všech 240 cen se shoduje.** Žádný rozdíl mezi legacy a novou větví.

### N8 — VYŘEŠENO (a původní diagnóza byla špatně)

Původní nález tvrdil, že brigádník platí na nové větvi za jídlo o 30 Kč víc. Po rebase
a resetu **rozdíl neexistuje** — obě větve dávají shodné ceny.

Ověřeno přímo přes `Cenik::cena()` na produktu 1858 („Oběd čtvrtek", 140 Kč):

```
prava brigadnika: 1004,1008,1016,1025,1028,1037
cena pro brigadnika: 110   sleva: 30
```

Sleva se tedy přiznává správně, na právo 1004, v očekávané výši. Nález nejspíš padl
s [PR #1128](https://github.com/gamecon-cz/gamecon/pull/1128), který storefront přepojil
z prázdné tabulky `product_discount` na `discount_rule` — to je přesně ta třída chyby,
kdy se sleva „ztratí".

### Dvě pasti na měření, do kterých jsem spadl

Obě vypadaly jako nález a nebyly:

1. **`LIMIT` bez pokrytí typů.** První verze skriptu brala 40 předmětů seřazených podle
   názvu — a všech 40 vyšly kostky. „240 cen sedí" tedy neříkalo nic o jídle ani
   ubytování. Vybírat po osmi **z každého typu** zvlášť.
2. **Nedeterministické řazení.** `ORDER BY nazev` u osmi ročníkových variant „Oběd
   čtvrtek" (85 až 140 Kč) vrací na každé větvi jinou osmičku, takže diff hlásil
   `85 vs 140`. Řadit podle `id_predmetu`, ne podle názvu, a do klíče porovnání dávat
   **id**, ne jméno.

Obecně: **než rozdíl v ceně prohlásím za nález, ověřit ho přímo přes `Cenik::cena()` nad
konkrétním `id_predmetu`.** Souhrnná tabulka je dobrá na screening, ne na závěr.

### Rozdíly, které jsou záměrné

Velikosti triček se staly variantami, takže se přejmenovaly předměty: legacy „Dámské
tílko červené **S**" → nově „Dámské tílko červené". Ceny shodné, jen jiný název.

---

## N10 — 500 na `/admin/infopult`: legacy chyba, NE regrese přepisu

Prohlížeč (Playwright) našel to, co programové ověření najít nemohlo.

```
Deprecated: Implicit conversion from float 2936.58 to int loses precision
model/Accounting/TransactionSplit.php:15
```

`TransactionSplit::__construct(int $amount, …)` dostane float. Obrazovka infopultu spadne
na 500 u účastníka s nenulovým zůstatkem — tedy u běžného případu.

**Ověřeno na obou větvích za stejných podmínek** (čas 2026-07-24, tentýž účastník 6586,
tentýž operátor): obě hlásí `500` a **tentýž řetězec včetně částky 2936.58**. Soubor je
navíc na obou větvích znak po znaku shodný.

**Závěr: dlouhodobá vada legacy, kterou přepis nezavlekl.** Opravit se má, ale nepatří to
do PR e-shopu.

## N11 — `KontextZobrazeni` na `/prihlaska`: artefakt testovací sestavy

```
LogicException: Nelze rozpoznat kontext zobrazení podle URL: http://localhost:18020/prihlaska
model/Shop/KontextZobrazeni.php:30
```

`KontextZobrazeni::dej()` porovnává URL requestu proti `URL_WEBU`, a ta je
`http://localhost/web` — **bez portu**. Diferenční sestava běží na `:18020` a `:18040`,
takže se nikdy netrefí.

**Není to vada produktu.** Na ostré je URL bez portu a porovnání sedí. Stránka se i tak
vykreslí správně, jen to plní log chyb.

Za pozornost stojí jen to, že se kontext hledá porovnáváním řetězců URL — takže jakákoli
odchylka (port, jiná doména, proxy) ho rozbije. Na řešení mimo tenhle přepis.

## Jak se k admin obrazovce vůbec dostat (stálo to pět pokusů)

- **Pracovní uživatel se nenastavuje přes `?id_uzivatele=` ani `?id=`.** Omnibox posílá
  **POST** `id` (`admin/scripts/prihlaseni.php:89`) a ten chce CSRF token — přímý POST
  skončí na `403`.
- **Funguje `?pracovni_uzivatel=<id>`** (tamtéž, řádek 93). Tím se nastaví do session.
- **Mřížky se vykreslí jen u účastníka přihlášeného na GC** (`$uPracovni->gcPrihlasen()`,
  `infopult.php:209`). S náhodným uživatelem se neukáže nic a vypadá to jako rozbitá
  komponenta. Vhodný testovací účastník: **6586** (role `-2601` = GC2026_PRIHLASEN).

## N12 — pult nemůže po termínu objednat ubytování, i když zápis to dovolí

Nalezeno až v prohlížeči; kódové ověření to minout muselo, protože testovalo zapisovač.

**Stav v prohlížeči** (čas 2026-07-24, tedy po termínu ubytování i jídla, operátor
s právem 100, účastník 5246):

| mřížka | klikatelných políček |
|---|---|
| jídlo | **12** |
| ubytování | **0** |

Ubytovací mřížka hlásí „Možnost objednání ubytování už skončila." a všechny noci jsou
`disabled`.

**Proč:** `AccommodationProvider::forCustomer()` nastaví
`$cell->locked = ! $koupeno && ($prodejUkoncen || …)` a admin provider
(`CustomerAccommodationProvider:56`) na ni jen deleguje — nerozlišuje, jestli se ptá
účastník, nebo pult.

**Přitom zápis to dovolí.** `SetCustomerAccommodationProcessor` termín záměrně nekontroluje
(ověřeno programově ve scénáři 6.6: admin po termínu zapsal noc 0 → 1). Takže API by
objednávku přijalo, ale GUI ji nedovolí odeslat.

**Dopad:** přesně to, kvůli čemu admin obrazovky existují — doobjednat ubytování na pultu
po termínu — přes GUI nejde. Jídlo ano, ubytování ne.

**Pozor na rozdíl oproti 6.7:** u jídla je matice po termínu klikatelná, u ubytování ne. Ty
dvě cesty se tedy chovají nekonzistentně, i když obě mají mít stejný smysl.

**Je to REGRESE přepisu, ne zděděné chování.** Ověřeno na legacy větvi za stejných podmínek
(tentýž čas, operátor i účastník):

| větev | checkboxů celkem | z toho klikatelných | hláška o konci prodeje |
|---|---|---|---|
| legacy | 15 | **11** | ne |
| nová | 33 | **0** | „Možnost objednání ubytování už skončila." |

Legacy pult tedy po termínu ubytování objednat umí a nová vrstva to zakázala.

**Opraveno.** `AccommodationProvider::forCustomer()` dostalo `zPultu`, které admin provider
posílá `true` — termín pak neplatí, stejně jako ho už nekontroluje zápis. Ověřeno
v prohlížeči za týchž podmínek:

| | klikatelných nocí po termínu |
|---|---|
| před opravou | 0 |
| po opravě | **19** |
| účastník sám na sebe | **0** (zůstává zamčeno, správně) |

## N13 — jídlo nekontroluje `JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE`, účastník ho koupí i po termínu

**Je to regrese přepisu**, ne zděděné chování.

### Co se děje

Nová vrstva ten termín nečte vůbec. `prodejJidlaUkoncen()` má v celém repu **dva**
volající a ani jeden není storefront:

- `model/SystemoveNastaveni/SystemoveNastaveni.php:839` — definice
- `model/Shop/Shop.php:809` — hromadné rušení objednávek (*co se po uzávěrce neruší*)

V `symfony/src/` **není ani jeden výskyt**.

### Proč to na první pohled vypadá v pořádku

Účastník po termínu jídlo nekoupí — jenže ho zastaví **`nabizet_do` produktu**, ne ten
termín. Ty dvě hodnoty si dnes neodpovídají:

| | hodnota |
|---|---|
| `JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE` | 2026-07-19 |
| `nabizet_do` u „Snídaně čtvrtek" | **2026-09-13** |

Mezi 19. 7. a 13. 9. je tedy jídlo objednatelné, přestože termín dávno vypršel.

Ověřeno izolovaně — `nabizet_do` posunuto na 2099, aby blokoval jen termín z nastavení:

```
prodej jídla ukončen podle nastavení: ANO
účastník kupuje jídlo: KOUPIL → termín z nastavení se NEKONTROLUJE
```

### Legacy to kontrolovalo

`model/Shop/Shop.php:406` a `:428` (legacy větev):

```php
$prodejJidlaUkoncen = !$muzeEditovatUkoncenyProdej && $this->systemoveNastaveni->prodejJidlaUkoncen();
…
if ($prodejJidlaUkoncen || …) { $t->parse('jidlo.druh.den.locked'); }
```

Všimni si `$muzeEditovatUkoncenyProdej` — legacy mělo **přesně tu asymetrii účastník/pult**,
kterou jsme právě doplnili u ubytování (N12). U jídla chybí obojí: zámek i výjimka pro pult.

Kód zmizel s odstraněním legacy storefrontu (`1274 Remove the legacy storefront rendering`).

### Co s tím

Doplnit do nové vrstvy zámek po termínu, ale **jinak než u ubytování**:

- `MealProductsProvider` / matice jídel má po termínu zamykat pro účastníka,
- `SetCustomerMealsProcessor` (pult) termín kontrolovat nemá — to už platí.

### ⚠️ U jídla se zamyká i to, co má účastník KOUPENÉ

To je zásadní rozdíl oproti ubytování. `JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE` znamená
„objednat **a měnit**": po termínu už GameCon nahlásil počty jídel do jídelny, takže
účastník nesmí ani **zrušit**, co má — jinak by se platilo za jídlo, které nikdo nesní.

**Nesmí se tedy použít výjimka `! $koupeno`, kterou má ubytování.** Legacy to tak dělalo:

```php
// model/Shop/Shop.php:428 (legacy) — žádná výjimka na kusu_uzivatele
if ($prodejJidlaUkoncen || ($jidloVDen['stav'] == self::STAV_POZASTAVENY && …)) {
    $t->parse('jidlo.druh.den.locked');
}
```

Potvrzuje to i zápisová strana, `Shop::zrusZrusitelneLetosniObjednavky()` s komentářem:

> *Po uzávěrce jídla / ubytování už je GameCon objednal u dodavatele (catering,
> ubytovatel) a nedostane za ně zpět peníze, takže tyto položky se NEruší a zůstávají
> zákazníkovi naúčtované.*

Legacy navíc po termínu vypisovalo `jidlo.objednavkyZmrazeny` — hlášku, že objednávky jsou
zmrazené (`Shop.php:451`). Nová vrstva nic takového nemá.

### Opraveno

Zámek je na **dvou vrstvách**, protože ta v UI sama o sobě nic nedrží:

| Vrstva | Co dělá |
|---|---|
| `CartService::prodejSekceUkoncen()` | jídlo přidáno mezi sekce s termínem — po něm `addItem()` vyhodí |
| `CartService::removeItem()` | nově odmítá i **odebrání** po termínu sekce |
| `MealProductsProvider` → `locked` | nápověda pro matici, aby uživatel nemlátil do checkboxu |
| `JídloMatice.tsx` | `disabled` bez výjimky `!checked` — zamyká i koupené |

Pult zůstává odemčený: chodí mimo košík, přes `MealWriter`, a v katalogu se pozná podle
`?customerId` **plus** práva (`CustomerDeskRights::jeObsluhaPultu()`), takže si účastník
matici dopsáním parametru do URL neodemkne.

**Že je zámek v UI jen kosmetický, se ukázalo až při ověřování:** matice byla zamčená, ale
přímý požadavek na `POST cart/items` jídlo koupil dál. Kontrola v `CartService` je to, co
díru doopravdy zavírá — proto na ni míří testy (`CartServiceJidloTest`), ne na `locked`.

**Vedlejší efekt, záměrný:** `removeItem()` hlídá termín *celé sekce*, takže po svém termínu
nejde z košíku odebrat ani tričko/mikina/merch. To odpovídá legacy komentáři výše — po
uzávěrce je zboží objednané u dodavatele a nevrací se.

**Výjimka `vraceniZruseneho`:** `BreakfastCanceller::restore()` vrací snídani, kterou systém
sám zrušil (krytou hotelem). To není nový prodej, takže termín neplatí — jinak by účastník
po termínu přišel o položku objednanou včas.

Hláška `objednavkyZmrazeny` zatím nepřenesena — matice je zamčená, ale neřekne proč.

**Code review našel dvě díry, obě potvrzené proti kódu a opravené:**

1. **`removeBundle()` zámek obcházel.** `RemoveFromCartProcessor` se větví — povinný
   balíček jde na `removeBundle()`, kde kontrola nebyla. Jídlo v povinném balíčku by po
   termínu šlo zrušit. Obě větve teď volají sdílené `overRuseni()`.
2. **Zámek odebírání se rozlézal na merch, což legacy NEDĚLÁ.**
   `Shop::zrusZrusitelneLetosniObjednavky()` staví `$typyKZachovani` jen z jídla a
   ubytování — trička/mikiny/předměty se po svém termínu ruší dál, protože za ně GameCon
   dodavateli ještě nezaplatil. Kontrola je proto zúžená na `ProductTagCode::JIDLO`.

Obě opravy mají test, který na původním kódu padá (ověřeno mutací zpět).

### Otevřené, nesouvisí s touto opravou

Když se `UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE` nastaví dřív než termín jídla, stane se
snídaně zrušená `cancelCovered()` na pultu **nevratnou** — `BreakfastCanceller::restore()`
je dosažitelné jen ze `SetAccommodationProcessor`, a ten po termínu ubytování odmítá. Ve
výchozím nastavení jsou oba termíny stejné, takže to dnes nenastane. Existovalo to i před
touto opravou.
