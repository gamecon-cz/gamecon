# Import a export e-shopu

TL;DR: report `finance-report-eshop` je zároveň šablona importu a jde naimportovat beze změny
zpátky (ověřeno nad produkčním dumpem, test `exportJdeBezeZmenyNaimportovatZpet`). Jeden řádek
= jedna varianta, sloupce produktu se opakují na každém jejím řádku.

## Vstupní body

- `model/Shop/EshopExport.php` — dotaz exportu (report jen volá `->report()`)
- `model/Shop/EshopImporter.php` — import, `SLOUPCE` je seznam povinných sloupců
- `admin/scripts/modules/_import-eshopu.php` — stránka `Finance → Import e-shopu`
- `tests/Shop/EshopImporterTest.php`

## Sloupce

| produktu (musí se shodovat na všech řádcích produktu) | varianty |
|---|---|
| `product_name`, `product_code`, `archivovano`, `tag` (jen kategorie), `cena_aktualni`, `stav`, `nabizet_do`, `popis`, `vedlejsi`, `snidane_v_cene`, `je_letosni_hlavni` (nepovinný) | `variant_name`, `variant_code`, `cena_varianty` (prázdná = cena produktu), `stav_varianty` (prázdný = stav produktu), `kusu_vyrobeno`, `ubytovani_den` |

## Pravidla, která z kódu nejsou hned vidět

- **Stav je i na variantě.** Noci jednoho typu pokoje mají různý stav (neděle podpultová,
  ostatní veřejné), proto `stav_varianty`. Varianta s kódem svého produktu (výchozí varianta,
  vlastník skupiny) ale vlastní stav mít nesmí a nejde ji ani vynechat: `VariantStateMirror` jí
  při úpravě produktu v adminu přepíše stav stavem produktu. Když z výchozí varianty má být
  jedna z velikostí, zůstane v listu a dostane `variant_name`.
- **`archivovano` se bere ze souboru.** Export obsahuje všechny ročníky; import minulý ročník
  neoživí. Produkt, který v souboru chybí, se archivuje (jen pokud ještě archivovaný není).
- **Varianta, která v souboru chybí, se vyřadí** (`state = RETIRED`), smazat nejde kvůli nákupům.
- **Zbylé legacy řádky velikostí a nocí** (kód = kód varianty jiného produktu) nejsou produkty:
  v exportu nejsou, import je nearchivuje a jejich kód nejde použít jako `product_code`. Import
  jim srovná `stav` (a `archived_at`) s variantou, aby je mirror při pozdější úpravě v adminu
  nepřepsal zpátky. S krokem C v [issue #1157](https://github.com/gamecon-cz/gamecon/issues/1157)
  zmizí i tohle.
- **Pořadí variant import nepřečísluje.** Nová varianta dostane pozici za poslední; existující
  si drží svou (u vlastníků skupin čísla nejdou od nuly a round-trip by je jinak změnil).
- **Varianta nejde přesunout pod jiný produkt** — nákupy by zůstaly u původního. Import to
  odmítne celý, jedna transakce.
- Starý formát (řádek na velikost/noc, `nazev` / `kod_predmetu`) import odmítne chybějícími sloupci.
