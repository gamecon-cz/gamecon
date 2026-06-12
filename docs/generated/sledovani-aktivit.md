# sledovani-aktivit

TL;DR: „Sledování" (watchlist) aktivity = uživatel si nechá poslat mail, až se na plné aktivitě uvolní místo. Dokument popisuje, kde se zobrazuje tlačítko „sledovat / zrušit sledování", kdy a komu se posílá mail, a tři reálné chyby u **genderově rozdělených** aktivit (volno jen pro opačné pohlaví) nahlášené testerem 2026-06.

## Datový model

- Stav sledování je řádek v `akce_prihlaseni_spec` se `id_stavu_prihlaseni = StavPrihlaseni::SLEDUJICI` (= 5).
- `StavPrihlaseni::SLEDUJICI` v `model/Aktivita/StavPrihlaseni.php`.
- Struktura tabulky: `model/Aktivita/SqlStruktura/AkcePrihlaseniSpecSqlStruktura.php` (`id_akce`, `id_uzivatele`, `id_stavu_prihlaseni`).

## Vstupní body v kódu

- `Aktivita::prihlasovatko()` — `model/Aktivita/Aktivita.php` (cca ř. 3122) — renderuje přihlašovátko vč. „sledovat" / „zrušit sledování".
- `Aktivita::volno()` — tamtéž (cca ř. 3677) — vrací typ volného místa: `'u'` volno / `'x'` plno / `'f'` zbývají jen ženská místa / `'m'` zbývají jen mužská místa.
- `Aktivita::prihlasovatelnaProSledujici()` — `return !$this->tymova() && !$this->jeSoucastiTurnaje();` — sledovat lze jen netýmové aktivity mimo turnaj.
- `Aktivita::prihlasSledujiciho()` / `odhlasSledujiciho()` — zápis/smazání řádku v `akce_prihlaseni_spec`.
- `Aktivita::poslatMailSledujicim()` — `model/Aktivita/Aktivita.php` (cca ř. 2375) — odešle mail (`hlaskaMail('uvolneneMisto', …)`).
- **Jediný spouštěč mailu**: `Aktivita::odhlas()` (cca ř. 2039): `if ($this->volno() === "x" && !($params & NEPOSILAT_MAILY_SLEDUJICIM))`. Žádný cron, žádný jiný trigger, žádná fronta/retry — posílá se synchronně v requestu odhlášení.
- Mail šablona `uvolneneMisto` v `nastaveni/hlasky/nastaveni-hlasky-subst.php`.

### Frontend (Preact /program) má vlastní kopii téhle logiky — DRIFT TRAP

Stránka `/program` (`gamecon.cz/program/...`) **není** server-rendered `prihlasovatko()`. Je to Preact appka renderující z JSON. Rozhodnutí o tlačítku sledování je v `ui/src/pages/program/components/tabulka/Přihlašovátko.tsx` a logika „volno typu" v `ui/src/utils/tranformace.ts` (`volnoTypZObsazenost()` — 1:1 port PHP `volno()`, vrací `'u'/'x'/'f'/'m'/'t'`). JSON s obsazeností (`m/f/km/kf/ku/kt/t`) staví `Aktivita::obsazenostObj()` přes `ProgramStaticFileGenerator::generateObsazenosti()` do statického cache souboru (dirty-flag worker). **Když měníš pravidlo zobrazení sledování, musíš ho změnit na OBOU místech** (PHP `prihlasovatko()` i TSX `Přihlašovátko`). Build frontendu: `web/soubory/ui/bundle.js` je **committed** — po změně TSX spusť `./bin-docker/yarn build:web` a commitni nový bundle (ostra deploy `yarn build` nespouští).

## Jak se rozhoduje zobrazení (přihlašovátko)

Řetěz `if/elseif` v `prihlasovatko()`. Po opravě (2026-06) větve `'f'`/`'m'` přidávají k textu i odkaz na sledování přes `prihlasovatkoSledovani()`:
```
$volno = $this->volno();
if ($volno === 'u' || $volno == $u->pohlavi()) { ... "přihlásit" ... }
elseif ($volno === 'f') { $out = 'pouze ženská místa' . $this->prihlasovatkoSledovani($u, ' | '); }
elseif ($volno === 'm') { $out = 'pouze mužská místa' . $this->prihlasovatkoSledovani($u, ' | '); }
else { $out = $this->prihlasovatkoSledovani($u); }   // 'x' = úplně plno
```
`prihlasovatkoSledovani()` vrátí „sledovat" / „zrušit sledování" (dle `prihlasenJakoSledujici`), nebo prázdný řetězec když aktivitu nelze sledovat (`!prihlasovatelnaProSledujici()` → týmová / turnaj).

## Tři chyby u genderově rozdělených aktivit (nález 2026-06, tester)

Příčina chyb 1+2: stav `'f'`/`'m'` z `volno()` (volno jen pro opačné pohlaví) je z pohledu dotčeného uživatele *plno*, ale starý kód ho neřešil jako plno — větve `'f'`/`'m'` vracely jen statický text a `elseif` (resp. v TSX časný `return`) řetěz tím končil, takže větev se sledováním byla nedosažitelná. **Chyba byla na obou místech** — server `prihlasovatko()` i frontend `Přihlašovátko.tsx`.

1. **(OPRAVENO 2026-06) Nešlo začít sledovat.** Screenshot: `A.R.C.H.A. ♀ 3/5 ♂ 3/3 pouze ženská místa` (bez „sledovat"). Nyní se vedle textu zobrazí i „sledovat".

2. **(OPRAVENO 2026-06) Nešlo zrušit sledování.** Když uživatel sledoval aktivitu (kdysi `'x'`) a ta se odemkla na `'f'`/`'m'`, zůstal ve sledování bez možnosti se odhlásit. Screenshot: `Temná ulička ♀ 3/4 ♂ 5/5 pouze ženská místa`. Nyní se vedle textu zobrazí „zrušit sledování".

   Test (server): `tests/Aktivity/SledovaniGenderoveRozdeleneAktivityTest.php`. Frontend testovací infra v `ui/` neexistuje (žádný vitest/jest) — TSX změna ověřena `tsc`/eslint + buildem; logika je 1:1 port serveru.

3. **(NEOPRAVENO) Maily sledujícím „celkově nefungují".** (nejisté — odvozeno z kódu, neověřeno z provozu) Dva slabé body:
   - Podmínka odeslání je `volno() === "x"` *před* odhlášením. Genderově rozdělená aktivita se ale snáz dostane do `'f'`/`'m'` než do `'x'`. Když se uvolní místo na aktivitě, která byla `'f'` (nebo `'m'`), `volno()` před odhlášením **nebylo `'x'`** → **mail se neodešle**, přestože se reálně uvolnilo místo, které sledující správného pohlaví může chtít.
   - `poslatMailSledujicim()` nefiltruje podle pohlaví — pošle **všem** sledujícím. U gender-aktivity tak může dostat „uvolnilo se místo" i sledující, pro jehož pohlaví se nic neuvolnilo.
   - Žádný retry: selhání SMTP v requestu = ztracený mail.

## Stav oprav

- **Zobrazení (bug 1+2)** — HOTOVO 2026-06, na obou místech:
  - server: `Aktivita::prihlasovatkoSledovani()` přidá odkaz na sledování i ve větvích `'f'`/`'m'`.
  - frontend: `Přihlašovátko.tsx` — pomocná `sledováníTlačítko(oddělovač)` přidá tlačítko vedle textu „pouze … místa" místo časného `return`u (+ rebuild `bundle.js`).
  - Text „pouze X místa" je správný jen pro uživatele opačného pohlaví; uživateli, pro jehož pohlaví je volno, vrátí `volno() == $u->pohlavi()` „přihlásit" už dřív — do `'f'`/`'m'` větve tedy spadne jen ten, pro koho je reálně plno, takže nabídka sledování dává smysl.
- **Maily (bug 3)** — NEOPRAVENO. Větší zásah do logiky obsazenosti: spouštět `poslatMailSledujicim()` i při přechodu z `'f'`/`'m'` na volno pro dané pohlaví (ne jen z `'x'`), a ideálně filtrovat příjemce dle pohlaví, pro které se místo uvolnilo. Před implementací ověřit reálné chování z logů.

## Gotchas

- `prihlasovatelnaProSledujici()` vylučuje týmové aktivity a aktivity v turnaji — sledování u nich neexistuje záměrně.
- `NEPOSILAT_MAILY_SLEDUJICIM` flag se používá při hromadných operacích (mazání uživatele `model/uzivatel.php`, prezence `AktivitaPrezence.php`), aby se neposílaly maily — ne bug, záměr.
