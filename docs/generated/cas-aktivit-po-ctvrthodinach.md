TL;DR: Aktivity začínají a končí po čtvrthodinách (dřív jen celé hodiny). Dokument popisuje, kde všude na tom záleží (editor, tabulka programu, import, prezence, peníze), proč je tabulka programu postavená přes `<colgroup>` a jak se počítají necelé hodiny u bonusu a brigádníků.

## Jediný zdroj kroku

- PHP: `model/Aktivita/Program.php::KROK_CASU_AKTIVITY_MINUT` (15).
- UI: `ui/src/pages/program/components/tabulka/seskupování.ts::SLOUPCŮ_ZA_HODINU` (4). Při změně kroku je potřeba změnit obojí.
- CSS kroku nezná, šířky počítá z `--hodin` (zlomek hodiny), který nastavuje UI.

## Editor aktivity (admin)

- Nabídka časů: `Program::seznamCasuZacatku()`, hodnoty ve formuláři jsou `"14:15"`, konec o půlnoci `"24:00"`.
- Parsování: `Program::minutyZCasuFormulare()` – bere i starý tvar jen s hodinou (`"14"`, `"24"`), který dál posílají testy. Čas mimo čtvrthodinu vrací `null` = backend ho neuloží.
- Den po půlnoci: `Program::denAktivityDleZacatku()` / `denAktivityDleKonce()` porovnávají minuty s `PROGRAM_ZACATEK * 60`. Stejné pravidlo opakuje JS v `editor-aktivity.xtpl::dejMinutyOdZacatkuDne` (délka pro odměnu brigádníka).
- Starší aktivita s časem mimo čtvrthodiny se v nabídce zaokrouhlí dolů (jako dřív na celé hodiny); uložením se čas posune. Zaokrouhlení je jen v `Program::minutaZacatkuVEditoru()` / `minutaKonceVEditoru()`.
- Hlídání změn u aktivity s přihlášenými (`ZmenyAktivitySPrihlasenymi::zmenenCas`) porovnává původní čas **zaokrouhlený stejně jako editor** (jinak by uložení beze změny pořád hlásilo změnu času a aktivita by nešla uložit), půlnoc `0:00` = `24:00`.
- Den „?“ posílá editor jako `0` – `empty()`, ne `isset()`; s vyplněným časem se aktivita uloží bez času s hláškou „Čas byl zrušen“.
- Gotcha v `editor-aktivity.xtpl`: XTemplate se kompiluje do PHP a PHP za `?>` spolkne odřádkování. Proměnná `{neco}` na konci řádku JS se tak slepí s dalším řádkem (`const a = 480const b = …` → syntaktická chyba, nefunguje celý skript ceny brigády). Proměnnou dávej do uvozovek (`Number.parseInt('{neco}', 10)`) nebo za ni středník.

## Tabulka programu (Preact)

- `seskupování.ts::rozložŘádekDoSloupců` – sloupec = čtvrthodina od `PROGRAM_ZACATEK`; vrací odsazení, šířku buněk a zbytek řádku. Začátek se zarovná dolů, konec nahoru (pojistka pro „necelé“ časy).
- Řádky bez překryvu (`časyDoŘádkůBezPřekryvu`) se počítají ze **zarovnaných** časů, jinak by dvě aktivity sdílející čtvrthodinu skončily v jednom řádku a rozbily colspany.
- Hlavička: `<th colSpan=4>` na hodinu, čárkované oddělovače hodin zůstávají na `th::before`.
- Buňka kratší než hodina dostane třídu `program_kratkaAktivita` (menší padding, název v `title`, obsazenost a místnost oříznuté na šířku buňky; přihlašovátko přetéká, aby šlo kliknout – `div` buňky nesmí mít `overflow: hidden` kvůli bublinám `.hint`).

### Gotcha: šířky sloupců (`web/soubory/blackarrow/program/program-trida.css`)

- Původní layout spoléhal na `td { min-width/max-width: 100px }`. Chrome `max-width` dodrží jen u buňky přes **jeden** sloupec; u colspan buněk šířku rozdělí mezi sloupce nerovnoměrně (změřeno: čtvrthodiny 0 px i 70 px v jedné hodině).
- Proto: `<colgroup>` s pevnou šířkou každé čtvrthodiny (`col.program_ctvrthodina`), `div` uvnitř buňky má pevnou šířku podle `--hodin` (delší název nerozšíří sloupec) a `td` min/max-width podle `--hodin` (bez něj 1 hodina – název linie, nadpis dne).
- Pravidlo pro úzký sloupec názvu linie (`max-width: 1500px`, 40 px) musí cílit na `td[rowspan]`, ne `td:first-child` – v dalších řádcích linie je první buňkou aktivita nebo mezera a 40 px by roztáhlo čtvrthodinu.
- `--mezera-sloupcu` musí sedět s `border-spacing` (10 px, na mobilu 6 px), `--sirka-hodiny` s `min-width` hlavičky (100 px i na mobilu – hlavička tam vždycky přebíjela 70 px u `td`).
- Ověřeno v headless Chromiu: celohodinové aktivity mají stejné pozice a šířky jako před změnou (desktop i ≤ 800 px). E2E na lokální instanci (web den/kompaktní/po místnostech, admin všechny dny a po místnostech s prázdnými místnostmi, mobil): buňky přesně na čtvrthodinách, každý řádek má stejný počet sloupců jako hlavička.
- Vybuildované bundly `web/soubory/ui/*` a `admin/files/ui/*` jsou v gitu, CI je nestaví – po změně UI je potřeba `yarn build` v `ui/`.

## Import a export (Google Sheets)

- `ImportValuesSanitizer::createDateTimeFromRangeBorder` bere `14:15` i `14:15:00` (Sheets vrací čas se sekundami), čas mimo čtvrthodiny odmítne s varováním a čas aktivity vynechá.
- Konec přesně v `PROGRAM_KONEC` (6:00) import bere, mimo program je až konec po něm (dřív se kontrolovala jen hodina a 6:00 neprošlo).
- Import neumí aktivitu přes půlnoc kromě konce `24` – den je den začátku a začátek po konci se odmítne.
- Export (`ActivitiesExporter`) píše `G:i`, celé hodiny bez `:00`.

## Prezence

- Výběr času (`admin/scripts/modules/prezence/_casy.php`) nabízí každý různý začátek s minutami (`Aktivita::zacatkyAktivit` klíčuje podle `YmdHi`). Filtr hledá přesný začátek (`od = do`), takže hodinové seskupení by aktivitu od 14:15 schovalo.

## Peníze

- `Aktivita::delka()` vrací skutečné desetinné hodiny (dřív `% 24` necelé hodiny tiše usekl).
- Bonus za vedení (`Finance::bonusZaAktivitu`) a přepočet na standardní aktivitu (`SystemoveNastaveni::getActivityStandardLengthCoefficient`, BFSR) berou pásma jako horní mez → **necelá hodina padá do vyššího pásma** (15 min = pásmo 1 h, 1:15 = 2 h, 5:15 = 6–7 h). (záměr)
- Nad 13 h přepočet pokračuje stejným krokem (každé další 2 h +0,5: 13:15–15 h = 3,5; do 17 h = 4,0). Dřív vyhodil výjimku a celý BFSR spadl. (nejisté – extrapolace tabulky, ne zadání) Bonus za vedení (`bonusyZaVedeniAktivity`) má pásma jen do 13 h, delší aktivita dostane bonus 0 – nezměněno.
- `finance-report-sirien.php::$delkaAktivityJakoNasobekStandardni` dělá totéž v SQL přes `CEIL(TIMESTAMPDIFF(MINUTE…)/60)`; test `FinanceReportSirienTest` hlídá shodu s PHP.
- Brigádnická aktivita (`Aktivita::vypocitejCenuBrigadnickeAktivity`): odměna za hodinu × **přesná délka**, zaokrouhleno na celé Kč (1:15 = 1,25 ×). (záměr) Dřív se hodiny zaokrouhlovaly na celé.

## Zobrazení času

- `DateTimeCzTrait::formatHodinaPripadneSMinutami()` – „14“ u celé hodiny, „14:15“ jinak; používá `Aktivita::denCasSkutecny()` (web, mail o uvolněném místě, admin) a kola turnaje v editoru.
- Náhled aktivity v programu: `ProgramStaticFileGenerator::aktivitaDoPole` `casText` ve tvaru `G:i–G:i`.
- Web detail aktivity: `Aktivita::delkaTextem()` („1 h 15 min“).

## Otevřené / mimo rozsah

- Cron (cron-job.org) běží jednou za hodinu, automatické zamčení aktivity od 14:15 / 14:30 tak proběhne až při dalším běhu. Zrychlit cron nejde jen tak – připomínky neuzavřené prezence počítají s hodinovým intervalem (`admin/cron.php`).
- `_casy.php` předvolba času během GC počítá s `setTime(0, 0, 0)`, takže nepředvolí aktuální čas, ale poslední začátek před půlnocí (nejisté, jestli záměr; existuje od 2016).
