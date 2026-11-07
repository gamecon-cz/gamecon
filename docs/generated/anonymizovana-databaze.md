# Anonymizovaná databáze pro čerstvý checkout

TL;DR: čerstvý checkout si databázi nevytváří z `000.php`, ale nahraje commitnutý dump `migrace/pomocne/gc_anonymizovana_databaze.sql` a pustí nad ním migrace. Dump vzniká každou noc na ostré; v repu se musí čas od času obnovit, jinak `make run` / `docker compose up` u nového vývojáře spadne.

## Jak čerstvý checkout dostane databázi

- `make run` jen spustí kontejnery, databázi nezakládá. Vznikne při prvním požadavku na `/web`: `nastaveni/db-migrace.php` ukáže webové GUI Godric migrací a po potvrzení založí `gamecon`, nahraje dump (`\MySQLImport`) a pustí `migrace/*`.
- Po nahrání dumpu platí přihlášení `AnonymizovanaDatabaze::ADMIN_LOGIN` / `ADMIN_PASSWORD` (`admin` / `admin`).
- Ostatní testy dump nepoužívají: `tests/_zavadec.php` staví prázdnou databázi z `migrace/000.php` a dalších migrací. Dump nahrává jen `AnonymizovanaDatabazeVRepozitariTest`.

## Odkud dump je

- Na ostré ho každou noc kolem 05:00 vyrobí `AnonymizovanaDatabaze::obnov()` + `exportujDoSouboru()` do `ZALOHA_DB_SLOZKA/gc_anonymizovana_databaze.sql.gz`.
- `bin/stahni-anonymizovanou-databazi.sh` ho stáhne a rozbalí na místo v repu. Pak spusť `AnonymizovanaDatabazeVRepozitariTest`.
- Repo je veřejné. Dump smí obsahovat jen to, co z gamecon.cz vidí odhlášený návštěvník, plus pseudonymní data (`Login<id>`, `email<id>@gamecon.cz`, posunutá ID).

## Co se do dumpu nedostane

- `AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY`: tabulky, které nic neanonymizuje a které drží přihlašovací údaje (OAuth tokeny), e-maily, texty psané účastníky (názvy týmů, poznámky ke slevám) nebo ID uživatelů bez cizího klíče na `uzivatele_hodnoty`, která anonymizace neposune. Nová tabulka s takovým obsahem patří tam.
- `AnonymizovanaDatabazeTest` hlídá, že pro každou tabulku z konstanty vloží řádek a po anonymizaci je prázdná. Nová položka v konstantě bez řádku v testu ho shodí.
- `AnonymizovanaDatabazeVRepozitariTest` hlídá totéž přímo na commitnutém souboru. K tomu žádný uživatel kromě `admin` nemá jiné než anonymní hodnoty ve sloupcích, které `AnonymizovanyUzivatel` přepisuje, ostatní sloupce `uzivatele_hodnoty` jsou v `PREKONTROLOVANE_SLOUPCE_UZIVATELE` a každá tabulka s řádky je buď v `PRILIS_CITLIVE_TABULKY` (pak je prázdná), nebo v `PREKONTROLOVANE_TABULKY_S_DATY` v testu. Tabulka, která od posledního obnovení přibyla a má data, tak test shodí a někdo ji musí posoudit, než se dostane do veřejného repozitáře.

## Kdy dump obnovit

- Po zredukování nebo smazání migrací (jako 2026-01-18). Dump má v tabulce `migrations` zapsané použité migrace; když schéma nezná to, co smazané migrace vytvářely, první zbývající migrace spadne (`Unknown column ...`). Postaru to neopravíš přehráním starého dumpu přes starou řadu migrací: endless migrace potřebují předchozí roky a `000.php` se po sloučení ještě upravoval, takže stará řada na dumpu nedoběhne.
- Na přelomu roku: dump z roku N přestane fungovat, až `ROCNIK` dosáhne N+2, protože `migrace/9999_03-letosni-systemove-nastaveni-endless.php` kopíruje loňské nastavení (`PRUMERNE_LONSKE_VSTUPNE`). Pod `UNIT_TESTS` si chybějící záznam vymyslí, proto to hlídá až `AnonymizovanaDatabazeVRepozitariTest::testMigracePodDumpemProjdou`. Od 1. 1. N+2 je červený, dokud se dump neobnoví.

## Gotchas

- Prázdná databáze (testy) nemá tabulky, které `000.php` nevytváří a ostrá je má. Test anonymizace do každé tabulky z `PRILIS_CITLIVE_TABULKY` vkládá řádek, takže tam musí být i v prázdné databázi. Newsletterové tabulky (entity Doctrine) proto doplňuje migrace `2026-11-07-192550_newsletter-tabulky-v-prazdne-databazi.php`.
