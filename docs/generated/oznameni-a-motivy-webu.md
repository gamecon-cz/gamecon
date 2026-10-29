# Oznámení na webu, barvy ročníku a speciální motivy

TL;DR: z adminu (Web → Oznámení na webu, Web → Barvy a motivy webu, právo 105) jde dát na všechny stránky webu lištu s oznámením, přebarvit moderní vzhled pro ročník (návrh → náhled → zveřejnění) a naplánovat speciální motiv napsaný v kódu (apríl).

## Vstupní body

- Admin: `admin/scripts/modules/web/oznameni-na-webu.php`, `admin/scripts/modules/web/barvy-a-motivy-webu.php`.
- Web: `web/index.php` zpracuje `?skrytOznameni=` a `?nahledMotivu=` (spolu s `?vzhled=` jedním přesměrováním), přiřadí kostře `oznameni`, `motivWebu`, `barvaProhlizece` a blok `nahledMotivu`.
- Oznámení: `model/Web/OznameniNaWebu/` (`OznameniNaWebuRepository`, `SkrytaOznameniNaWebu` = cookie zavřených, `OznameniNaWebuHtml`), šablony `web/sablony/{blackarrow,moderni}/oznameni.xtpl`, styly `web/soubory/blackarrow/oznameni/oznameni.less` (klasický) a sekce Oznámení v `web/soubory/moderni/moderni.css`.
- Barvy a motivy: `model/Web/Motiv/` (`BarvaWebuEnum`, `BarvyWebu`, `BarvyWebuRepository`, `KontrastBarevWebu`, `MotivWebuEnum`, `NaplanovaneMotivyWebuRepository`, `NahledMotivuWebu`, `MotivWebu` = co platí pro request), motivy `web/soubory/moderni/motivy/<motiv>.css`.
- Společné: `model/Web/StavZobrazeniEnum.php` (naplánováno / na webu / skončilo), `model/Web/DatumACasZFormulare.php` (`datetime-local`).
- Tabulky z `migrace/2026-10-29-102218_oznameni-a-motivy-webu.php`: `oznameni_na_webu`, `barvy_webu`, `naplanovany_motiv_webu`.

## Pravidla

- (záměr) Oznámení je pro všechny návštěvníky, hlavně během festivalu (přesun, zpoždění, zrušení), proto je v klasickém i moderním vzhledu.
- (záměr) Barvy a motivy jsou jen pro moderní vzhled, klasický se časem odbourá.
- (záměr) Barvy ročníku nastavuje grafik v adminu. Speciální motiv umí víc než barvy, proto je v kódu a admin ho jen plánuje.
- Oznámení bez začátku platí hned, bez konce do ukončení. Pořadí: naléhavé, upozornění, informace, v rámci typu nejnovější. Naléhavé nejde zavřít.
- Zavření je obyčejný odkaz `?skrytOznameni=<id>-<md5(typ + text)>`, server klíč uloží do cookie `skrytaOznameni` (posledních 20) a přesměruje bez parametru: funguje bez JS a zavřené oznámení neprobliká. Po změně textu nebo typu se oznámení ukáže znovu.
- `barvy_webu` má nejvýš dva řádky (`navrh`, `zverejneno`), JSON drží jen barvy odlišné od výchozích, takže nezměněná barva sleduje `moderni.css`. Výchozí hodnoty v `BarvaWebuEnum` jsou kopie `:root` z `moderni.css`, shodu hlídá `BarvaWebuEnumTest`.
- Barvy jdou do stránky jako `<style>:root{…}</style>` za `moderni.css`. Hodnota projde jen jako `#rrggbb` (`BarvyWebu::normalizujHodnotu()`), nic jiného se do CSS nedostane.
- Kontrast (WCAG 4,5:1) admin jen hlásí, uložit a zveřejnit jde i tak. Hlídané dvojice jsou v `KontrastBarevWebu::dvojice()`.
- Překrývající se motivy: platí ten, který začal později. Motiv, který kód nezná, se přeskočí a platí ten pod ním.
- Náhled `?nahledMotivu=navrh-barev|<motiv>|konec` jen pro přihlášené s právem 105 (jinak chybová hláška), pamatuje se v cookie do zavření prohlížeče. Odkaz z adminu přidává `?vzhled=moderni`. Náhled návrhu ukazuje barvy bez naplánovaného motivu.
- Čas bere z `SystemoveNastaveni::ted()`, dá se tedy posunout jako u ostatních termínů.

## Jak přidat motiv

1. Nový `case` v `MotivWebuEnum` s názvem a popisem pro admin.
2. Soubor `web/soubory/moderni/motivy/<value>.css` (jeho existenci hlídá `MotivWebuEnumTest`). Načítá se za barvami ročníku, takže je může přepsat.

## Gotchas

- Blok `nahledMotivu` je v obou kostrách, parsuje se bez ohledu na vzhled. V klasické kostře lišta jen řekne, že náhled jde vidět v moderním vzhledu.
- Stránky skládané Symfony (`ObnovaHeslaController`) si kostru skládají samy a lištu oznámení nemají.
- Klasická titulka dává menu `position: fixed` (hack v `sablony/blackarrow/titulka.xtpl`), které by lištu pod sebou překrylo. S lištou je menu `sticky` jako jinde (`.menu:has(~ .oznameniWebu)`), odsazení o výšku menu by nestačilo, menu má podle šířky a přihlášení 70 až 120 px.
- „Uložit a prohlédnout na webu“ ukládá v nové záložce (`formtarget="_blank"`), původní záložka adminu se proto po návratu z náhledu načte znovu, jinak by v ní chybělo „Zveřejnit návrh“.
- Barvy napsané v `moderni.css` natvrdo se s ročníkem nemění. Záře v úvodu titulky a stín červeného tlačítka jsou proto přes `color-mix()` z proměnných, bílý text na hlavní a tmavé barvě zůstává bílý (proto ho hlídá kontrola kontrastu). Totéž platí pro obrázky: červený obrázek by zůstal červený.
- Tah štětcem pod nadpisem titulky (`.mw-zvyraznit`) je proto `smouha.png` jako maska pseudoprvku v barvě `--mw-cervena`. Pseudoprvek se na řádky nerozdělí, takže tah má každé slovo (`.mw-zvyraznit-slovo`). Jeden tah přes celou frázi je jen tehdy, když ji `moderni.js` najde na jednom řádku (`getClientRects()`, třída `mw-zvyraznit-na-radku`). Měří se, protože zalomení záleží i na písmu motivu: práh v `em` počítaný pro Gilroy by s Comic Sans aprílu nechal tah jen pod posledním slovem.
- Motiv natáčí vlastností `rotate`, ne `transform`, ta by se prala s animacemi karet (`mw-odhalit`, hover).
- V inline JS admin šablony XTemplate nahradí `{identifikator}` bez mezer; `{6}` v regulárním výrazu nevadí, protože začíná číslicí.
