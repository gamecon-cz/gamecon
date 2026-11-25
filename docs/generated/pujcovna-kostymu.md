TL;DR: Půjčovna larpových kostýmů (Trello 1510): veřejný katalog na `/kostymy`, rezervace kostýmu na aktivitu, na kterou je účastník přihlášený, výdej a vrácení v adminu (Půjčovna kostýmů, právo 115). Rezervace stojí v databázi na přihlášce a zmizí s ní, ať ji smaže kterákoli cesta.

## Vstupní body

- `model/Kostymy/PujcovnaKostymu.php`: rezervace (`rezervuj`, `zrus`), volné kostýmy pro aktivitu (`volneKostymy`), aktivity, na které jde rezervovat (`aktivityKRezervaci`), data pro program (`kostymyVProgramu`) a prezenci (`kostymyUcastnikuAktivity`). Podmínka překryvu je jeden SQL fragment `KOLIZE_KOSTYMU` pro kontrolu i výpis.
- `VydejKostymu` (výdej, vrácení), `PrehledyPujcovny` (výdejní list, nevrácené, konflikty, rezervace kostýmů mimo provoz, hledání účastníka), `KatalogKostymu` (kostýmy a fotky), `NastaveniPujcovny`, `EmailyPujcovny`, `FormatPujcovny`, vše v `model/Kostymy/`.
- `Aktivita::odhlas()`: přečte rezervaci před smazáním přihlášky a na konci pošle e-mail (`PujcovnaKostymu::oznamUvolneni`).
- `UzivatelSlucovani::smazPrihlaskyStarehoNaAktivityNoveho()`, viz Gotchas.
- Admin `admin/scripts/modules/pujcovna/`: Katalog, Výdej a vrácení (`ucastnik.php`), Výdejní list, Nevrácené, Konflikty. Ikona klasického vzhledu `admin/files/design/pujcovna.avif` (glyf `shirt` z Font Awesome).
- Web `web/moduly/kostymy.php` + `web/sablony/blackarrow/kostymy.xtpl` + `web/soubory/blackarrow/kostymy/kostymy.less`: `/kostymy` (filtr, volné na vlastní aktivitu `?aktivita=`), `/kostymy/<id>` (detail s rezervací), `/kostymy/moje`. Odkazy v menu webu (`MenuWebu`, `menu.xtpl`, menu je v kódu, ne ve správě stránek): Informace → Půjčovna kostýmů a v menu přihlášeného Moje kostýmy.
- Program: pole `kostym` v `web/moduly/api/aktivityUzivatel.php` a `aktivitaAkce.php`, komponenta `ui/src/pages/program/components/KostymAktivity.tsx` (náhled), řádek v `ProgramTabulkaBuňka.tsx`. Prezence: `OnlinePrezenceUcastnikHtml::nazevKostymu()`.
- Migrace `migrace/2026-11-12-151618_pujcovna-kostymu.php` (tabulky, nastavení, právo, role), `…-153137_pujcovna-kostymu-emaily.php`.
- Testy `tests/Kostymy/*`, přes Apache `tests/web/KatalogKostymuNaWebuTest.php`.

## Datový model

- `kostym`: název, popis, velikost (`VelikostKostymuEnum`, pevný seznam kvůli filtru), míry (volný text), střih, kategorie (volný text s nabídkou použitých), stav (`k_dispozici` / `v_oprave` / `vyrazeny`), interní poznámka, pořadí. Smazat jde jen kostým bez rezervací a výdejů, jinak se vyřadí, a bez fotky, na kterou odkazuje text webu (kontroluje se u všech fotek předem, soubory se mažou až po řádku kostýmu).
- `kostym_foto`: soubor v `web/soubory/systemove/kostymy/<id kostýmu>-<náhodný kód>.<přípona>`, pořadí (první je hlavní). Řádky mizí kaskádou s kostýmem, soubory maže kód.
- `kostym_rezervace`: kostým, `id_akce`, `id_uzivatele`, kdo a kdy rezervoval. `UNIQUE (id_akce, id_uzivatele)` = jeden kostým na osobu a aktivitu. Cizí klíč `(id_akce, id_uzivatele)` → `akce_prihlaseni` s `ON DELETE CASCADE ON UPDATE CASCADE`. Čas se nekopíruje, bere se z aktivity, takže přesun aktivity posune i rezervaci.
- `kostym_vydej`: kdo, kdy vydal a přijal, stav při vrácení, poznámka. Na rezervaci `ON DELETE SET NULL` a navíc vlastní `id_akce`, z jejíhož konce se počítá termín vrácení i po zániku rezervace.
- Nastavení skupiny „Půjčovna kostýmů“ (všechny ročníky, výchozí hodnoty v `SystemoveNastaveni::dejVychoziHodnoty()`, bez nich by odškrtnutí „vlastní“ definovalo konstantu prázdnou a vypnulo půjčovnu): `KOSTYMY_KATALOG_ZVEREJNEN`, `KOSTYMY_TYPY_AKTIVIT` (ID oddělená čárkou), `KOSTYMY_LIMIT_NA_OSOBU`, `KOSTYMY_CAS_NA_VYMENU_MINUT`, `KOSTYMY_VYDEJ_MINUT_PRED_ZACATKEM`, `KOSTYMY_VRACENI_MINUT_PO_KONCI`, `KOSTYMY_UZAVERKA_HODIN_PRED_ZACATKEM`.
- Právo 115 `ADMINISTRACE_PUJCOVNA_KOSTYMU` (je v `PristupDoAdminu::PRAVA`), trvalá role 31 „Správce půjčovny kostýmů“ (kategorie běžná).

## Pravidla (záměr)

- Katalog projde kdokoli; rezervovat smí jen přihlášený, sám sobě, na aktivitu, na kterou má přihlášku ve stavu „přihlášen“ (ne sledující, ne náhradník). Aktivita má začátek i konec, ještě nezačala a je povoleného typu (výchozí jen larp).
- Kostým nejde mít na dvě aktivity, jejichž intervaly `[začátek, konec + čas na výměnu)` se protínají (výchozí výměna 60 min, nová rezervace od 15:00 projde po jiné do 14:00, od 14:59 ne). Blokuje i vydaný a nevrácený kostým, jehož rezervace mezitím zanikla, a kostým, jehož aktivita už neexistuje, blokuje vždy.
- Jeden kostým na osobu a aktivitu, limit na osobu za ročník (0 = bez limitu). Zrušit nebo vyměnit jde do uzávěrky (výchozí 2 h před začátkem), novou rezervaci do začátku.
- Správce půjčovny v adminu rezervuje a mění za kohokoli přihlášeného; neplatí pro něj uzávěrka, limit ani zveřejnění katalogu, ostatní pravidla ano.
- Výdej nejdřív 60 min před začátkem a do konce aktivity, vrácení do 60 min po konci; potom je kostým mezi nevrácenými. Poškozený kostým se při vrácení přepne do opravy.
- Odhlášení z aktivity rezervaci uvolní (cizí klíč) a `odhlas()` pošle e-mail; e-mail dostanou i účastníci smazané aktivity a odhlášení z GameConu. Kde se odhlašuje v transakci (smazání aktivity, import účastníků), odejdou e-maily až po commitu, a když transakce spadne, žádný (`PujcovnaKostymu::sOznamenimUvolneniPoZmene()`); další takové místo ho potřebuje taky. E-mail půjčovny, který nejde odeslat, změnu neshodí ani nevrátí, skončí ve výjimkovači. „Nedorazil“ v prezenci, zrušený náhradník, rozpuštění týmu a slučování uživatelů rezervaci uvolní bez e-mailu. Potvrzení „dorazil“ přihlášku jen přepíše a rezervace zůstane.
- Přesun aktivity se kvůli kostýmu neblokuje; vzniklý překryv ukáže správci stránka Konflikty.
- Zdarma, bez kauce, bez napojení na finance.
- Nezveřejněný katalog je 404 pro všechny kromě správce půjčovny (vidí ho s upozorněním, rezervovat nejde, detail kostýmu proto formulář rezervace nemá). `/kostymy/moje` vidí každý přihlášený i před zveřejněním, jen bez odkazů do katalogu a bez výměny: správce rezervuje za účastníky i předtím a e-mail i program na ni odkazují. Odkazy v menu webu a odkaz do katalogu v programu se ukážou až se zveřejněným katalogem. Do sitemapy se dostane až zveřejněný, `/kostymy/moje` má v `robots.txt` `Disallow`. Filtry nejsou součástí kanonické adresy.
- Po uzávěrce detail kostýmu ani filtr katalogu nenabídne aktivitu, na kterou má účastník jiný kostým (výměna by neprošla). Rezervaci na aktivitu, která přišla o začátek, zruší jen správce; vyměnit ji nejde (rezervace potřebuje začátek i konec).
- Výdej najde (a stránka účastníka ukáže) jen toho, kdo je letos přihlášený na aktivitu povoleného typu nebo má kostým u sebe, ne kohokoli (vydala by e-mail a telefon).

## Gotchas

- **Logika běží na legacy spojení** (`dbQuery`, `SELECT … FOR UPDATE` na řádku kostýmu). Rezervace stojí na `akce_prihlaseni`, kterou legacy kód mění ve vlastních transakcích, a Doctrine spojení by na její řádky čekalo (CLAUDE.md, dvě spojení). Entity `App\Entity\Costume*` popisují schéma.
- **Složený cizí klíč na přihlášku Doctrine neumí** (asociace jen na primární klíč), entita má `id_akce` a `id_uzivatele` holé a `make migrations-diff` by klíč zrušil, proto je ve `symfony/migrations/ignored.sql`.
- **Rezervace nemá vlastní cizí klíč na `uzivatele_hodnoty`**, schválně. Slučování uživatelů i anonymizace projdou tabulky s klíčem na uživatele v libovolném pořadí; kdyby rezervace přišla na řadu dřív než přihláška, slučování by ji smazalo a anonymizace posunula ID dvakrát. Takhle se přenese kaskádou z přihlášky.
- **MariaDB provede kaskádu dřív, než zjistí kolizi unikátního klíče rodiče.** `UPDATE IGNORE akce_prihlaseni` při slučování dvou účtů na stejné aktivitě, kde oba mají rezervaci, skončil duplicitou v `kostym_rezervace`, kterou `IGNORE` nepromine. Proto `UzivatelSlucovani` kolidující přihlášky starého účtu smaže předem. Rezervace starého účtu pak zmizí i tehdy, když nový účet na aktivitě rezervaci nemá.
- `dbQueryAssemble()` posune číslování parametrů o jedno, když pole má klíč 0 a dotaz `$0` nepoužívá (`volneKostymy` proto klíč 0 maže).
- Kompilátor LESS na webu počítá `1 / -1` jako dělení: `grid-column: ~'1 / -1'`.
- Konstanty nastavení nejde v testu předefinovat, testy si skládají `NastaveniPujcovny` samy a „teď“ dávají přes `SystemoveNastaveni::zGlobals(ROCNIK, new DateTimeImmutableStrict(…))`.
- **Zámky a deadlocky:** rezervace zamyká kostým, na který rezervuje, pak přihlášku a nakonec stávající rezervaci podle primárního klíče. Zrušení zamyká přihlášku a pak rezervaci, odhlášení maže přihlášku a rezervaci smaže kaskáda: všechny drží zámek přihlášky dřív než rezervace. Kdyby zrušení zamklo jen rezervaci, s odhlášením by se zablokovalo. Rezervaci hledá podle aktivity a účastníka bez zámku: `FOR UPDATE` nad řádkem, který neexistuje, vezme v unikátním indexu gap lock a dvě první rezervace by na sebe čekaly při INSERT. Duplicita nehrozí, téhož účastníka na téže aktivitě drží zámek přihlášky. Kostým stávající rezervace se nezamyká (název se dočítá zvlášť), jinak by dvě výměny opačným směrem (K1 → K2 a K2 → K1) skončily deadlockem. Snímek transakce tak vzniká hledáním rezervace: až po zámku přihlášky, ale před zámkem rezervace, na který může čekat výdej. Kontrola vydaného kostýmu proto čte se zámkem (`LOCK IN SHARE MODE`), aby výdej viděla.
- Limit na osobu nic nezamyká: dvě souběžné rezervace téhož člověka na různé aktivity ho můžou o jednu překročit.
- Program ukazuje jen dny GameConu, aktivita mimo ně (třeba testovací) v programu nebude, i když `aktivityUzivatel` pole `kostym` pošle. Změna rezervace mimo program se v něm projeví po načtení stránky, v online prezenci také.
- Fotky leží pod `web/soubory/systemove/*/*`, které už chrání nasazení (`getRsyncProtectedPaths()`) i `.gitignore`.
- `kostym_vydej` je v `AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY` (poznámky o lidech), `kostym.interni_poznamka` se při anonymizaci vyprázdní.

## Vzhled

- (záměr) Web je stavěný hlavně na telefon: do 600 px má katalog dva sloupce a rezervace i fotka detailu jdou od kraje ke kraji, do 800 px je detail v jednom sloupci. Fotky jsou na výšku 3:4 (kostým na ramínku), náhledy dělá `FotkaKostymu::getUrlNahledu()` s ořezem; kostým bez fotky má v CSS zástupné ramínko. Celé fotky ukáže baguetteBox jako u přihlášky. `og:image` detailu je náhled v JPEG (`FotkaKostymu::getUrlNahleduProSocialniSite()`): AVIF sociální sítě nezobrazí a náhled z cache `Info` nepřevede.
- Aktivita k rezervaci je přepínač s kartou, ne `<select>`: na telefonu select uřízl dovětek „vymění …“. Předvybraná je aktivita z `?aktivita=` (přechod z katalogu nebo programu), jinak první volná.
- Admin Výdej a vrácení (`ucastnik.xtpl`): v moderním vzhledu pod 640 px jsou řádky tabulek `.pujcovna-karty` karty s popisky z `data-popisek` (u výdejního pultu z telefonu). Pravidla jsou pod `.vzhled-moderni`, klasický vzhled se nemění. Tlačítka zůstávají bez třídy, moderní vzhled stylizuje jen `button` bez třídy (a pár vyjmenovaných).

## Mimo rozsah v1 (záměr) a kudy na ně

- Poplatek přes GC finance a evidence kauce: nové sloupce u rezervace nebo výdeje, nic v modelu je neblokuje.
- Rezervace vypravěčem za hráče: `PujcovnaKostymu::rezervuj()` už rozlišuje účastníka a toho, kdo rezervuje (`rezervoval`), chybí jen pravidlo, kdo smí.
- Vyhrazení kostýmů pro konkrétní larp: další podmínka do `KOLIZE_KOSTYMU` a `volneKostymy()`.
- Hromadný import z tabulky: `KatalogKostymu::uloz()` přijímá pole hodnot formuláře.
- Štítky s QR kódem: `kostym.id` je trvalé a detail má adresu `/kostymy/<id>`.

## Otevřené otázky

Otevřené otázky karty nikdo nezodpověděl, platí výchozí rozhodnutí výše (záměr). Až se rozhodne jinak, většina z nich je v nastavení.
