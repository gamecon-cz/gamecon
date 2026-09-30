# Který předmět je „letošní model"

TL;DR: placka a kostka mají každý rok nový design, ale festival zároveň doprodává staré.
Zdarma pro organizátory (a v BFSR jako „letošní") je **právě jeden produkt na druh** a ten
**jmenuje přesným kódem pravidlo slevy** daného ročníku. Kód do pravidla zapisuje import
e-shopu ze sloupce `je_letosni_hlavni`. Žádná heuristika.

## Vstupní body

- `discount_rule` — pravidla `kostka_zdarma` / `placka_zdarma`, rozsah `product_code`:
  `codeFragment` říká druh (`kostka`, `placka`), `productCode` konkrétní předmět
- `symfony/src/Discount/DiscountableItem.php::matches()` — `PRODUCT_CODE` se shoduje jen s tím kódem
- `model/Shop/LetosniPredmetyZdarma.php` — stav pro stránku importu, zápis z importu, varování
- `model/Shop/EshopImporter.php` — nepovinný sloupec `je_letosni_hlavni` (1 = letošní)
- `model/Report/BfsrReport.php` — dělí placky na letošní a staré podle téhož pravidla
- `admin/scripts/zvlastni/reporty/finance-report-eshop.php` — `je_letosni_hlavni` = 1 jen u jmenovaných

## Co „letošní" znamená

Ne „každý, co je letos v nabídce" — těch je víc, protože se starší designy doprodávají pod
letošním ročníkem. V 2026 jsou čtyři placky a devět kostek, zdarma je jen `placka_2026_verne`
a `kostka_2026_verne`. Záměna není kosmetická: sleva padne na jiný předmět a účastník zaplatí
jinou částku (tak vzniklo [issue #1172](https://github.com/gamecon-cz/gamecon/issues/1172)).

## Proč ne heuristika, proč ne příznak na produktu

Legacy vybíral letošní předmět dotazem `NOT EXISTS(nákup v dřívějším roce)` + `ORDER BY
model_rok DESC, je_letosni_hlavni DESC, cena_aktualni DESC, id_predmetu`. Příznak
`je_letosni_hlavni` (karta 1338, 2025) nesla jen kostka; u placek o vítězi rozhodovalo
**pořadí nahrání**. Eshopová migrace sloupec zrušila a pohled ho dopočítává jako
`archived_at IS NULL` (1 pro všechno letošní), takže heuristika by na nových datech vybrala
jinou kostku než legacy. Navíc `NOT EXISTS` zpětně měnil, co je „letošní" v minulém roce.

Pravidla slev jsou **per ročník**, takže přesný kód v pravidle řeší historii sám (každý
ročník jmenuje svůj předmět) a sleva i BFSR čtou jeden zdroj. Příznak na produktu by potřeboval
vlastní vazbu na ročník a admin UI navíc.

## Jak se to nastavuje a jak se pozná, že chybí

- **Import** (`Finance → Import e-shopu`): řádek s `je_letosni_hlavni = 1` jmenuje letošní
  předmět svého druhu. Právě jeden na druh → zapíše se do pravidla; žádný nebo víc → varování
  a pravidlo zůstane beze změny. List bez sloupce pravidla nemění.
- **Stránka importu** vždy ukazuje, co pravidla jmenují, a varuje u chybějícího kódu nebo
  předmětu, který v letošní nabídce není.
- **Při výpočtu cen** pravidlo bez kódu nic nedá — nikdo nedostane předmět zdarma, místo aby
  ho dostal ke špatnému předmětu. Chyba je vidět ve financích.

Migrace `2026-09-30-100027` jmenuje pro 2026 předměty, které vybral legacy; pravidla jiných
ročníků zůstanou bez kódu, dokud je nenastaví import.

Pozor: export (`finance-report-eshop`) dnes nejde naimportovat beze změn — dává sloupec `typ`,
import chce `tag`. List pro import se proto připravuje zvlášť.
