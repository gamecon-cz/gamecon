Nákup (`shop_nakupy`, `shop_nakupy_zrusene`) ukazuje na **produkt** i na **variantu**; sloupec `id_predmetu` je produkt, ne koupená velikost ani noc, a lidem se ukazuje jako `product_id`.

## Datový model

- `id_predmetu` → `shop_predmety.id_predmetu` = **produkt** (model trička, typ pokoje, jídlo). Od migrace `symfony/migrations/structures/2026-10-02-100033_purchases-point-at-product.php`; dřív u velikostí a nocí ukazoval na jejich vlastní řádek katalogu.
- `variant_id` → `product_variant.id` = koupená **velikost nebo noc**. Je `NOT NULL` v obou tabulkách.
- `id_predmetu` je vždy `product_variant.product_id` varianty nákupu — zapisovače (`CartService`, `AccommodationWriter`, `MealWriter`, `ManualSaleService`) ho berou z produktu varianty, `EntryFeeService` bere variantu z produktu vstupného a `BulkCancelService` kopíruje produkt rušeného nákupu. Databáze shodu nevynucuje.
- Čísla produktů a variant jsou **různé řady, které se překrývají** — `id_predmetu` a `variant_id` nejdou zaměnit.

## Pravidla

- **Lidem `product_id`, v kódu zatím `id_predmetu`** (záměr). Kde sloupec vidí člověk (report, export, quick report), jmenuje se `product_id`, aby se nepletl se starým významem „koupená položka". Fyzický sloupec a SQL v kódu si název drží, dokud ho krok D v [issue #1157](https://github.com/gamecon-cz/gamecon/issues/1157) neodstraní. Nový report, který ho vypisuje, píše `id_predmetu AS product_id`.
- **Co potřebuje velikost nebo noc, čte `variant_id`** přes `shop_varianty_s_typem` (ubytování, trička na infopultu, `Finance`, BFGR). Přes `id_predmetu` jen to, co je pro všechny varianty produktu stejné: typ, název modelu, ročník.
- Víc variant na produkt mají jen trička, předměty s velikostí (ponožky, mikina) a typy pokojů od 2026; jídlo, vstupné a starší ubytování mají variantu jedinou.

## Gotchas

- Quick reporty žijí v `reporty_quick.dotaz`, ne v repozitáři — přejmenování sloupce v nich dělá migrace (`migrace/2026-10-02-164518_quick-reporty-product-id.php`), která přepíše dotaz, jen když zní přesně jako dodaný. `select *` nad nákupem a pohledem katalogu sloupec přejmenovat neumí, proto jsou tam sloupce vyjmenované.
