TL;DR: Tři hromadná odhlašování neplatičů za ročník, spouští je hodinový CRON; kdy smí běžet, koho odhlásí, co se stane, když běh nevyjde, a proč časové pojistky porovnávají se skutečným časem, ne s posunutým `$kDatu` e-mailů předem.

## Vstupní body

- `admin/cron/jobs/odhlaseni_neplaticu.php` — samotné odhlášení; běží v jobu `aktivity_hromadne` před aktivací aktivit (`admin/cron/_cron_job.php`), aktivace jde až po něm a i když se odhlášení přeskočí.
- `model/Uzivatel/HromadneOdhlaseniNeplaticu.php` — `neplaticiAKategorie()` (všechny časové pojistky, generátor neplatičů), `hromadneOdhlasit()`, `odhlaseniProvedenoKdy()` (záznam v logu hromadných akcí, podle něj CRON pozná hotové odhlášení).
- `model/Uzivatel/KategorieNeplatice.php` — kdo je neplatič (kategorie 1 a 2 se odhlašují).
- `model/Cas/DateTimeGamecon.php` — `spocitej*HromadneOdhlasovani()`, `nejblizsiHromadneOdhlasovaniKdy()`, `nejblizsiVlnaKdy()`.
- E-maily předem: `mail_cfo_brzke_odhlaseni_neplaticu.php` (CFO týden, den, hodinu předem), `mail_varovani_neplaticum_o_brzkem_odhlaseni.php` (neplatičům tři dny předem), `mail_cfo_nesparovane_platby.php`.
- Ruční spuštění odhlašování v adminu není. Admin má jen ruční „Aktivovat vše“ (`admin/scripts/modules/aktivity/aktivity.php`), které otevře aktivity bez ohledu na plánovaný čas vlny.

## Kalendář

- 1. odhlašování = třetí vlna − 10 minut (záměr: uvolnit místa těsně před otevřením vlny), 2. = třetí vlna + 9 dní v 00:00, 3. = 2. + týden. Nastavení `HROMADNE_ODHLASOVANI_1..3` je může přepsat.
- „Nejbližší“ odhlašování je první, které je nejvýš den staré (`VYCHOZI_PLATNOST_HROMADNYCH_AKCI_ZPETNE`): zmeškaný běh se do druhého dne zkouší znovu, pak se čeká na další odhlašování.

## Časové pojistky (`neplaticiAKategorie()`)

Všechny hází potomka `NevhodnyCasProHromadneOdhlasovani`, CRON ho jen zaloguje a skončí:

1. `NaHromadneOdhlasovaniJeBrzy` — plánovaný čas ještě nenastal.
2. `NaHromadneOdhlasovaniJePozde` — plánovaný čas je starší než den.
3. `HromadneOdhlasovaniJePrilisBrzyPoVlne` — plánovaný čas padl do týdne po začátku vlny (chybné nastavení).
4. `HromadneOdhlasovaniAzPoZacatkuVlny` — odhlašování naplánované před vlnou běží až po jejím (plánovaném) začátku. Bez tolerance. Platí jen pro odhlašování, po kterém ještě nějaká vlna je, tedy prakticky pro první; druhé a třetí běží po třetí vlně schválně. Vzniklo po incidentu z 1. 7. 2026 (Trello 1464): vlna ve 20:26, odhlašování ve 20:16, CRON ho pustil ve 21:14 a zrušil i přihlášky ze třetí vlny.

Co se stane po přeskočení prvního odhlašování: hodinové běhy ho další den ještě zkoušejí (a znovu přeskočí), pak je nejbližší druhé odhlašování (třetí vlna + 9 dní). To odhlásí, kdo do té doby nezaplatí, a to včetně přihlášek ze třetí vlny. Neplatičům předtím zase přijde varování tři dny předem a CFO e-maily předem. O samotném přeskočení se dozví jen log CRONu.

## Gotchas

- **`$kDatu` není skutečný čas.** E-maily předem volají `neplaticiAKategorie()` s `$kDatu` posunutým do budoucna (hodina, den, tři dny, týden), aby prošly pojistky 1 a 2. Pojistka, která se ptá na skutečnost (už se lidé přihlašují do vlny?), proto musí brát `SystemoveNastaveni::ted()`. S `$kDatu` by se při hodinovém CRONu varování neplatičům ani e-maily CFO typicky neodeslaly: první běh, kdy odhlašování spadne do posunu, má posunutý čas už po vlně, a každý další ještě později. Hlídá `HromadneOdhlaseniNeplaticuTest::varovaniPredOdhlasovanimSeRidiSkutecnymCasemNeSimulovanym`.
- **`ted()` je čas startu requestu.** `_cron_na_cas.php` u parametru `cas` v budoucnu (do 80 s) počká, ale `ted()` zůstane z doby před čekáním. Job spuštěný chvíli před plánovaným odhlašováním s `cas=<plánovaný čas>` tak po čekání skončí na pojistce 1. Funguje `cas=now` spuštěné v plánovanou minutu nebo po ní.
- **Hodinový CRON a desetiminutové okno.** První odhlašování smí běžet jen mezi svým plánovaným časem a začátkem vlny (10 minut). Hodinový `aktivity_hromadne` do okna trefí jen náhodou. Bez samostatného běhu v okně (nastavení na cron-job.org, mimo repozitář) se první odhlašování přeskočí a neplatiče odhlásí až druhé (nejisté, jak je cron-job.org nastavený dnes).
- **Kontrola je na začátku běhu.** Odhlašování spuštěné těsně před vlnou může doběhnout až po ní (stažení plateb z Fio, záloha DB, desítky neplatičů trvaly v incidentu dvě minuty). V `aktivity_hromadne` se aktivace pouští až po něm, takže nová vlna se otevře až po odhlášení; ruční „Aktivovat vše“ nebo samostatný job aktivace by se ale překrýt mohly. Pojistka 4 porovnává s plánovaným časem vlny, ne s logem aktivace, takže vlnu otevřenou ručně dřív nepozná.
- **Co se ruší.** Kategorie 2: postupně ubytování, LARPy, RPG, pak ostatní aktivity (`odhlasMuJenNeco()`), dokud dluh neklesne. Kategorie 1 a zbytek 2: `Uzivatel::odhlasZGc()` (všechny aktivity, sledování, role, zrušitelné objednávky). Dluh (`Finance::stav()`) počítá všechny současné přihlášky. `akce_prihlaseni` nemá čas přihlášení, ten je jen v `akce_prihlaseni_log.kdy`; odhlašovat jen z toho, co měl neplatič v plánovaný čas, by chtělo stav financí k datu a filtr ve `Shop::zrusPrihlaseniNa*()` (neuděláno, viz Trello 1464).
- **Testy.** `HromadneOdhlaseniNeplaticuTest::dejSystemoveNastaveniSTretiVlnou()` počítá vlny a odhlašování od třetí vlny jako výchozí hodnoty. Částečná rušení (ubytování, aktivity) z testů, které opravdu odhlašují, přežijí rollback testu a ovlivní další testy třídy; v obráceném pořadí (`--order-by=reverse`) proto padá i původní `hromadneOdhlaseniOdhlasiSpravneUzivateleNeboObjednavky`. Testy, které jen čtou neplatiče, stojí v souboru před těmi, co odhlašují.
