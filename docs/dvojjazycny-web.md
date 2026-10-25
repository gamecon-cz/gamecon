# Analýza: dvojjazyčný web (čeština + angličtina)

Analýza ke stavu `main` na `f2c491d90` (19. 10. 2026).

## Shrnutí

- **Dnes web s jazyky vůbec nepočítá.** Všechny texty jsou napevno česky, `<html lang="cs">` je v šablonách natvrdo a u uživatele se jazyk nikam neukládá. Jediný anglický obsah je ručně psaná stránka `/en` (tabulka `stranky`, id 127, naposledy opravená 2023).
- **Práce má tři vrstvy:**
  - texty v kódu: zhruba 600–800 řetězců v PHP na veřejné části a 200–250 v Preactu;
  - obsah v DB, který píšou orgové: aktivity, stránky, novinky, tagy, e-shop;
  - e-maily, hlášky a formát dat a cen.
- **Nejdražší nebude kód, ale překlad a každoroční údržba obsahu.** Aktivity se každý rok píšou znovu.
- **Na co navázat:**
  - [PR #1058](https://github.com/gamecon-cz/gamecon/pull/1058) (přepis e-shopu) přináší `symfony/translation` (`default_locale: cs`), katalog `symfony/translations/errors.cs.yaml` a test `ChybovyKatalogTest`, který hlídá, že kód a katalog sedí. Na `main` zatím translator není.
  - [PR #1362](https://github.com/gamecon-cz/gamecon/pull/1362) přesouvá texty e-mailů do DB.
  - Do obou je výhodné přidat jazyk hned, ne zpětně.

## 0. Rozhodnutí, která musí padnout první

1. **Rozsah.** Doporučení: přeložit celou veřejnou část pro účastníka (titulka, praktické info, program, přihláška, finance, e-maily). Admin nechat česky.
2. **Popisy aktivit.** Anglický popis larpu, který se hraje česky, cizince spíš zmate.
   - Doporučení: nový atribut **„jazyk aktivity"** (česky / anglicky / jazykově nezávislá) a filtr v programu.
   - Je to levné a pro cizince to má největší cenu, i kdyby se nic dalšího nepřeložilo.
3. **Kdo bude překládat.** Buď šéfové linií u svých aktivit, nebo jeden překladatel. Podle toho EN pole patří buď do editoru aktivity, nebo do samostatného přehledu či exportu.
4. **Korektura anglických textů:** má mít vlastní příznak, nebo ne?
5. **Slovenština** se nepředpokládá.

## 1. Jak web pozná jazyk

**Doporučení: prefix `/en/…`.** Funguje bez nové subdomény na hostingu, dá se sdílet odkazem a podporuje `hreflang` pro vyhledávače.

- **Jedno místo v routingu:** [`Url::zAktualni()`](../model/url.php#L17). Z něj čtou cestu všechny routery (`Modul::zUrl`, `Stranka::zUrl`, `TypAktivity::zUrl`, `Uzivatel::zUrl`). Stačí tam odtrhnout `en/` a nastavit jazyk požadavku.
- **Odkazy:** web má `<base href="{base}">` (hodnota `URL_WEBU . '/'` se plní v [`web/index.php`](../web/index.php#L101)) a přes 60 relativních odkazů. Když se base nastaví na `…/en/`, přepnou se samy. Opravit je potřeba asi 14 relativních odkazů na `soubory/…` a pár natvrdo psaných `https://gamecon.cz/…`.
- **Adresy stránek** nechat česky (`/en/program`, `/en/larpy`). Překládat je nestojí za to.
- **Kolize:** dnešní CMS stránka `/en` se stane anglickou titulkou.
- **Přepínač jazyka** v menu povede na stejnou cestu. Podle jazyka prohlížeče automaticky nepřesměrovávat, nanejvýš nabídnout lištu.
- **Uložení preference:** cookie a nový sloupec `uzivatele_hodnoty.jazyk`. Ten je nutný pro e-maily z cronu, kde žádný request není.
- **Pro vyhledávače:**
  - `lang="cs"` je natvrdo na 5 místech: [`index.xtpl`](../web/sablony/blackarrow/index.xtpl#L3), `login.xtpl`, `cip.xtpl`, `vyjimkovac-omluva.xtpl`, `GcMailSablona.php`.
  - V [`Info`](../model/Web/Info.php) chybí `hreflang` a `og:locale`.
  - RSS má natvrdo `<language>cs</language>`.

## 2. Texty v kódu

**Nástroj: Symfony Translator s formátem zpráv ICU.**

- **PHP:** globální `t('klic', [...])`.
- **Šablony:** nová syntaxe `{t:klic}` v [`XTemplate::convertVariables()`](../model/XTemplate/XTemplate.php#L168). Bude se kompilovat na volání za běhu, takže cache šablon zůstane jedna pro oba jazyky.
- **Mluvnice:** ICU zvládne české množné číslo (1 / 2–4 / 5+) i rod: `{pohlavi, select, f {přihlášena} other {přihlášen}}`. Tím zmizí lepení koncovek přes `koncovkaDlePohlavi()` (asi 40 míst) a `{a}` v hláškách.
- **První katalog:** hlášky v [`nastaveni-hlasky.php`](../nastaveni/hlasky/nastaveni-hlasky.php) a `nastaveni-hlasky-subst.php`. Je jich 36 a už teď mají klíče.
- **Test katalogu** z #1058 rozšířit o pravidlo, že `cs` a `en` mají stejné klíče.
- **Preact:** stačí malá vlastní funkce `t()` se slovníkem v bundlu a jazykem předaným přes `GAMECON_KONSTANTY` ([`Program::gameconKonstanty()`](../model/Aktivita/Program.php#L45)). Na 250 řetězců není potřeba i18next.

**Kde je textu nejvíc** (dolní odhad podle řádků s diakritikou):

| Část | Řetězců |
|---|---|
| šablony `web/sablony/blackarrow` a moduly `web/moduly` | ~250 (nejvíc `prihlaska.xtpl`, `finance.php`, `titulka.xtpl`) |
| model, který skládá veřejné HTML (registrace, shop, ubytování, finance, přihlašovátko) | ~400–500 |
| výjimky a validace, které vidí uživatel | ~100–150 |
| Preact | ~200–250 (nejvíc týmy, program, filtry) |

**Pozor na místa, kde čeština není jen text, ale logika.** Ta se musí přepsat dřív, než se cokoli přeloží:

- **E-shop podle názvů:**
  - [`Shop::bezDne()`](../model/Shop/Shop.php#L100) zjišťuje druh jídla tak, že z názvu odtrhne český den.
  - Ubytování porovnává název s `'Spacák'` ([`ShopUbytovani.php:623`](../model/Shop/ShopUbytovani.php#L623)), snídaně se hledají přes `LIKE 'Snídaně%'`, reporty přes `'Tričko%'`.
  - Je potřeba přejít na kódy nebo typy, ideálně v rámci #1058.
- **Program v Preactu:**
  - Český název linie slouží jako klíč seskupení i jako hodnota filtru v URL (`?linie=[…]`). Řadí se podle českého seznamu `PROGRAM_ŘAZENÍ_LINIE` v [`env.ts`](../ui/src/env.ts).
  - Názvy dnů s háčky jsou taky klíče.
  - Je potřeba přejít na ID.
- **Data:** [`DateTimeCzTrait::format()`](../model/Cas/DateTimeCzTrait.php#L230) vždy přepíše dny na české. Tuhle třídu používá zhruba 240 míst. Česky formátují i `relativni()` a `relativniVBudoucnu()` ve stejné traitě a `datum2/3/4()` ve `funkce.php`. Řešení je formátovat podle jazyka přes `IntlDateFormatter`.
- **Markdown:** [`Smartyp`](../model/smartyp.php#L24) po každém převodu vnutí české uvozovky „“ a anglický text by tím poškodil. Typografii je potřeba volit podle jazyka.
- **Drobnosti:**
  - Generátor názvů týmů ([`NazevTymuGenerator`](../model/Aktivita/NazevTymuGenerator.php) a [`nazevTymu.ts`](../ui/src/utils/nazevTymu.ts)) potřebuje anglické slovníky.
  - „Kč" je napsané natvrdo asi 60× a „zdarma" je v `Aktivita::cenaTextem()`.

## 3. Obsah v DB a jak orgové zadají anglický text

### Datový model

| | Jak | Pro | Proti |
|---|---|---|---|
| **A** | sloupce `*_en` ve stejné tabulce | jednoduché dotazy; obecný formulář adminu je ukáže sám | třetí jazyk = další sloupce |
| B | překladová tabulka pro každou entitu (`…_preklad(id, jazyk, …)`) | čisté, unese víc jazyků | vlastní editor všude, joiny při každém čtení |
| C | jedna společná tabulka (entita, id, pole, jazyk, hodnota) | univerzální | špatně se dotazuje, nedoporučuje se |

**Doporučení: varianta A.**

- Druhý jazyk bude realisticky jen angličtina.
- Obecný formulář adminu ([`DbFormGc`](../model/db-form-gc.php)) skládá pole podle sloupců tabulky. Sloupec s komentářem `markdown` dostane sám editor s náhledem, takže u stránek, novinek a medailonků stačí migrace a EN pole v adminu se objeví bez psaní kódu.
- Doctrine entity v `symfony/src/Entity/` (`Activity`, `Page`, `News`, `Tag`, …) je potřeba doplnit ručně, zdrojem pravdy pro schéma jsou migrace v `migrace/`.
- **Když EN text chybí:** zobrazit český s poznámkou „only in Czech". Novinky bez překladu raději v EN verzi skrýt.

### Kde se který obsah bude zadávat

| Obsah | Kde se edituje dnes | Co je potřeba |
|---|---|---|
| Stránky (`stranky.obsah`, ~66 ks) | [`editace-stranek.php`](../admin/scripts/modules/web/editace-stranek.php), obecný formulář | sloupec `obsah_en` s komentářem `markdown`, pole se objeví samo |
| Novinky a blog (`novinky.nazev`, `text`) | obecný formulář | `nazev_en`, `text_en` |
| Medailonky (`medailonky.o_sobe`) | obecný formulář | `o_sobe_en` |
| **Aktivity** (`akce_seznam.nazev_akce`, `popis_kratky`, `popis`) | vlastní editor [`editor-aktivity.xtpl`](../model/Aktivita/templates/editor-aktivity.xtpl) a hromadný import/export přes Google Sheets | viz níže |
| Typy aktivit / linie (`akce_typy`, ~10 ks) | editor nemají, mění se migrací | EN názvy migrací; stránka linie (`stranka_o`) se přeloží jako běžná stránka |
| Tagy (`sjednocene_tagy`, ~450) a jejich kategorie (~50) | [`EditorTagu`](../model/Aktivita/EditorTagu.php) | `nazev_en`; import páruje podle českého názvu, ten musí zůstat |
| E-shop (`shop_predmety.nazev`, `popis`) | jen XLSX import ([`EshopImporter`](../model/Shop/EshopImporter.php)) | řešit až v novém e-shopu z #1058, ne v legacy kódu |
| Místnosti (`lokace.nazev`, `dvere`) | — | spíš nepřekládat |

**Aktivity – co konkrétně udělat:**

- V editoru přidat EN pole vedle českých se stejným náhledem markdownu.
- Přidat EN sloupce do importu a exportu: [`ExportAktivitSloupce.php`](../admin/scripts/modules/aktivity/_Export/ExportAktivitSloupce.php) a [`ImportValuesReader.php`](../admin/scripts/modules/aktivity/_Import/Activities/ImportValuesReader.php). Import dnes neznámé sloupce odmítá.
- `popis` se kopíruje do všech instancí aktivity (`patri_pod`), totéž musí platit pro `popis_en`.
- Rozhodnout, jestli platí korektura (`probehla_korekce`) i pro EN text.

### Pomoc pro překladatele

Bez ní anglický obsah potichu zastará:

- u aktivit a stránek označení „chybí EN" nebo „EN je starší než CZ" (stačí ukládat čas změny české verze);
- přehled nepřeložených publikovaných položek;
- volitelně tlačítko „navrhnout překlad": strojový překlad jako koncept, člověk ho zkontroluje.

## 4. E-maily

- Všechny e-maily jdou přes [`GcMail`](../model/Kanaly/GcMail.php). Texty jsou dnes přímo v kódu nebo v hláškách.
- **[PR #1362](https://github.com/gamecon-cz/gamecon/pull/1362)** přesouvá 33 e-mailů do tabulky `emailova_sablona(kod, predmet, text)`. **Teď je nejlepší chvíle přidat `jazyk` do primárního klíče.** Po mergi už to bude migrace dat.
- Jazyk e-mailu se vezme z `uzivatele_hodnoty.jazyk`.
- E-mail o uvolněném místě na sledované aktivitě ([`Aktivita::poslatMailSledujicim()`](../model/Aktivita/Aktivita.php#L2342)) vybírá jen adresy. Dotaz už joinuje `uzivatele_hodnoty`, takže stačí vybrat i `jazyk`.
- Text upomínek dlužníkům, který si finanční šéf píše sám (`upominka_vlastni_zneni`), potřebuje EN variantu taky.

## 5. Cache a ostatní

- **Statický JSON programu** ([`ProgramStaticFileGenerator`](../model/Cache/ProgramStaticFileGenerator.php#L248)) se dnes generuje pro každý rok, ale jen v jedné jazykové verzi (`manifest-{rok}.json`, `aktivity-{rok}-{hash}.json`, …). Jazyk musí přibýt do názvů souborů i do manifestu a generovat se musí obě verze.
- **Cache markdownu** ([`markdown()`](../model/funkce/funkce.php#L174)) má jako klíč samotný text, ale ukládá výstup až po Smartypu. Jakmile bude typografie záviset na jazyce, musí jazyk přibýt i do klíče. Jinak stejný text v obou verzích (třeba krátký název) dostane typografii toho jazyka, ve kterém se vykreslil dřív.
- **Placeholdery** `%KLIC|datum:…%` ve stránkách formátují datum česky.
- **Registrace cizinců:**
  - [Validace](../model/uzivatel.php#L2109) počítá s českými údaji: PSČ smí mít jen číslice a mezery (britské `SW1A 1AA` neprojde) a ulice musí končit číslem popisným (`10 Downing Street` neprojde). Chybové hlášky dávají české příklady.
  - Zahraniční telefon validací projde, s `+420` a devíti číslicemi počítá jen formátování v [`Uzivatel::telefon()`](../model/uzivatel.php#L2430).
  - Stát lze vybrat jen CZ, SK nebo „jiný".
  - Statická PDF (souhlas rodičů, velikosti mikin) jsou jen česky.

## Doporučené pořadí

| Fáze | Co | Velikost |
|---|---|---|
| 0 | „Jazyk aktivity", filtr v programu a aktualizace stránky `/en` | malá, rychle přinese užitek |
| 1 | Základ: routing `/en/`, translator s `t()` a `{t:…}`, `lang`/`hreflang`, přepínač, `uzivatele_hodnoty.jazyk`, program v cache pro oba jazyky | střední |
| 2 | Odpojit logiku od českých řetězců: e-shop, linie a dny v Preactu, formát dat | střední, vyplatí se i bez angličtiny |
| 3 | Překlad rozhraní: menu, titulka, program, přihláška, finance, hlášky, chybové zprávy | velká, hlavně objemem textu |
| 4 | Obsah: sloupce `_en`, editor aktivit, import/export, přehled nepřeložených | střední |
| 5 | E-maily | malá, pokud se `jazyk` přidá do #1362 už teď |

**Průběžně:** každá nová featura musí přidat texty v obou jazycích. U kódu to vynutí test katalogu, u obsahu jen přehled nepřeložených položek.
