# Oznámení na webu, barvy ročníku a speciální motivy

TL;DR: z adminu (Web → Oznámení na webu, Web → Barvy a motivy webu, právo 105) jde dát na všechny stránky webu lištu s oznámením, přebarvit web pro ročník (návrh → náhled → zveřejnění; moderní vzhled celý, klasický z části) a naplánovat speciální motiv napsaný v kódu (apríl).

## Vstupní body

- Admin: `admin/scripts/modules/web/oznameni-na-webu.php`, `admin/scripts/modules/web/barvy-a-motivy-webu.php`.
- Web: `web/index.php` zpracuje `?skrytOznameni=` a `?nahledMotivu=` (spolu s `?vzhled=` jedním přesměrováním), přiřadí kostře `oznameni`, `motivWebu`, `barvaProhlizece` a blok `nahledMotivu`.
- Oznámení: `model/Web/OznameniNaWebu/` (`OznameniNaWebuRepository`, `SkrytaOznameniNaWebu` = cookie zavřených, `OznameniNaWebuHtml`), šablony `web/sablony/{blackarrow,moderni}/oznameni.xtpl`, styly `web/soubory/blackarrow/oznameni/oznameni.less` (klasický) a sekce Oznámení v `web/soubory/moderni/moderni.css`.
- Barvy a motivy: `model/Web/Motiv/` (`BarvaWebuEnum`, `BarvyWebu`, `BarvyWebuRepository`, `KontrastBarevWebu`, `MotivWebuEnum`, `NaplanovaneMotivyWebuRepository`, `NahledMotivuWebu`, `MotivWebu` = co platí pro request), motivy `web/soubory/moderni/motivy/<motiv>.css`.
- Společné: `model/Web/StavZobrazeniEnum.php` (naplánováno / na webu / skončilo), `model/Web/DatumACasZFormulare.php` (`datetime-local`).
- Tabulky z `migrace/2026-10-29-102218_oznameni-a-motivy-webu.php`: `oznameni_na_webu`, `barvy_webu`, `naplanovany_motiv_webu`.

## Pravidla

- (záměr) Oznámení je pro všechny návštěvníky, hlavně během festivalu (přesun, zpoždění, zrušení), proto je v klasickém i moderním vzhledu.
- (záměr) Barvy ročníku platí pro oba vzhledy, motivy jen pro moderní (jsou psané pro jeho kostru). Klasický vzhled se sice časem odbourá, ale dokud je výchozí, mají ho barvy ročníku přebarvit taky.
- (záměr) Klasický vzhled přebarví jen hlavní, tmavší hlavní, tmavou, doplňkovou a text (`BarvaWebuEnum::getCssPromennaKlasickehoVzhledu()`). Krémovou si nechá: má na ní nakreslené ilustrace (úvod titulky, hlavička programu, pozadí „Co na GameConu zažiješ“, ikony karet), s jinou by se rozpadl na krémové a jiné plochy. Bílou taky (obrázky s bílým pozadím), šedé nejsou barvy značky. Admin u každé barvy píše, kde je v kterém vzhledu.
- (záměr) Barvy ročníku nastavuje grafik v adminu. Speciální motiv umí víc než barvy, proto je v kódu a admin ho jen plánuje.
- Oznámení bez začátku platí hned, bez konce do ukončení. Pořadí: naléhavé, upozornění, informace, v rámci typu nejnovější. Naléhavé nejde zavřít.
- Zavření je obyčejný odkaz `?skrytOznameni=<id>-<md5(typ + text)>`, server klíč uloží do cookie `skrytaOznameni` (posledních 20) a přesměruje bez parametru: funguje bez JS a zavřené oznámení neprobliká. Po změně textu nebo typu se oznámení ukáže znovu.
- `barvy_webu` má nejvýš dva řádky (`navrh`, `zverejneno`), JSON drží jen barvy odlišné od výchozích, takže nezměněná barva sleduje `moderni.css`. Výchozí hodnoty v `BarvaWebuEnum` jsou kopie `:root` z `moderni.css`, shodu hlídá `BarvaWebuEnumTest`.
- Klasický vzhled má vlastní proměnné `--gc-<hodnota BarvaWebuEnum>` s vlastními výchozími hodnotami v `:root` `web/soubory/blackarrow/_spolecne/_zaklad.less` (doplňková `#FDC689` a tmavší hlavní `#CE232D` jsou o odstín jiné než moderní). Nezměněná barva tak drží každému vzhledu tu jeho, změněná platí pro oba. Že každá proměnná `--gc-` jde z adminu změnit a naopak, hlídá `BarvaWebuEnumTest`.
- Barvy jdou do stránky jako `<style>:root{…}</style>` (`--mw-…` i `--gc-…`) za styly vzhledu, v obou kostrách proměnná `{motivWebu}`. Hodnota projde jen jako `#rrggbb` (`BarvyWebu::normalizujHodnotu()`), nic jiného se do CSS nedostane.
- Kontrast (WCAG 4,5:1) admin jen hlásí, uložit a zveřejnit jde i tak. Hlídané dvojice jsou v `KontrastBarevWebu::dvojice()`.
- Překrývající se motivy: platí ten, který začal později. Motiv, který kód nezná, se přeskočí a platí ten pod ním.
- Náhled `?nahledMotivu=navrh-barev|<motiv>|konec` jen pro přihlášené s právem 105 (jinak chybová hláška), pamatuje se v cookie do zavření prohlížeče. Odkaz z adminu na náhled motivu přidává `?vzhled=moderni`, náhled barev nechá vzhled, který má grafik zapnutý (lišta náhledu v obou kostrách umí přepnout na druhý). Náhled návrhu ukazuje barvy bez naplánovaného motivu.
- Čas bere z `SystemoveNastaveni::ted()`, dá se tedy posunout jako u ostatních termínů.

## Jak přidat motiv

1. Nový `case` v `MotivWebuEnum` s názvem a popisem pro admin.
2. Soubor `web/soubory/moderni/motivy/<value>.css` (jeho existenci hlídá `MotivWebuEnumTest`). Načítá se za barvami ročníku, takže je může přepsat.

## Gotchas

- Blok `nahledMotivu` je v obou kostrách, parsuje se bez ohledu na vzhled. V klasické kostře lišta u náhledu motivu řekne, že motiv je jen v moderním vzhledu (`poznamkaNahleduMotivu`).
- Klasický vzhled má značkové barvy v LESS (`perfectcache`) i v UI (Preact program a filtr aktivit, `ui/src`). UI se načítá i v adminu, kde proměnné `--gc-` nejsou, proto UI a soubory, které si přibaluje (`program-nahled.less`, `program-trida.css`), píšou `var(--gc-cervena, #E22630)` se zálohou. `:root` s výchozími hodnotami do UI nepatří: načítá se až za barvami ročníku a přebil by je.
- Odvozené odstíny klasického vzhledu (tmavá po najetí myší `#30323E`, světle oranžový štítek v náhledu `#F9D6B0`) jsou `color-mix()` 50 : 50 proměnné s barvou spočítanou tak, aby s výchozí barvou vyšel přesně původní odstín.
- Červená chyb a tlačítka „Odhlásit se z GameConu“ v `formular.less` zůstává červená schválně, chyba a nevratný krok mají být červené v každém ročníku.
- Obrázky v barvách značky jsou v klasickém vzhledu masky pseudoprvku v barvě proměnné (vlna nad patičkou, šmouha pod datem na titulce, šmouhy u karet a aktivit, křížky, vlnky v pásu na titulce). Oranžové šmouhy byly jpg na bílé, teď jsou to `smouha.avif` s alfou; jpg zakrýval bílým obdélníkem vlnky vedle seznamu aktivit, proto má `.aktivity_aktivity` pod šmouhou bílý obdélník jako pozadí. Pás s vlnkami na titulce má obal `.popisboxObal`: maska potřebuje pozicovaný prvek a `.popisbox` jím být nemůže, jeho `overflow: hidden` (kvůli animaci textu) by uřízl bublinu, která přečnívá nahoru.
- Nepřebarví se ilustrace (úvod titulky s červenými tahy a oranžovou skvrnou, hlavička programu), korálově červené kresby `#fc4349` (vlnky u aktivit, pily u čísel, bubliny; jiný odstín než hlavní barva, maska by změnila i výchozí vzhled) a tmavé ikony.
- Klasický vzhled s výchozími barvami se nemá změnit: ověřeno pixelovým porovnáním 17 stránek na šířce 1400, 900 a 375 px proti `main`, liší se jen vyhlazení okraje masek (vlna patičky o 2 z 255) a šmouhy (šum jpg, nejvýš 34 z 255). Převod masek a fotek titulky na AVIF to viditelně nezměnil: klasická titulka na šířce 1440 px se před ním a po něm liší jen v přehrávači vloženého videa a v dev liště, jinde o 60 dB PSNR.
- Stránky skládané Symfony (`ObnovaHeslaController`) si kostru skládají samy a lištu oznámení nemají. Zveřejněné barvy ročníku dostanou (`{motivWebu}`), náhled grafika ne.
- Klasická titulka dává menu `position: fixed` (hack v `sablony/blackarrow/titulka.xtpl`), které by lištu pod sebou překrylo. S lištou je menu `sticky` jako jinde (`.menu:has(~ .oznameniWebu)`), odsazení o výšku menu by nestačilo, menu má podle šířky a přihlášení 70 až 120 px.
- „Uložit a prohlédnout na webu“ ukládá v nové záložce (`formtarget="_blank"`), původní záložka adminu se proto po návratu z náhledu načte znovu, jinak by v ní chybělo „Zveřejnit návrh“.
- Barvy napsané v `moderni.css` natvrdo se s ročníkem nemění. Záře v úvodu titulky a stín červeného tlačítka jsou proto přes `color-mix()` z proměnných, bílý text na hlavní a tmavé barvě zůstává bílý (proto ho hlídá kontrola kontrastu). Totéž platí pro obrázky: červený obrázek by zůstal červený.
- Tah štětcem pod nadpisem titulky (`.mw-zvyraznit`) je proto `smouha.avif` jako maska pseudoprvku v barvě `--mw-cervena`. Pseudoprvek se na řádky nerozdělí, takže tah má každé slovo (`.mw-zvyraznit-slovo`). Jeden tah přes celou frázi je jen tehdy, když ji `moderni.js` najde na jednom řádku (`getClientRects()`, třída `mw-zvyraznit-na-radku`). Měří se, protože zalomení záleží i na písmu motivu: práh v `em` počítaný pro Gilroy by s Comic Sans aprílu nechal tah jen pod posledním slovem.
- Motiv natáčí vlastností `rotate`, ne `transform`, ta by se prala s animacemi karet (`mw-odhalit`, hover).
- V inline JS admin šablony XTemplate nahradí `{identifikator}` bez mezer; `{6}` v regulárním výrazu nevadí, protože začíná číslicí.
