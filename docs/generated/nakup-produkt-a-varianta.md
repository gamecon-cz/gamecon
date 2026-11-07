Nákup (`shop_nakupy`, `shop_nakupy_zrusene`) ukazuje jen na **variantu** — koupenou velikost nebo noc; produkt (model trička, typ pokoje) je produkt té varianty. Lidem se produkt nákupu ukazuje jako `product_id`.

## Datový model

- `variant_id` → `product_variant.id` = koupená **velikost nebo noc**. Je `NOT NULL` v obou tabulkách.
- Produkt nákupu je `product_variant.product_id`; v Doctrine `OrderItem::getProduct()` a `CancelledOrderItem::getProduct()` vrací produkt varianty. Nákup vlastní sloupec produktu nemá: dřívější `id_predmetu` (kopie produktu varianty, kterou databáze nehlídala) odstranila migrace `symfony/migrations/structures/2026-11-07-100009_legacy-catalog-removed.php`.
- Čísla produktů a variant jsou **různé řady, které se překrývají** — `variant_id` neznamená produkt.

## Pravidla

- **Lidem `product_id`** (záměr). Kde produkt nákupu vidí člověk (report, export, quick report), jmenuje se `product_id` a bere se z `product_variant.product_id`.
- **Co potřebuje velikost nebo noc, čte rovnou variantu**; co je pro všechny varianty produktu stejné (typ, název modelu, ročník), čte produkt varianty.
- **Legacy SQL čte katalog z tabulek, ne z pohledů** (krok D v [issue #1157](https://github.com/gamecon-cz/gamecon/issues/1157)): kategorie přes `product_product_tag` + `product_tag` s kódem z `ProductTagCode` jako parametrem dotazu; dotaz vrací kód kategorie a PHP větví podle `ProductTagCode`, ne podle čísla typu (krok E). Řazení podle kategorie je `FIELD(kategorie, <ProductTagCode::categoryCodes()>)`. Ročník modelu `COALESCE(YEAR(archived_at), ročník)`, název `CONCAT_WS(' ', produkt.nazev, varianta.name)`. Dotaz, který vrací kategorii, připojuje kategorii přes `LEFT JOIN (product_product_tag INNER JOIN product_tag …)`, aby produkt bez kategorie nezmizel.
- Víc variant na produkt mají jen trička, předměty s velikostí (ponožky, mikina) a typy pokojů od 2026; jídlo, vstupné a starší ubytování mají variantu jedinou.

## Gotchas

- Quick reporty žijí v `reporty_quick.dotaz`, ne v repozitáři — změny sloupců v nich dělá migrace, která dotaz přepíše, jen když zní přesně jako dodaný (u dvou reportů, které se přepisují po úsecích, jen když se najdou všechny úseky), a když po přepisu některý report pořád čte, co se maže, skončí výjimkou (`2026-11-07-100008_quick-reports.php` přepisuje, `2026-11-07-100009_legacy-catalog-removed.php` hlídá).
- Migrace z `migrace/` a `symfony/migrations/structures/` běží dohromady seřazené podle jména souboru, ne podle toho, kdy vznikly — migrace, která navazuje na jinou, musí mít jméno, které se řadí za ni.
