# Testování zabezpečeného API

TL;DR: jak se v `symfony/tests/` testují endpointy za firewallem `^/symfony/api` (JWT, role) — přes API Platform `ApiTestCase` a `createClient()`, s transakčním obalem — a které další vzory z projektu `3brs/PiercingApp` sem jdou převzít.

## Rozsah

Týká se testů nad novým Symfony/API Platform stackem, ne legacy testů v `tests/`. Legacy a Symfony testy sdílí jednu PHPUnit sadu a jednu testovací databázi, což je zdroj většiny omezení níže.

## Vstupní body v kódu

- `symfony/tests/Api/ProductApiTest.php` — jediný test, který volá API přes HTTP vrstvu; obsahuje helper `authenticatedRequestHeaders()` (vytvoří admina, podepíše JWT).
- `symfony/tests/ApiResource/ApiSecurityTest.php` — testuje **deklarované metadata** operací (reflexí nad `#[ApiResource]`), ne runtime chování. Nebootuje kernel, je to čistý `TestCase`. Viz Invariant níž.
- `symfony/src/Security/JwtAuthenticator` — čte `Authorization: Bearer`, dekóduje přes `JwtService`, načte uživatele.
- `symfony/src/Service/JwtService::generateJwtToken()` / `extractUserData()` — podepisování tokenu.
- `symfony/config/packages/security.yaml` — firewall `api` (stateless, `custom_authenticators`), `access_control` (`^/symfony/api` → `ROLE_USER`, `^/symfony/api/public` → `PUBLIC_ACCESS`).
- `symfony/src/Entity/User::getRoles()` — odvozuje `ROLE_ADMIN` z kódů rolí `organizator/admin/infopult/cfo`.
- `symfony/tests/AbstractDatabaseKernelTestCase` — transakční obal pro Symfony testy zapisující do DB.
- `symfony/tests/Db/DatabaseCleanupTest.php` — regresní pojistka, že po Symfony testech nezůstávají řádky; čte **legacy** spojením (mimo Doctrine transakci), jinak by na tu chybu neviděla.

## Jak se dnes autentizuje v testu

Přes `ApiTestCase::createClient()`. Bázová třída nabízí `jsonLdClient(array $headers)`, `ProductApiTest` nad tím staví `adminClient()` a `clientForUser()`. Request je pak jeden řádek:

```php
$response = $this->adminClient()->request('GET', '/symfony/api/products?tags.code=' . $tagCode);
$data = $response->toArray();   // hodí výjimku, když tělo není JSON
```

Prochází se **reálný** firewall i `JwtAuthenticator` — token se podepisuje `JwtService`, nic se neobchází.

Admin se v testu **vytváří**, nedohledává: fixtury nikomu nepřidělují roli, která by dala `ROLE_ADMIN`. Role se přidává SQL insertem do `uzivatele_role`, takže po něm musí přijít `$this->entityManager()->clear()` — jinak už načtená entita `User` o nové roli neví a `getRoles()` hlásí běžného účastníka.

## Gotchas

- **`createClient()` defaultně bootuje vlastní kernel.** `AbstractDatabaseKernelTestCase` proto nastavuje `$alwaysBootKernel = false`, aby klient použil kernel nabootovaný v `setUp()`. Bez toho by `createClient()` shodil ten předchozí — a s ním spojení držící transakci testu (stejná past jako `bootKernel()` níž). Je to zároveň default v API Platform 5.0, takže to umlčí deprecation.
- **Symfony testy, které píšou do DB, musí dědit z `App\Tests\AbstractDatabaseKernelTestCase`** — ta obaluje každou metodu transakcí na **Doctrine** spojení a odroluje ji. Legacy `Gamecon\Tests\Db\AbstractTestDb` to nezvládne: jede na jiném spojení (ověřeno `SELECT CONNECTION_ID()` na obou) a míchání legacy fixtur s Doctrine čtením týchž řádků deadlockuje.
- **‼️ V testu nad `AbstractDatabaseKernelTestCase` nikdy nevolej `bootKernel()`.** Bázová třída kernel bootuje v `setUp()`; opakovaný `bootKernel()` uvnitř testu nejdřív provede `ensureKernelShutdown()`, **čímž zahodí spojení držící transakci** — zápisy pak commitnou na novém spojení a v DB zůstanou. Přesně tohle bylo příčinou, proč `ProductApiTest` po zavedení obalu pořád nechával 9 řádků. Hlídá to `symfony/tests/Db/DatabaseCleanupTest.php`.
- **`product_tag.created_at` je `NOT NULL` bez defaultu a entita `ProductTag` ho nemapuje** — tag vytvořený přes Doctrine databáze odmítne. Migrace i testy ho proto vkládají SQL. Sesterské tabulky (`product_bundle`, `product_discount`) default mají, tahle ne.
- **Kolekce má klíč `member`, ne `hydra:member`.** API Platform 4 (`^4.2`, reálně 4.3.x) má `hydra_prefix` defaultně `false` a projekt to nikde nepřepisuje.
- **PHPStan testy neanalyzuje** — `phpstan.dist.neon` má v `paths:` jen `symfony/config/` a `symfony/src/`. Jediná kontrola nad testy je, že procházejí.
- **Sada je závislá na pořadí.** `--order-by=random` shodí i samotnou legacy část (neuzavřené output buffery apod.). CI jede deterministicky. Neplatí tedy, že „zeleně = testy jsou izolované".

## Co má PiercingApp vyřešené líp

Projekt `~/customers/3brs/PiercingApp` (Symfony + API Platform 4 + LexikJWT) má na tohle postavenou infrastrukturu. Následující je zápis z průzkumu — **není to návod k okamžitému převzetí**, většina vyžaduje `ApiTestCase`.

### Použitelné hned, bez nových závislostí

**Matice rolí přes data providery, kde deny-list je vždy doplněk allow-listu.**
Test si přepíše jediný getter (`getRolesWithAccess()`) a automaticky dostane datasety i pro všechny *ostatní* role, které mají dostat 403. Provider navíc **asertuje, že množina není prázdná**, a v chybové hlášce jmenuje metodu i třídu k přepsání — takže špatně nakonfigurovaný negativní test spadne, místo aby prošel s nula datasety.

U nás je enum `App\Enum\RoleMeaning` (29 case'ů: `ADMIN`, `CFO`, `INFOPULT`, `MINI_ORG`, …). Pozor, **naivní doplněk by u nás dal 28 „forbidden" datasetů na test** — u PiercingApp jich je 5, protože jejich `RoleEnum` má 5 case'ů a je to čistá hierarchie oprávnění. Náš enum míchá oprávnění (`ADMIN`, `INFOPULT`) s benefity a příznaky (`STREDECNI_NOC_ZDARMA`, `NEODHLASOVAT`, `PARTNER`), takže testovat proti všem nemá smysl.

Použitelné by to bylo nad **ručně vybranou podmnožinou** rolí, které reálně ovlivňují `User::getRoles()` — tedy `organizator/admin/infopult/cfo` (dávají `ROLE_ADMIN`) plus jedna běžná role bez oprávnění. To je 5–6 datasetů, ne 29.

Dnes máme 24 operací se `security:` výrazem (19× `ROLE_ADMIN`, 5× `ROLE_USER`) a runtime test jen nad `Product`.

**401 a 403 jako dvě různé věci.** 403 se testuje autentizovaným klientem přes matici rolí; 401 samostatným testem bez tokenu. 405 (route pro daný firewall neexistuje) je u nich ještě třetí kategorie. **Převzato** — `ProductApiTest::testAnonymousRequestIsRejected()` a `testNonAdminIsForbidden()`.

Při ověřování mutací vyšlo najevo, na čem ty dva testy reálně visí: **rozhoduje `security:` výraz operace, ne řádek v `access_control`.** Když se `^/symfony/api` povolí na `PUBLIC_ACCESS`, obě odpovědi zůstanou stejné (401 i 403); teprve povolení operace z `ROLE_ADMIN` na `ROLE_USER` změní 403 na 200. Kdo bude psát podobný test jinde, ať s tím počítá — `access_control` je tu druhá vrstva, ne ta rozhodující.

**Testovat security výraz na dvou úrovních.** Unit přes `AuthorizationCheckerInterface` (role hierarchy) + end-to-end přes API. Přínos je hlavně u `role_hierarchy`, kde unit test řekne *proč* to prošlo.

### Odemčené přidáním `browser-kit` (zatím nevyužité)

`symfony/browser-kit` + `symfony/http-client` jsou od září 2026 v `require-dev`, takže `ApiTestCase` funguje. Tím padl blokátor u těchhle vzorů — jsou k dispozici, jen po nich zatím nebyl důvod sáhnout:

- `assertJsonContains()` a `assertMatchesResourceCollectionJsonSchema()` — kontrola JSON-LD obálky proti vygenerovanému schématu. **Tohle by chytlo chybu, kterou jsme udělali**: ručně psaná aserce na klíč kolekce si nevšimla, že `hydra:member` v API Platform 4 neexistuje.
- `createAuthorizedClient(RoleEnum)` s cachováním tokenu per-role — u nás zatím zbytečné, `adminClient()` stačí.
- Rodina `do*Test()` metod (`doForbiddenPatchTest`, `doNotFoundGetTest`, …) ústící do jednoho `doRequest($uri, $method, $expectedCode, $role)`. Dává smysl až s maticí rolí a víc resources.
- Přihlášení **reálným** `POST /api/login` místo podepsání tokenu v testu (u nich se heslo v testu nehashuje: `password_hashers: … 'plaintext'`). My token podepisujeme přímo přes `JwtService`; reálný login by navíc pokryl `json_login`, ale ten pro API zatím nemáme.

### Zajímavé, ale ne pro nás

- **DAMA\DoctrineTestBundle** místo ručních transakcí — obalí každý test transakcí a odroluje ji, navíc drží statické spojení. My to řešíme vlastní bázovou třídou (viz Gotchas), což pro dva testovací soubory stačí a nepřidává závislost. Kdyby Symfony testů zapisujících do DB bylo výrazně víc, DAMA je robustnější — hlavně proto, že díky statickému spojení nezáleží na tom, kolikrát se kernel rebootuje.
- **Foundry `ResetDatabase` je u nich zakázaný** (i přes `conflict` v `composer.json`), protože při paralelním běhu závodí s jejich migračním resetem. Dobré vědět, kdyby nás někdy napadlo ho zapnout.
- **Odvozování cesty a resource třídy z názvu testu** (`App\Tests\Entity\Store\StoreTest` → `App\Entity\Store\Store` → `/api/stores`). Funguje jen proto, že jejich `tests/` zrcadlí `src/`. U nás to tak není.
- `ResetSequencesExtension` — PostgreSQL sekvence nejsou transakční, takže je po každém testu resetují reflexí nad DAMA spojením. My jsme na MariaDB, netýká se.

## Invariant: entita nikdy není veřejně dostupná

**Přes veřejné API se nikdy nechodí na entitu, jen na DTO.** Každá operace entity proto musí být zabezpečená; operace **bez** `security:` je chyba, ne výchozí stav — API Platform ji nechá otevřenou komukoli, koho pustí firewall. (záměr)

Hlídá to `ApiSecurityTest::testEveryEntityOperationIsGuarded()`, jeden dataset na operaci. Kontroluje obojí: že `security:` vůbec existuje, a že to není `PUBLIC_ACCESS`.

Tři věci, na kterých to stojí a které se snadno rozbijí při úpravě:

- **Class-level `security:` se do operace nepropíše, dokud metadata nesloučí API Platform.** Čtení syrového atributu vrátí na operaci `null`, i když třída zabezpečení deklaruje — proto se dělá fallback `$operation->getSecurity() ?? $classSecurity`. Bez něj by test padal na platném zápisu.
- Adresář se prochází **rekurzivně** (`RecursiveDirectoryIterator`). Původní `glob('*.php')` míjel `src/Entity/Enum/` a `src/Entity/Partials/` — dnes tam žádná ApiResource entita není, ale slepé místo to bylo.
- Data provider **asertuje, že nějaká entita byla nalezena**, jinak by celý test prošel naprázdno, kdyby se entity přesunuly jinam.

## Otevřené otázky

- Zavést matici rolí (viz výše) až bude API testů víc? Dnes je runtime test jen nad `Product`, na 24 zabezpečených operací.
