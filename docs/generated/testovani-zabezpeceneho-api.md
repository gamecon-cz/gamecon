# Testování zabezpečeného API

TL;DR: jak se v `symfony/tests/` testují endpointy za firewallem `^/symfony/api` (JWT, role), co je dnes hotové, a které vyzrálejší vzory z projektu `3brs/PiercingApp` sem jdou převzít — a co jim zatím brání.

## Rozsah

Týká se testů nad novým Symfony/API Platform stackem, ne legacy testů v `tests/`. Legacy a Symfony testy sdílí jednu PHPUnit sadu a jednu testovací databázi, což je zdroj většiny omezení níže.

## Vstupní body v kódu

- `symfony/tests/Api/ProductApiTest.php` — jediný test, který volá API přes HTTP vrstvu; obsahuje helper `authenticatedRequestHeaders()` (vytvoří admina, podepíše JWT).
- `symfony/tests/ApiResource/ApiSecurityTest.php` — testuje **deklarované metadata** operací (reflexí nad `#[ApiResource]`), ne runtime chování. Nebootuje kernel, je to čistý `TestCase`.
- `symfony/src/Security/JwtAuthenticator` — čte `Authorization: Bearer`, dekóduje přes `JwtService`, načte uživatele.
- `symfony/src/Service/JwtService::generateJwtToken()` / `extractUserData()` — podepisování tokenu.
- `symfony/config/packages/security.yaml` — firewall `api` (stateless, `custom_authenticators`), `access_control` (`^/symfony/api` → `ROLE_USER`, `^/symfony/api/public` → `PUBLIC_ACCESS`).
- `symfony/src/Entity/User::getRoles()` — odvozuje `ROLE_ADMIN` z kódů rolí `organizator/admin/infopult/cfo`.

## Jak se dnes autentizuje v testu

Bez klienta. `ProductApiTest` staví `Request::create()` ručně a volá `$kernel->handle($request)`; token přiloží jako server parametr `HTTP_AUTHORIZATION`. Prochází se přitom **reálný** firewall i `JwtAuthenticator`, takže to není obcházení bezpečnosti — jen ruční doprava requestu.

Admin se v testu **vytváří**, nedohledává: fixtury nikomu nepřidělují roli, která by dala `ROLE_ADMIN`. Token se drží ve statické property (jeden admin na třídu, ne na metodu — viz Gotchas).

## Gotchas

- **`ApiTestCase` / `createClient()` nejde použít.** Třída sice existuje (`vendor/api-platform/core/src/Symfony/Bundle/Test/ApiTestCase.php`), ale její `Client` vyžaduje `symfony/browser-kit`, `symfony/http-client` a `symfony/dom-crawler` — **žádný z nich není nainstalovaný**. Tohle je jediný důvod, proč se requesty skládají ručně, a zároveň hlavní blokátor převzetí vzorů z PiercingApp (viz níže).
- **Symfony testy nejsou obalené transakcí.** Dědí ze `symfony`ho `KernelTestCase`, ne z `Gamecon\Tests\Db\AbstractTestDb`, takže je míjí legacy transakční obal. Co zapíšou, v DB zůstane po zbytek běhu. Napříč běhy to nevadí (bootstrap testovací DB pokaždé zahodí a postaví znovu), ale v rámci jednoho běhu leží ty řádky ve stejné DB jako ~900 legacy testů.
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

### Blokované na chybějícím `browser-kit`

- `createAuthorizedClient(RoleEnum)` — jednořádkové získání autentizovaného klienta; token se cachuje per-role v rámci testu, uživatel se zakládá lazy (`ensureAdminUserWithRoleExists()`).
- Rodina `do*Test()` metod (`doForbiddenPatchTest`, `doUnprocessablePostTest`, `doNotFoundGetTest`, …) — všechny ústí do jednoho `doRequest($uri, $method, $expectedCode, $role)`.
- `assertJsonContains()` / `assertMatchesResourceCollectionJsonSchema()` — kontrola JSON-LD obálky proti vygenerovanému schématu.
- Přihlášení **reálným** `POST /api/login` místo podepsání tokenu v testu (heslo se v testu nehashuje: `password_hashers: … 'plaintext'`).

### Zajímavé, ale ne pro nás

- **DAMA\DoctrineTestBundle** místo ručních transakcí — obalí každý test transakcí a odroluje ji. Vyřešilo by to náš problém s neuklízenými Symfony testy elegantně, jenže by se to muselo srovnat s legacy `AbstractTestDb`, který si transakce řídí sám na druhém spojení. Netriviální.
- **Foundry `ResetDatabase` je u nich zakázaný** (i přes `conflict` v `composer.json`), protože při paralelním běhu závodí s jejich migračním resetem. Dobré vědět, kdyby nás někdy napadlo ho zapnout.
- **Odvozování cesty a resource třídy z názvu testu** (`App\Tests\Entity\Store\StoreTest` → `App\Entity\Store\Store` → `/api/stores`). Funguje jen proto, že jejich `tests/` zrcadlí `src/`. U nás to tak není.
- `ResetSequencesExtension` — PostgreSQL sekvence nejsou transakční, takže je po každém testu resetují reflexí nad DAMA spojením. My jsme na MariaDB, netýká se.

## Otevřené otázky

- Stojí `symfony/browser-kit` + `symfony/http-client` (dev dependencies) za to, aby šlo použít `ApiTestCase`? Odemklo by to většinu výše zmíněného. Nikdo o tom zatím nerozhodoval.
- `ApiSecurityTest::testNoEntityExposesPublicAccess()` kontroluje jen, že operace **nemá** `PUBLIC_ACCESS`. Entita úplně **bez** `security:` projde — a to je nebezpečnější případ. Dnes latentní (všechny ApiResource entity nějaké `security:` mají), ale jako regresní pojistka to nefunguje.
- Neuklízející Symfony testy: nechat, nebo systémově vyřešit (transakční obal i pro `KernelTestCase`, nebo DAMA)?
