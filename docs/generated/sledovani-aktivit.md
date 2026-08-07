# sledovani-aktivit

TL;DR: „Sledování" (watchlist) aktivity = uživatel si nechá poslat mail, až se na plné aktivitě uvolní místo. Dokument popisuje, kde se zobrazuje tlačítko „sledovat / zrušit sledování", kdy a komu se posílá mail, a tři chyby u **genderově rozdělených** aktivit (volno jen pro opačné pohlaví) nahlášené testerem 2026-06 — všechny tři jsou dnes opravené.

## Datový model

- Stav sledování je řádek v `akce_prihlaseni_spec` se `id_stavu_prihlaseni = StavPrihlaseni::SLEDUJICI` (= 5).
- `StavPrihlaseni::SLEDUJICI` v `model/Aktivita/StavPrihlaseni.php`.
- Struktura tabulky: `model/Aktivita/SqlStruktura/AkcePrihlaseniSpecSqlStruktura.php` (`id_akce`, `id_uzivatele`, `id_stavu_prihlaseni`).

## Vstupní body v kódu

- `Aktivita::prihlasovatko()` — `model/Aktivita/Aktivita.php` (cca ř. 3163) — renderuje přihlašovátko vč. „sledovat" / „zrušit sledování".
- `Aktivita::volnoPro()` — tamtéž (cca ř. 3734) — vrací `VolnoProEnum` (dřív string z `volno()`, ta už neexistuje).
- `VolnoProEnum` — `model/Aktivita/VolnoProEnum.php` — `PRO_VSECHNY` = `'u'` / `PLNO` = `'x'` / `JEN_ZENY` = `'f'` / `JEN_MUZI` = `'m'`. Backing hodnoty zůstaly historické právě proto, aby `'f'`/`'m'` šly porovnat s `Uzivatel::pohlavi()`. Dvě metody nesou logiku, kterou dřív dělaly stringové porovnávačky rozeseté po kódu:
  - `proPohlaviJeVolno(string $kodPohlavi): bool` — má uživatel daného pohlaví kam sednout (`PRO_VSECHNY`, nebo se hodnota rovná jeho pohlaví).
  - `pohlaviSVolnymMistem(): list<string>` — pro která pohlaví je volno (`PRO_VSECHNY` → obě, `PLNO` → žádné).
- `Aktivita::prihlasovatelnaProSledujici()` — `return !$this->tymova() && !$this->jeSoucastiTurnaje();` — sledovat lze jen netýmové aktivity mimo turnaj.
- `Aktivita::prihlasSledujiciho()` / `odhlasSledujiciho()` — zápis/smazání řádku v `akce_prihlaseni_spec`.
- `Aktivita::poslatMailSledujicim(array $pohlaviSVolnymMistem)` — `model/Aktivita/Aktivita.php` (ř. 2402) — odešle mail (`hlaskaMail('uvolneneMisto', …)`) jen sledujícím předaných pohlaví (filtr `AND uzivatele_hodnoty.pohlavi IN (…)` přímo v SQL).
- **Jediný spouštěč mailu**: `Aktivita::odhlas()` (ř. 2060–2066). Žádný cron, žádný jiný trigger, žádná fronta — posílá se synchronně v requestu odhlášení (retry viz Gotchas).
- Mail šablona `uvolneneMisto` v `nastaveni/hlasky/nastaveni-hlasky-subst.php`.

### Kdy přesně se mail pošle

Nestačí „aktivita byla plná" — porovnává se zaplněnost **před a po** odhlášení a mailuje se jen sledujícím těch pohlaví, pro která volno předtím nebylo a teď je:

```php
$volnoProPredOdhlasenim = $this->volnoPro();   // ř. 1999, PŘED smazáním řádku z akce_prihlaseni
// … reálné odhlášení + $this->refresh() …
$pohlaviSNoveUvolnenymMistem = array_values(array_filter(
    $this->volnoPro()->pohlaviSVolnymMistem(),
    static fn (string $pohlavi): bool => ! $volnoProPredOdhlasenim->proPohlaviJeVolno($pohlavi),
));
if ($pohlaviSNoveUvolnenymMistem && ! ($params & self::NEPOSILAT_MAILY_SLEDUJICIM)) {
    $this->poslatMailSledujicim($pohlaviSNoveUvolnenymMistem);
}
```

Důsledky, které nejsou z kódu na první pohled vidět:
- Odhlášení z aktivity, kde **už volno bylo** (`PRO_VSECHNY`), nepošle nic — množina „nově uvolněných" pohlaví je prázdná.
- Přechod `JEN_ZENY` → `PRO_VSECHNY` pošle mail **jen mužům**; ženy volno měly už předtím, nic nového pro ně nevzniklo.
- `$volnoProPredOdhlasenim` se musí sejmout **před** smazáním řádku a druhé volání až **po** `refresh()`, jinak obě strany porovnání vrátí totéž a nepošle se nikdy nic.

### Frontend (Preact /program) má vlastní kopii téhle logiky — DRIFT TRAP

Stránka `/program` (`gamecon.cz/program/...`) **není** server-rendered `prihlasovatko()`. Je to Preact appka renderující z JSON. Rozhodnutí o tlačítku sledování je v `ui/src/pages/program/components/tabulka/Přihlašovátko.tsx` a logika „volno typu" v `ui/src/utils/tranformace.ts` (`volnoTypZObsazenost()` — port PHP `volnoPro()`, vrací `'u'/'x'/'f'/'m'/'t'`; TS zůstalo u stringů, PHP přešlo na `VolnoProEnum` — hodnoty enumu jsou proto schválně ty historické stringy). Testy: `ui/src/utils/tranformace.test.ts` (`./bin-docker/yarn test`). JSON s obsazeností (`m/f/km/kf/ku/kt/t`) staví `Aktivita::obsazenostObj()` přes `ProgramStaticFileGenerator::generateObsazenosti()` do statického cache souboru (dirty-flag worker). **Když měníš pravidlo zobrazení sledování, musíš ho změnit na OBOU místech** (PHP `prihlasovatko()` i TSX `Přihlašovátko`). Build frontendu: `web/soubory/ui/bundle.js` je **committed** — po změně TSX spusť `./bin-docker/yarn build:web` a commitni nový bundle (ostra deploy `yarn build` nespouští).

## Jak se rozhoduje zobrazení (přihlašovátko)

Řetěz `if/elseif` v `prihlasovatko()`. Po opravě (2026-06) větve „jen ženská / jen mužská místa" přidávají k textu i odkaz na sledování přes `prihlasovatkoSledovani()`:
```php
$volnoPro = $this->volnoPro();
if ($volnoPro->proPohlaviJeVolno($u->pohlavi())) { ... "přihlásit" ... }
elseif ($volnoPro === VolnoProEnum::JEN_ZENY) { $out = 'pouze ženská místa' . $this->prihlasovatkoSledovani($u, ' | '); }
elseif ($volnoPro === VolnoProEnum::JEN_MUZI) { $out = 'pouze mužská místa' . $this->prihlasovatkoSledovani($u, ' | '); }
else { $out = $this->prihlasovatkoSledovani($u); }   // PLNO = úplně plno
```
`prihlasovatkoSledovani()` vrátí „sledovat" / „zrušit sledování" (dle `prihlasenJakoSledujici`), nebo prázdný řetězec když aktivitu nelze sledovat (`!prihlasovatelnaProSledujici()` → týmová / turnaj).

## Tři chyby u genderově rozdělených aktivit (nález 2026-06, tester)

Společná příčina všech tří: stav „volno jen pro opačné pohlaví" (`JEN_ZENY`/`JEN_MUZI`) je z pohledu dotčeného uživatele *plno*, ale starý kód ho jako plno nikde neřešil — porovnával se jen proti `'x'`.

U chyb 1+2 se to projevilo v zobrazení: větve `'f'`/`'m'` vracely jen statický text a `elseif` (resp. v TSX časný `return`) řetěz tím končil, takže větev se sledováním byla nedosažitelná. **Chyba byla na obou místech** — server `prihlasovatko()` i frontend `Přihlašovátko.tsx`.

1. **(OPRAVENO 2026-06) Nešlo začít sledovat.** Screenshot: `A.R.C.H.A. ♀ 3/5 ♂ 3/3 pouze ženská místa` (bez „sledovat"). Nyní se vedle textu zobrazí i „sledovat".

2. **(OPRAVENO 2026-06) Nešlo zrušit sledování.** Když uživatel sledoval aktivitu (kdysi `'x'`) a ta se odemkla na `'f'`/`'m'`, zůstal ve sledování bez možnosti se odhlásit. Screenshot: `Temná ulička ♀ 3/4 ♂ 5/5 pouze ženská místa`. Nyní se vedle textu zobrazí „zrušit sledování".

   Test (server): `tests/Aktivity/SledovaniGenderoveRozdeleneAktivityTest.php`.

3. **(OPRAVENO 2026-07) Maily sledujícím „celkově nefungují".** Byly to dvě samostatné chyby ve stejné podmínce:
   - Podmínka odeslání byla `volno() === "x"` *před* odhlášením. Genderově rozdělená aktivita se ale snáz dostane do `'f'`/`'m'` než do `'x'`. Když se uvolnilo místo na aktivitě, která byla `'f'` (nebo `'m'`), `volno()` před odhlášením **nebylo `'x'`** → mail se neodeslal, přestože se reálně uvolnilo místo pro sledující správného pohlaví. Nyní se porovnává zaplněnost před/po (viz „Kdy přesně se mail pošle").
   - `poslatMailSledujicim()` nefiltrovala podle pohlaví — poslala **všem** sledujícím. Nyní bere `list<string> $pohlaviSVolnymMistem` a filtruje adresáty v SQL.

## Stav oprav

- **Zobrazení (bug 1+2)** — HOTOVO 2026-06, na obou místech:
  - server (`web/moduly/aktivity.php` list): `Aktivita::prihlasovatkoSledovani()` přidá odkaz vč. ` | ` oddělovače — tahle šablona (`aktivity.xtpl`) spojuje obsazenost a přihlašovátko jen `&ensp;`, vlastní oddělovač nemá.
  - frontend (`/program`): `Přihlašovátko.tsx` — pomocná `sledováníTlačítko()` přidá tlačítko vedle textu „pouze … místa" místo časného `return`u (+ rebuild `bundle.js`). **BEZ vlastního ` | `** — oddělovač už dělá CSS `border-left: solid 1px #0005` na `.program td > div > form > a` (`web/soubory/blackarrow/program/program-trida.css:158`). Přidání literálního ` | ` tam způsobí dvojitou čáru (greyed border + pipe) — nepřidávej ho.
  - Text „pouze X místa" je správný jen pro uživatele opačného pohlaví; uživateli, pro jehož pohlaví je volno, vrátí `proPohlaviJeVolno($u->pohlavi())` „přihlásit" už dřív — do větví `JEN_ZENY`/`JEN_MUZI` tedy spadne jen ten, pro koho je reálně plno, takže nabídka sledování dává smysl.
- **Maily (bug 3)** — HOTOVO 2026-07. Stringová `volno()` nahrazena `volnoPro(): VolnoProEnum`, trigger porovnává zaplněnost před/po odhlášení, adresáti se filtrují dle pohlaví. Testy: `tests/Aktivity/SledovaniGenderoveRozdeleneAktivityTest.php` + `tests/Aktivity/VolnoProEnumTest.php`.
- **Zbývá (mimo rozsah oprav)**: odeslání je pořád synchronní v requestu odhlášení, bez fronty. `GcMail::odeslat()` zkusí poslat jednou znovu po `sleep(1)` a pak chybu zaloguje a přehodí výš — delší výpadek SMTP tedy mail ztratí. Případná fronta/retry je samostatná práce, ne dodělávka téhle karty.

## Gotchas

- `prihlasovatelnaProSledujici()` vylučuje týmové aktivity a aktivity v turnaji — sledování u nich neexistuje záměrně.
- **Retry existuje, ale jen jeden a jen v rámci requestu.** `GcMail::odeslat()` (`model/Kanaly/GcMail.php`) po selhání počká `sleep(1)` (ratelimiting) a zkusí to podruhé; když selže i ten, zaloguje chybu do mail logu a výjimku přehodí výš. Není to fronta — po skončení requestu se už nic neopakuje.
- **`volno()` už neexistuje**, nahradila ji `volnoPro(): VolnoProEnum`. Když narazíš na `=== 'x'` / `== $u->pohlavi()` porovnávání stringů, je to buď TS strana (`volnoTypZObsazenost()`, tam je to v pořádku), nebo neaktuální kód/dokumentace.
- `NEPOSILAT_MAILY_SLEDUJICIM` flag se používá při hromadných operacích (mazání uživatele `model/uzivatel.php`, prezence `AktivitaPrezence.php`), aby se neposílaly maily — ne bug, záměr.
