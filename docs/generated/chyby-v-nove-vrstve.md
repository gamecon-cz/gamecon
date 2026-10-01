TL;DR: v `symfony/` se odmítnutí („tohle nejde") hází jako potomek `UserFacingException` s textem z překladového katalogu; na HTTP status ho převede jedna mapa. Všechno ostatní je porucha: nechytá se, zaloguje se a klient dostane 500 bez detailu.

## Kde to je

- `symfony/src/Exception/` — `UserFacingException` (základ) a čtyři druhy odmítnutí
- `symfony/config/packages/api_platform.yaml` — `exception_to_status`: typ → status, jediné místo
- `symfony/config/packages/framework.yaml` — `exceptions`: odmítnutí se loguje jen jako `info`
- `symfony/translations/errors.cs.yaml` — texty všech chyb pro uživatele (doména `errors`)
- `symfony/tests/Translations/ChybovyKatalogTest.php` — hlídá, že kód a katalog sedí oběma směry

## Pravidla

| situace | výjimka | status |
|---|---|---|
| neplatný požadavek (noci nenavazují, varianta cizího produktu, chybí povinné pole) | `InvalidRequestException` | 400 |
| chybí právo (přeplnění bez šéfa infopultu, produkt bez nároku) | `InsufficientPermissionsException` | 403 |
| vyprodáno, nedostatečná kapacita | `CapacityExceededException` | 409 |
| stažené z prodeje, po termínu | `NoLongerAvailableException` | 409 |

- **Nechytat `\RuntimeException`, nepřebalovat na `BadRequestHttpException`.** Chytá i poruchy (SQL, Doctrine, PHPUnit expectace) a pošle klientovi cizí text jako 400, bez logu. Odmítnutí doputuje k API Platform samo.
- **Text nikdy v kódu**, jen klíč a parametry: `$this->translator->trans('cart.sale_ended', ['%product%' => …], 'errors')`. Klíč, který v katalogu chybí, by uživatel dostal jako text — proto `ChybovyKatalogTest`.
- **Porucha zůstává `\RuntimeException`** (chybí řádek v katalogu, nenastavená konfigurace). Na produkci se z ní stane „Internal Server Error": API Platform u 5xx detail skrývá (`ErrorProvider`, mimo debug).
- Legacy import ubytování (`admin/scripts/modules/_ubytovani-…-import-ubytovani.php`) přeskočí řádek jen u `UserFacingException`; porucha import zastaví.

## Gotchas

- `exception_to_status` **přepisuje** výchozí mapu API Platform, proto jsou v ní zopakované její tři položky (serializer, `InvalidArgumentException`, `OptimisticLockException`).
- Klíče té mapy jsou názvy tříd jako řetězce: `!php/const …::class` YAML neumí (`::class` není konstanta). Přejmenování třídy hlídají HTTP testy statusů (`CartApiTest`, `KfcSaleApiTest`).
- Symfony atributy `#[WithHttpStatus]` API Platform nečte — status musí jít přes `exception_to_status`.
- Testy, které si službu staví ručně, dostanou skutečný katalog přes `App\Tests\Support\ChybovePreklady::translator()`, takže dál ověřují text, který uvidí uživatel.
