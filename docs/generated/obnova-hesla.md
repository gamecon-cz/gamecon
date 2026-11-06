TL;DR: Zapomenuté heslo = mail s podepsaným odkazem platným hodinu, heslo si uživatel nastaví sám. Dokument pokrývá limity proti zahlcení cizí schránky, co se kde loguje a co se schválně neloguje.

## Vstupní body

- `symfony/src/Controller/ObnovaHeslaController.php` — `/zapomenute-heslo` (žádost, `vyridZadost()` rozhoduje o odeslání), `/obnova-hesla` (nastavení hesla z odkazu)
- `model/Uzivatel/ResetHeslaToken.php` — bezstavový token; váže se na aktuální `heslo_md5`, takže po změně hesla přestanou platit všechny dřívější odkazy
- `model/Uzivatel/ObnovaHeslaLog.php` — tabulka `obnova_hesla_log` a limity

## Limity

- **Účet:** nejvýš `MAX_MAILU_NA_UCET` mailů za `OKNO_MINUT`, počítají se jen řádky s `odeslano = 1`. Překročení se navenek neprojeví (stejná hláška jako u odeslání), jinak by prozradilo, že k e-mailu účet je.
- **IP:** nejvýš `MAX_ZADOSTI_Z_IP` žádostí za okno, počítají se všechny, i na neznámý e-mail. Překročení se hlásí chybou. Odmítnutá žádost se z logu **hned smaže**, aby vytrvalý bot nemohl tabulku plnit bez omezení.
- **IP se počítá po rozsazích:** IPv4 adresa sama za sebe, IPv4 zapsaná jako IPv6 (`::ffff:192.0.2.1`) jako ta IPv4, jiná IPv6 celá síť /64. Klient s IPv6 má obvykle celou /64 a adresu v ní může měnit s každou žádostí; po jednotlivých adresách by limit neplatil (gamecon.cz má záznam AAAA). Rozsah je generovaný sloupec `ip_rozsah` (výraz v migraci `2026-11-06-110407_obnova-hesla-log-ip-rozsah.php`), PHP ho nepočítá: `jePrekrocenLimitIp()` bere id právě zapsané žádosti a počítá řádky se stejným rozsahem. Plná adresa zůstává ve sloupci `ip` kvůli dohledání.
- **Nejdřív zápis, pak počítání:** `vyridZadost()` žádost zapíše (u účtu rovnou s `odeslano = 1`) a teprve pak počítá limity, do kterých se počítá i ona (`>` limit, ne `>=`). Neodejde-li mail, `oznacNeodeslano()`. Při opačném pořadí (počítat, poslat, zapsat) poslalo 10 paralelních žádostí s jedním vyřešením Altchy 10 mailů. Souběh teď limit nepřetáhne, nanejvýš odmítne o něco víc.
- Řádky starší než `UCHOVAVAT_DNI` maže každý zápis (IP jsou osobní údaj).
- Čas měří DB (`NOW()`) pro zápis i počítání.

## Co se kde loguje

- `obnova_hesla_log` — každá přijatá žádost: kdy, IP, účet (`NULL` = k e-mailu účet není), jestli mail odešel. Prohlížeč v adminu není, jen SQL. Anonymizovaná DB ji dostane prázdnou (`AnonymizovanaDatabaze::zkopirujData()`).
- Log odeslaných mailů (`logy/maily.sqlite`, admin Dev → Mail logy, šablona `obnova_hesla`) — token v odkazu je nahrazený `***` (`GcMail::skrytVLogu()`), jinak by si s ním kdokoli s přístupem k logu nastavil cizí heslo. Soubor `MAILY_DO_SOUBORU` (beta/lokál bez SMTP) dostává mail **s platným** odkazem, je to náhrada doručení.
- `uzivatele_hodnoty_log` — změna hesla se zapíše se zdrojem `obnova-hesla`; místo hashů `***` (`Uzivatel::SKRYTE_HODNOTY_V_LOGU`), prázdná stará hodnota zůstane prázdná (heslo předtím nebylo).

## Gotchas

- **Altcha nebrání opakovanému použití výzvy:** `AltchaOrg\Altcha\V1\Altcha::verifySolution()` ověří jen podpis a expiraci, jedno vyřešení tedy projde opakovaně až do expirace (`Altcha::PLATNOST_SEKUND`). Proto limity výše. Týká se i registrace a přihlášení, kde limity nejsou.
- **IP na preview:** před kontejnerem stojí Caddy a Symfony nemá nastavené `trusted_proxies`, takže `getClientIp()` vrací adresu proxy a všichni návštěvníci preview sdílí jeden limit IP. Ostrá odpovídá přímo z Apache (`Server: Apache`, bez `Via` a hlaviček CDN), tam je IP návštěvníka (podle hlaviček, na serveru neověřeno).
- **Jedna síť /64, jeden limit:** kdo sdílí /64 (wifi na festivalu s IPv6, hosting nebo VPN, které dávají adresy z jedné /64), sdílí i limit, stejně jako lidé za jednou IPv4 přes NAT. Kdo pošle `MAX_ZADOSTI_Z_IP` žádostí, zablokuje obnovu hesla ostatním v síti až do konce okna (hláška „Z tvé sítě přišlo…“, ač oni sami nic neposlali). Je to cena za to, že změna adresy uvnitř /64 limit neobejde.
- **Kdo má víc sítí /64, limit IP pořád obejde.** Nejen hosting (běžně /48, tedy 65 536 sítí): i domácí přípojky dostávají od poskytovatele obvykle /56 (256 sítí) nebo /48. Limit účtu platí dál, každý účet dostane nejvýš `MAX_MAILU_NA_UCET` mailů za okno; celkový počet mailů za okno ale omezený není. Druhý, hrubší rozsah (/56 nebo /48 s vyšším limitem) by pomohl u domácích přípojek, u hostingu ne a sdílený limit by dopadl na víc lidí (u mobilních operátorů leží /64 cizích zákazníků vedle sebe); zatím se nedělá. Celkový limit by zase při útoku zablokoval obnovu hesla všem.
- **Pád uprostřed odesílání:** výjimka (i chyba SMTP) projde přes `finally` ve `vyridZadost()` a žádost se označí `odeslano = 0`. `finally` neproběhne jen při fatální chybě (došlá paměť) nebo když proces ukončí server (časový limit požadavku v PHP-FPM, Apache); `max_execution_time` to na Linuxu nebývá, měří jen čas skriptu a čekání na SMTP do něj nepočítá. Žádost pak zůstane s `odeslano = 1`, ač mail neodešel, a **každá** taková žádost se počítá do limitu účtu: opakované pády (třeba SMTP, které visí déle než časový limit serveru) vyčerpají limit účtu až do konce okna. Schválně se to neřeší, zápis s `odeslano = 1` před odesláním brání souběhu (viz výše).
- **Účet bez hesla mail nedostane:** `posliResetMail()` skončí, když je `heslo_md5` prázdné (typicky rychloregistrace). Nejisté, jestli je to záměr.
- Test akce `zadost()` celé by potřeboval Altchu a vykreslení stránky webu; testy volají `vyridZadost()` / `posliResetMail()` přes reflexi (`tests/Symfony/Controller/ObnovaHeslaControllerTest.php`).
