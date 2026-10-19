# Audit změn nákupů — co se změnilo, kdy, kdo a odkud

TL;DR: Každá změna řádku `shop_nakupy` (vznik, změna, smazání) se zapíše do `shop_order_item_log` jedním Doctrine listenerem, ve stejné transakci jako změna sama. Vidí jen zápisy přes entity manager, proto se do `shop_nakupy` **nesmí psát ručním SQL** — hlídá to test.

## Vstupní body

- `symfony/src/EventListener/OrderItemAuditListener.php` — jediný zapisovač logu
- `symfony/src/Service/AuditOriginProvider.php` — kdo jedná a odkud (z paměti procesu, bez dotazu)
- `symfony/src/Entity/OrderItemLog.php` — čtení historie (zapisuje se přes DBAL, entita je `readOnly`)
- `symfony/migrations/structures/2026-10-19-100043_purchase-log.sql` — tabulka `shop_order_item_log`
- `symfony/src/Service/PurchaseTransferService.php` — přesun nákupů při slučování uživatelů
- `symfony/tests/Audit/PurchaseWritesGoThroughDoctrineTest.php` — selže na ručním `INSERT`/`UPDATE`/`DELETE` nad `shop_nakupy`

## Co řádek obsahuje

- `order_item_id`, `customer_id` (zákazník po změně), `year`, `action` (`created` / `updated` / `removed`), `changed_at`
- `changes` — JSON `sloupec shop_nakupy → [před, po]`. Vznik nemá „před" (`null`), smazání nemá „po". U vzniku a smazání jen jádro nákupu (zákazník, objednatel, objednávka, varianta, balíček, ročník, ceny, sleva, `override_log`); u změny cokoli, co se změnilo, včetně snapshotů názvů
- `changed_by_id` — přihlášený uživatel; `NULL` = nikdo (cron, příkaz)
- `origin` — název routy, cesta legacy stránky nebo skript; u konzole i název příkazu. Nikdy ne query string ani argumenty, ty mohou nést tokeny. Delší původ se zkrátí na délku sloupce, 255 znaků

## Pravidla

- **Log je ve stejné transakci jako změna.** `postPersist`, `postUpdate` i `postRemove` běží uvnitř transakce flushe, takže se zapíše a vrátí společně. Selhání logu proto shodí i nákup — nákup bez stopy je horší než žádný nákup. Pinuje to `OrderItemAuditListenerTest::testFailingLogInsertAbortsThePurchase`.
- **Žádné cizí klíče.** Log musí přežít nákup i uživatele (smazání nákupu je jedna ze zaznamenávaných událostí) a klíč na `uzivatele_hodnoty` by při každém nákupu bral sdílený zámek na řádku zákazníka. Slučování uživatelů proto přepíše `customer_id` a `changed_by_id` ve starých řádcích ručně (`PurchaseTransferService`).
- **Smazání se zachytí v `preRemove`.** Doctrine odkládá všechny `postRemove` až po smazání všech entit ve flushi a do té doby vynuluje vygenerovaná id — nákupu i všeho, co se maže spolu s ním (objednávka s kaskádou na nákupy).
- **Indexy:** `order_item_id` (historie jednoho nákupu), `(customer_id, year)` (historie zákazníka), `origin` (co změnila daná stránka, skript nebo příkaz) a `changed_by_id` (co kdo změnil). Index na `changed_by_id` je hlavně proto, aby přepisování autorství při slučování uživatelů neprocházelo celou tabulku pod zámky otevřené transakce.
- **Anonymizovaná databáze tabulku vyprazdňuje** (`AnonymizovanaDatabaze`, seznam příliš citlivých tabulek): nemá cizí klíče, takže se id uživatelů v ní při anonymizaci neposunou, a navíc leží i v JSONu `changes`.
- **Id souvisejících entit se loguje jako číslo**, ne jako odkaz; identity map je drží jako řetězce z ovladače, listener je převádí na `int`.
- **Kdo jedná:** token zabezpečení (JWT, API), jinak legacy session přes `Uzivatel::aktualniEditorIdZeSession()`, tedy tentýž zdroj pravdy jako `uzivatele_hodnoty_log`. Admin přepnutý na jiného uživatele se v legacy session jeví jako ten uživatel — odlišit ho nejde.

## Co log nevidí nebo vidí navíc

- DQL `UPDATE`/`DELETE` a nativní SQL — obejdou UnitOfWork. Test výše chytá příkaz nebo volání helperu, které pojmenuje tabulku nebo entitu; zápis přes proměnnou s názvem tabulky nebo přes `->delete()` repozitáře nechytí.
- Změny, které dělá sama databáze: `ON DELETE CASCADE` z `uzivatele_hodnoty` (smazaný uživatel odnese nákupy) a `ON DELETE SET NULL` na `order_id`, `bundle_id` a `id_objednatele` (smazaná objednávka, balíček nebo objednatel). Slučování uživatelů proto přesouvá nákupy před smazáním přes Doctrine; obecná smyčka slučování ale může smazat přebytečnou objednávku starého uživatele a tím vynulovat `order_id` jeho nákupů bez stopy.
- Migrace — běží jednou a nejsou provozní změnou.
- Souběžné smazání téhož nákupu ze dvou požadavků zapíše dva řádky `removed`: Doctrine nekontroluje, kolik řádků `DELETE` smazal.
- U smazání se loguje stav entity v paměti v okamžiku `remove()`, ne řádek přečtený z databáze. Liší se, jen když ji někdo těsně před smazáním změnil nebo když v paměti zastarala.

## Cena (naměřeno na lokálním Dockeru, test DB, každý flush commitnutý)

- Listener sám ~0,15 ms na nákup, z toho ~0,1 ms je `INSERT`; zbytek je čtení hodnot entity.
- Jednotlivý zápis s vlastním commitem: ~0,35 ms navíc na flush, který trvá ~2,8 ms (+12 %). Hromadný flush: ~0,1 ms navíc na nákup.
- Řádek zabere ~265 B dat, se dvěma indexy změřeno ~315 B; třetí index (`changed_by_id`) přidal po měření, odhadem dalších ~15 B a ~6 µs na zápis. Čtvrtý (`origin`) také nebyl měřen; drží celý text původu, takže bude dražší než číselné indexy, odhadem desítky bajtů na řádek.
- Dávkový zápis (jeden víceřádkový `INSERT` na flush) by u hromadných operací snížil cenu samotného `INSERT` z ~63 na ~14 µs na řádek, ale jednotlivé zápisy, které dominují při náporu registrace, nezrychlí; zatím se nedělá.
- Hromadné rušení objednávek v adminu (1000 nákupů ve 200 objednávkách): jeden ruční `DELETE` ~8 ms, přes Doctrine ~300 ms, s logem ~400 ms.

## Otevřené

- Zobrazení historie v adminu — data jsou, UI ne.
- Retence — log roste o řádek na každou změnu nákupu a nikdy se nemaže.
