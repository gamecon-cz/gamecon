# Analýza robustnosti `Aktivita::prihlas()` pro týmové a turnajové aktivity

TL;DR: rozbor `model/Aktivita/Aktivita.php::Aktivita::prihlas()` a navazujícího kódu (`AktivitaTym`, `AktivitaTymService`, `AktivitaTurnaj`) z pohledu robustnosti pro týmové/turnajové scénáře. Cíl je najít místa, kde současný flow může uvést systém do nekonzistentního stavu, a navrhnout konkrétní úpravy.

Hierarchie priority (od nejdůležitějšího):
1. Kritické – uvádí systém do nekonzistentního stavu (sekce **B**).
2. Hlavní user-flow (sekce **C**).
3. Nestandardní uživatelské cesty (sekce **D**).
4. Admin (sekce **E**).

Vstupní body (pro orientaci):
- `model/Aktivita/Aktivita.php::prihlas` — hlavní metoda
- `model/Aktivita/Aktivita.php::odhlas`
- `model/Aktivita/Aktivita.php::zkontrolujZdaSeMuzePrihlasit`
- `model/Aktivita/Aktivita.php::prihlasovatkoZpracujBezBack` — vstup z `web/moduly/api/aktivitaAkce.php`
- `web/moduly/api/aktivitaTym.php` — týmové akce z UI
- `model/Aktivita/AktivitaTym.php` + `symfony/src/Service/AktivitaTymService.php`
- `model/Aktivita/AktivitaTurnaj.php`

---

## A. Aktuální flow přihlašování (jak to funguje teď)

### A.1 Hlavní use-case: kapitán zakládá tým a přihlásí se

Vstup z UI: `web/moduly/api/aktivitaTym.php` akce `zalozPrazdnyTym`. Volá `Aktivita::prihlas($u, $u)` bez `$tym`.

Kroky uvnitř `prihlas()`:
1. Pokud je už uživatel přihlášen → return false (early-out).
2. `$zakladaTym = tymova() && $tym == null` → pravda → volá `AktivitaTym::zalozPrazdnyTym(idUzivatele, idAktivity, ignorovatLimit?)`.
   - `AktivitaTymService::vytvorNovyTym` provede: `zkontrolujZeNeniVJinemTymu`, `zkontrolujMuzeZalozitTym` (limit počtu týmů aktivity), vygeneruje 4-místný kód (1000–9999), nastaví kapitána, založí Doctrine entitu, `addAktivita($aktivita)`, `flush()`. **Mimo `dbBegin()` níže.**
3. Pokud je aktivita součástí turnaje a tým je rozpracovaný (žádný člen) a nemá přiřazená všechna kola turnaje, volá `turnaj()->priradTymNaAutomatickaKola($tym)`. Funkce přidá tým na všechna kola, kde je jen jedna aktivita; vrátí `true` jen když je týmu přiřazena aktivita ve všech kolech. Pokud `false` → `prihlas` vrací `false` (UI pak ukáže krok výběru kol).
4. Načtou se `dalsiAktivityTymu` (další aktivity, na kterých je tým přihlášen).
5. `zkontrolujZdaSeMuzePrihlasit(...)` — kapacita slotů, kolize aktivit, GC přihlášen, brigádník, není přihlášen na jiné aktivitě stejného kola, kontrola navazujících aktivit (rekurzivně bez kontroly limitu pro týmovky), kontrola `prihlasovatelna()` (stav, časové okno).
6. **Pro každou další aktivitu týmu volá `aktivitaTymu->prihlas(... | IGNOROVAT_TURNAJ | IGNOROVAT_KONTROLY)` → každé volání má vlastní `dbBegin/dbCommit`.**
7. `odhlasZeSledovaniAktivitVeStejnemCase` — odstraní watchlist u kolizí.
8. `dbBegin()`:
   - `SELECT … FOR UPDATE` na `akce_seznam` (lock řádku aktivity).
   - `refresh()` + opětovná kontrola kapacity slotu.
   - Pokud `tymova()` → `AktivitaTym::prihlasUzivateleDoTymu(idUzivatele, idAktivity, idTymu)` (vytvoří `TeamMemberRegistration`).
   - `INSERT INTO akce_prihlaseni …`
   - `zalogujPrihlaseni`, `zrusPredchoziStornoPoplatek`.
   - `dbCommit()`.
9. `refresh()`, `touchDirtyFlag(OBSAZENOSTI)`.

Po commitu UI dostane úspěch. Tým má kapitána, je přihlášený na aktivitu (a na další kola turnaje, pokud byla automatická). Kapitán dostane kód týmu pro pozvání.

### A.2 Hlavní use-case: další hráč se přidává kódem

Vstup: `web/moduly/api/aktivitaAkce.php` → `Aktivita::prihlasovatkoZpracujBezBack` → POST `prihlasit=<idAktivity>` + `tymKod=<4ciferny>`. Najde tým přes `AktivitaTym::najdiPodleKodu` (vyhodí Chybu, pokud tým neexistuje). Volá `Aktivita::prihlas($u, $u, tym: $tym)`.

V `prihlas()`:
- `$zakladaTym` = false (tým je předaný).
- Pokud je tým rozpracovaný a nemá všechna kola, volá `priradTymNaAutomatickaKola` — ale `jeRozpracovany()` je `count(clenove) === 0`. **Po prvním přidaném členovi už tato větev neběží** → další člen nikdy nezahájí přiřazení kol.
- `zkontrolujZdaSeMuzePrihlasit` → ověří mj. `zkontrolujZdaSeMuzePrihlasitDoTymuNaTetoAktivite` (kapacita týmu).
- Rekurzivně přihlásí na další aktivity týmu.
- DB transakce (FOR UPDATE + INSERT).

### A.3 Hlavní use-case: přihlášení do veřejného týmu

Stejné jako A.2, jen UI zavolá s `tymId` (z výpisu veřejných týmů) místo kódu.

### A.4 Edge / krajní cesty

- **Vícekolový turnaj s výběrem kol**: po `zalozPrazdnyTym` `priradTymNaAutomatickaKola` vrátí `false` → kapitán se zatím nepřihlásí, vrací se rozpracovaný tým. UI zobrazí výběr kol (`jeTrebaPredpripravitTym`). Po `potvrdVyberAktivit` se aktivita přidá k týmu (`pridejTymNaAktivitu`) a hned potom `prihlas` přihlásí kapitána.
- **Turnaj s více aktivitami v jednom kole** (rozvětvené přihlášení) — `idAktivitProKola()[$kolo]` vrací více ID; toto je explicitně mimo standardní flow, řešeno kapitánovým výběrem.
- **Kapitán má kolizi v čase aktivity**: `priradTymNaAutomatickaKola` proběhne, ale `prihlas` v kroku 5 hodí `Chybu` z kontroly kolizí. Tým zůstává rozpracovaný (kapitán není v `akce_tym_prihlaseni`, ale má `kapitan_id` na týmu). UI nabídne "přihlásit kapitána" zvlášť, jakmile má volný slot.
- **Tým expiroval (72h)**: cron `vyresExpirovaneTymyHromadne` projde `findExpired()` (zamceny=false a expiruje<now) a podle `tym_smazat_po_expiraci` aktivity buď odhlásí všechny členy a tým smaže (`ODEMKNI_TYM_ODHLASENIM`) nebo má zveřejnit tým.
- **Rozpracovaný tým bez členů** (kapitán nedokončil) — cron `smazRozpracovaneTymy` smaže týmy bez členů starší než `CAS_NA_PRIPRAVENI_TYMU_MINUT`.
- **Odhlášení člena z turnaje** (`Aktivita::odhlas`): mělo by odhlásit ze všech aktivit týmu. Viz B.1 — kvůli bugu se to NEděje.
- **Odhlášení posledního člena**: v `odhlasUzivateleOdTymu` po flush najde `findOldestClen`; pokud žádný → `em->remove($team)`. Tj. tým se rozpustí, ale aktivita zůstává pro ostatní/další týmy.
- **Sledující** (`prihlasSledujiciho` / `odhlasZeSledovaniAktivitVeStejnemCase`) — pro tým-aktivity sleduje přidání místa pro celý tým, ne jen sebe (viz `tymovky.md`); aktuálně mail jde každému sledujícímu (`poslatMailSledujicim`) bez týmové kontroly.
- **Admin import / hromadné akce** — využívají `Aktivita::prihlas` přímo (`admin/scripts/modules/aktivity/_Import/ImporterUcastnikuNaAktivitu.php`), bez rozlišení tym/non-tym → vytvoří se tým, ale flow nevolá explicitně `pridejTymNaAktivitu` pro vícekolové turnaje.

---

## B. Kritické problémy — možná inkonzistence stavu

Pořadí: nejdříve to, co skutečně rozbije DB stav nebo obejde core invariant.

### B.1 `odhlas()` neodhlašuje z dalších kol turnaje (bitový operátor)

`model/Aktivita/Aktivita.php:1961`:
```php
if ($tym && !($params | self::IGNOROVAT_TURNAJ)) {
```
`|` má být `&`. `IGNOROVAT_TURNAJ` je nenulová konstanta, takže `$params | IGNOROVAT_TURNAJ` je vždy truthy a `!(...)` vždy `false`. **Větev se nikdy neprovede.**

Důsledek: když člen odhlásíš z jednoho kola turnaje (např. kapitán vyhodí hráče přes API `odhlasClena` v `aktivitaTym.php:55`), v ostatních kolech turnaje zůstává v `akce_prihlaseni`. Současně se z něj přes `AktivitaTym::odhlasUzivateleOdTymu` (1991) odebere `akce_tym_prihlaseni` a může se mu předat kapitán / zrušit tým — uživatel je tedy zároveň "není v týmu" a "je přihlášen na turnajovou aktivitu jako jednotlivec". Pokud byl posledním členem, tým se odstraní (cascade přes ORM), ale `akce_prihlaseni` na sourozeneckých kolech zůstane viset.

**Oprava:** `!($params & self::IGNOROVAT_TURNAJ)`.

### B.2 Kontrola zamčení týmu při přihlašování se nikdy nespustí

`model/Aktivita/Aktivita.php:2530` v `zkontrolujZdaSeMuzePrihlasitDoTymuNaTetoAktivite`:
```php
if (!(self::IGNOROVAT_ZAMCENI_TYMU)) {
    $tym->zkontrolujZeNeniZamceny();
}
```
Chybí `& $parametry`. Konstanta je nenulová → `!(...)` vždy `false` → `zkontrolujZeNeniZamceny()` se z přihlašování **nikdy nezavolá**.

Důsledek (web flow přes `prihlasovatkoZpracujBezBack`, tj. zadání `tymKod` z UI nebo akce `prihlasit`): hráč může zadat kód zamčeného týmu a backend ho přidá. API endpoint `aktivitaTym.php` má kontrolu jen ve větvích, kde explicitně dostane `idTymu` z formuláře (řádek 36). Cesta přes `tymKod` v `aktivitaAkce.php` ji obchází. Nastane stav: do zamčeného týmu přibude člen → tým je zamčený, ale má víc členů než v okamžiku zamčení; navíc obejde i finální min/max limit a `$team->getLimit()` (limit kontrola má v sobě IGNOROVAT_LIMIT, ale tady jde o IGNOROVAT_ZAMCENI).

**Oprava:** `if (!(self::IGNOROVAT_ZAMCENI_TYMU & $parametry))`.

### B.3 Atomicita přihlášení napříč koly turnaje

`prihlas()` (řádky 2398–2406) přihlašuje uživatele do dalších kol turnaje **před** `dbBegin()` hlavní aktivity:
```php
foreach ($dalsiAktivityTymu as $aktivitaTymu) {
    $aktivitaTymu->prihlas($uzivatel, $prihlasujici, $parametryDalsichAktivit);
}
// … dál teprve dbBegin / FOR UPDATE / INSERT pro hlavní aktivitu
```
Každé rekurzivní volání má vlastní `dbBegin/dbCommit`. Pokud k-té kolo selže (např. throw na kapacitě v `volno()` re-checku po FOR UPDATE), **přechozí kola už jsou commitnutá** a hlavní aktivita rovněž neproběhne (výjimka prošla skrz). Výsledek: uživatel je v `akce_prihlaseni` jen na podmnožině kol turnaje a na jiných ne; kontrola `maPrirazeneVsechnaKolaTurnaje` se v běžném flow při dalších přihlášeních nepouští (viz B.4), takže tento stav přežije.

**Oprava:** Přihlášení na celý turnaj musí být v jedné transakci. Buď
- jedna `dbBegin()` v kořenu `prihlas()` obalující jak kontroly, tak insert hlavní aktivity i rekurzivní volání, a vnitřní volání nesmí volat `dbBegin/Commit` (nebo používat savepointy / `dbBegin` reentrantní), nebo
- nahradit rekurzi explicitní logikou „naplánuj všechny inserty, potvrď v jediné transakci“.

Pozn.: `IGNOROVAT_KONTROLY` v rekurzi přeskakuje `volno()` re-check, ale FOR UPDATE + capacity check uvnitř recursive call se nevypíná — viz řádky 2419–2424. Kapacita každého dalšího kola se kontroluje v jejím vlastním zamknutém řádku. To je správně, ale teprve **po** prvním commitu, takže to zhoršuje výše uvedený split-brain.

### B.4 Tým existuje mimo transakci hlavní aktivity (rozpracovaný tým "uvízne")

V `prihlas()` se `AktivitaTym::zalozPrazdnyTym` (řádek 2369) volá ještě před hlavní transakcí. Pokud následně cokoli mezi tím a `dbCommit()` selže (kontroly, kolize, throw v rekurzi, ztráta DB spojení), tým zůstává v DB:
- bez záznamu v `akce_tym_prihlaseni` (rozpracovaný),
- bez záznamu v `akce_prihlaseni` (uživatel není přihlášen).

Funguje cleanup `smazRozpracovaneTymy` (cron, default 15 min). Po dobu prodlevy ale tým drží jeden slot v `team_kapacita` (`pocetTymuNaAktivite`) a brání jinému kapitánovi založit tým — viz B.6. Bug existuje a je popsán v `web/moduly/api/aktivitaTym.php:77` jako `todo(tym): tady musí stoprocentně dojít k přihlášení uživatele jinak není úspěch a pořád hrozí smazání týmu`.

**Oprava:** Vytvoření týmu (insert do `akce_tym`, `akce_tym_akce`, případně `akce_tym_prihlaseni` pro kapitána a `akce_prihlaseni` pro hlavní aktivitu) provést v jedné transakci. Při selhání pozdější fáze rollback uklidí i tým.

### B.5 Race condition: dva kapitáni založí tým nad `team_kapacita`

`AktivitaTymService::vytvorNovyTym` volá `zkontrolujMuzeZalozitTym` **bez** databázového locku (`teamRepository->pocetTymuNaAktivite` = běžný COUNT). Dvě paralelní HTTP requesty obě vidí `pocet < limit`, oba vloží `Team` + `addAktivita`. Limit `team_kapacita` se přečerpá.

Stejný problém u `zkontrolujVolnouKapacituVTymu` (počet členů týmu) — count + insert bez locku.

**Oprava:** Před countem zamknout řádek aktivity (`SELECT … FOR UPDATE` na `akce_seznam` nebo na nějakém deterministickém řádku) v stejné transakci jako insert, případně nasadit unikátní omezení / kontroly v DB schématu (např. trigger). Nejjednodušší řešení: přesunout `vytvorNovyTym` dovnitř transakce s FOR UPDATE na `akce_seznam` aktivitě (sjednocení s B.4).

### B.6 Race condition: kolize 4-místných kódů týmu

`AktivitaTymService::vytvorNovyTym` (i `pregenerujKodTymu`):
```php
$existujiciKody = … findAllByAktivita;
do { $kod = rand(1000, 9999); } while (in_array($kod, $existujiciKody, true));
```
Mezi výběrem kódu a `flush()` může jiný proces vložit tým se stejným kódem. `Team` entita pravděpodobně nemá unique index na `(id_aktivity, kod)`, takže DB to nevynutí (potřeba ověřit migrací). Pokud žádný UNIQUE neexistuje → tichý duplikát; `najdiPodleKodu` pak vrátí jeden ze dvou (`getOneOrNullResult` při dvou výsledcích shodí výjimku Doctrine).

**Oprava:** Přidat unique index na `(id_akce, kod)` v `akce_tym_akce` nebo přímo v `akce_tym` (podle schématu). Při vložení odchytit DB chybu a zopakovat generování.

### B.7 `prihlasUzivateleDoTymu` mimo transakci hlavní aktivity

`AktivitaTymService::prihlasUzivateleDoTymu` provede `em->persist + em->flush` (řádek 61–62) — vlastní transakce Doctrine. V `Aktivita::prihlas` (řádek 2429) je voláno **uvnitř** `dbBegin/dbCommit`, ale Doctrine používá vlastní DBAL connection. Pokud nejde o stejné připojení, Doctrine flush commituje sám sebe a `dbRollback` v catch větvi (řádek 2443) **nestačí** vrátit `akce_tym_prihlaseni` zpět.

Důsledek: pokud `INSERT INTO akce_prihlaseni` na řádku 2433 selže (např. duplicate key), `dbRollback` zruší jen vlastní legacy spojení, ale `TeamMemberRegistration` v Doctrine spojení už je commitnutý. Stav: uživatel je členem týmu, ale není přihlášen na aktivitu.

Je třeba ověřit, jestli Symfony Doctrine sdílí stejnou MariaDB connection s legacy `dbBegin` (`model/funkce/fw-database.php`). Pokud ano, problém je menší (řeší to společný transaction stack), pokud ne, jde o reálný split-brain.

**Oprava:** Buď unifikovat connection (použít stejné PDO pro legacy + Doctrine s ručním řízením transakcí), nebo přepsat `prihlas()` na čistou Doctrine transakci.

### B.8 `potvrdVyberAktivit`: částečně přiřazená kola bez kapitána

`web/moduly/api/aktivitaTym.php:69-78`:
```php
foreach ($idVybranychAktivit as $idVybraneAktivity) {
    $tym->pridejNaAktivitu($idVybraneAktivity);   // 1× flush per přidání
}
$aktivita->prihlas(...)                            // teprve teď přihlášení kapitána
```
Každý `pridejNaAktivitu` provede vlastní `flush`. Pokud cyklus napůl spadne nebo `prihlas` shodí Chybu (kolize, plno, …), v DB je tým s podmnožinou přiřazených kol a bez kapitána = trvale rozpracovaný. Cleanup ho po `CAS_NA_PRIPRAVENI_TYMU_MINUT` smaže (smaže jen prázdné týmy bez členů, což je OK), ale do té doby drží `team_kapacita` slot.

**Oprava:** Buď celý vyber + prihlas obalit transakcí, nebo přidávat aktivity v batch flushi a `prihlas` provést uvnitř stejné transakce.

### B.9 Nezveřejnění týmu po expiraci 72h (logická chyba)

`model/Aktivita/HromadneAkceAktivit.php:138-141`:
```php
if ($tym->jeVerejny()) {
    $tym->nastavVerejnost(true);  // nastaví na true týmy, které už true jsou
    $odemcenoTymovychAktivit++;
}
```
Smysl je opačný: po expiraci 72h se má neveřejný (= ještě nezamčený, ale už expirovaný) tým **zveřejnit** (per `docs/tymovky.md` "po 72h je tým zveřejněn/smazán"). Aktuálně to volá `nastavVerejnost(true)` jen na týmech, které už veřejné jsou. Tým, který byl jednou zamčený a poté ručně odemčený (odemčení nastavuje verejny=false implicitně, viz `setZamceny(false)` v `AktivitaTymService.php:190` — neresetuje verejny zpět, takže pokud byl `verejny=false` z předchozího zamčení, zůstává), po 72h zůstane neveřejný a nedojde k automatickému zveřejnění.

**Oprava:** `if (!$tym->jeVerejny()) { $tym->nastavVerejnost(true); }` (otočit podmínku).

### B.10 Dvě konstanty `CAS_NA_PRIPRAVENI_TYMU_MINUT` s jinou hodnotou

- `model/Aktivita/AktivitaTym.php:18` — `30`
- `symfony/src/Service/AktivitaTymService.php:20` — `15`

Aplikace používá pouze tu z `AktivitaTymService` (frontend `model/Aktivita/Program.php:54` i odpočet v `web/moduly/api/aktivitaTym.php:154` i `rozpracovaneTymyIds` default). 30 v `AktivitaTym` je mrtvý kód, ale nese riziko, že někdo příště sáhne po něm. Důsledky pro inkonzistenci dat nejsou, ale je to gotcha.

**Oprava:** Smazat konstantu z `AktivitaTym.php`, nebo ji přesměrovat na `AktivitaTymService::CAS_NA_PRIPRAVENI_TYMU_MINUT`.

### B.11 `AktivitaTym::najdi` se volá s navíc argumentem — chybí kontrola příslušnosti k aktivitě

`model/Aktivita/Aktivita.php:3157`:
```php
$tym = AktivitaTym::najdi($tymId, $aktivita->id());
```
Signatura je `najdi(int $idTymu): self`. Druhý argument se v PHP tiše ignoruje (žádná validace, že `$tymId` skutečně patří k `$aktivita`). Pokud uživatel POSTne `tymId` libovolného existujícího týmu (i z jiné aktivity), `prihlas` to nepoznáno použije. `pridejTymNaAktivitu` přidá aktivitu k týmu z cizí soutěže — výsledkem je tým spojený s aktivitami z více turnajů, což porušuje předpoklad „tým je vždy na aktivitách maximálně jednoho turnaje" (viz komentář v `AktivitaTymService::maPrirazeneVsechnaKolaTurnaje`).

**Oprava:** Buď v `najdi` přidat druhý parametr `int $idAktivity` a uvnitř ověřit, že tým je na této aktivitě, nebo ve volajícím kódu po `najdi` ověřit `in_array($aktivita->id(), $tym->idDalsichAktivit())`. Současně odstranit nepoužitý druhý argument tak, aby kompilátor / IDE chybu odhalily.

---

## C. Hlavní user-flow — detailní rozbor

Cíl: pohled běžného hráče/kapitána. Co dnes funguje, kde to může selhat „za běhu" (ne jen race / atomicita).

### C.1 Zakládání týmu — early-out na `prihlasen($uzivatel)`

`prihlas()` na řádku 2361:
```php
if ($this->prihlasen($uzivatel)) {
    return false;
}
```
Pokud kapitán byl už dřív přihlášen na aktivitu sám (nezapojen v týmu, individuální přihláška = legacy stav), volání `zalozPrazdnyTym` neproběhne a vrátí se `false`. UI si může myslet, že akce „zalozPrazdnyTym" tiše uspěla, protože odpověď nemá explicitní chybu. Pro týmovku je tohle nesmysl — kapitán musí být ve svém týmu, ne mimo něj.

**Detail:** `prihlasen()` kontroluje jen `akce_prihlaseni`, ne `akce_tym_prihlaseni`. Pokud tedy kapitán byl přihlášen "starou cestou" před nasazením tymovek, přejde tato kontrola úspěšně i pro tymovou aktivitu a založení týmu se vůbec neudělá.

**Oprava:** Pro tymové aktivity nezavírat early-out, ale rovnou hodit Chybu („už jsi na aktivitě individuálně, ozvi se infu"), nebo automaticky odhlásit a založit tým. Reálně to mohou řešit migrace, takže priorita je nízká.

### C.2 `priradTymNaAutomatickaKola` přidává aktivity bez kontroly stavu/kapacity

`AktivitaTurnaj.php:78-101`. Funkce přidá tým na aktivitu volajíc `tym->pridejNaAktivitu(idAktivity)` → `service.pridejTymNaAktivitu` → `addAktivita + flush`.

Co se nekontroluje:
- jestli aktivita má volnou týmovou kapacitu (`team_kapacita`),
- jestli má volnou kapacitu slotů (kapacita aktivity),
- jestli je aktivita publikovaná / přihlašovatelná,
- jestli `tym_kapacita` u jedné z aktivit už není plné (jiný tým by neměl být registrován v dalším kole).

Důsledek: když má kolo turnaje 1 jedinou aktivitu, ale ta je už plná týmů, `priradTymNaAutomatickaKola` ji přesto přiřadí. Kapacitní kontrola pak v `prihlas()` přijde až při skutečném přihlášení uživatele, ale pro tým už je do toho kola zapsaný v `akce_tym_akce`.

**Oprava:** V `priradTymNaAutomatickaKola` před `pridejNaAktivitu` zavolat `AktivitaTym::muzePridatDalsiTym(idAktivity)`. Pokud `false`, vrátit `false` (jako by kolo mělo více aktivit) a nechat UI zobrazit chybu, případně řešit explicitně.

### C.3 Přihlášení do týmu kódem — chybí ověření, že tým je na téže aktivitě

`prihlasovatkoZpracujBezBack` (Aktivita.php:3158-3160):
```php
} elseif ($tymKod) {
    $tym = AktivitaTym::najdiPodleKodu($aktivita->id(), $tymKod);
}
```
`najdiPodleKodu` filtruje podle `(idAktivity, kod)` — tj. k aktivitě patřící tým. ✅ OK.

Ale následný `prihlas($u, $u, tym: $tym)` projde `zkontrolujZdaSeMuzePrihlasitDoTymuNaTetoAktivite`, který kontroluje **jen kapacitu týmu**, nikoli to, že je hráč v jiném týmu jiné stejné aktivity. `AktivitaTymService::prihlasUzivateleDoTymu` v cestě, kde `idTymu != 0`, volá `zkontrolujZeNeniVJinemTymu` (řádek 41) → tam to ošetřeno je. ✅

Co se ale **nekontroluje**: kapacita slotů aktivity. `prihlas()` to udělá v transakci v kroku 8, takže OK.

Co tedy chybí: **stav zamčení týmu** (B.2) a **stav „rozpracovaný / bez všech kol turnaje" týmu**. Hráč může zadat kód týmu, jehož kapitán ještě nedokončil výběr kol — `prihlasUzivateleDoTymu` spadne, protože ve `prihlas` rekurze přes `dalsiAktivityTymu` přihlásí hráče i na další kola, ale tým je nemá kompletní → fail při insertu na nedostupné kolo. Stav po failu: viz B.3.

**Oprava:** v `zkontrolujZdaSeMuzePrihlasit` před vstupem do transakce zkontrolovat `tym->jeRozpracovany() && tym->jeSoucastiTurnaje() && !tym->maPrirazeneVsechnaKolaTurnaje()` → odmítnout s vysvětlením („tým ještě není připraven, vyčkej na kapitána").

### C.4 `idDalsichAktivit` pro nový tým je prázdné při hlavním insertu

V `prihlas()` při zakládání týmu (`$zakladaTym = true`):
1. `zalozPrazdnyTym` proběhne, do `team.aktivity` přidá hlavní aktivitu.
2. `priradTymNaAutomatickaKola` může přidat další.
3. `dalsiAktivityTymu = tym->idDalsichAktivit($this->id())` — vyloučí hlavní aktivitu a vrátí ostatní.
4. Rekurzivně `aktivitaTymu->prihlas(...)` → každá s vlastní transakcí (B.3).

Pokud `priradTymNaAutomatickaKola` přidá tým na N kol a rekurze fail-uje na (k+1)-tém, atomicita je porušená. Doplněk B.3.

### C.5 `prihlas` na rekurzivně přihlašovaných koturnajových aktivitách — bypass kontrol

Recurse: `parametryDalsichAktivit = $parametry | IGNOROVAT_TURNAJ | IGNOROVAT_KONTROLY`.

`IGNOROVAT_KONTROLY` na začátku `zkontrolujZdaSeMuzePrihlasit` provede early-return (řádek 2598) — žádná kontrola GC přihlášení, kolize aktivit, brigádnik, kapacita ani prihlasovatelna. To je záměr (předtím proběhly), ale kombinace s B.3 znamená: pokud má uživatel kolizi v dalším kole turnaje (např. má v tom termínu jinou aktivitu), `prihlas` ji **ignoruje**. Po commitu je v `akce_prihlaseni` na dvou aktivitách ve stejný čas — `Uzivatel::maKoliziSJinouAktivitou` v jiných místech aplikace pak najde kolizi, kterou tady ignorujeme.

V hlavním kontroleru kolize byla ošetřená (`zkontrolujKolidujeSAktivitouUzivatele` se zavolá pro hlavní aktivitu na řádku 2611). Ale ne pro rekurzivně přihlašovaná kola turnaje. Pokud má hráč v kolizním čase přihlášenou jinou aktivitu, hlavní kolo se odmítne, ale to je **jiné kolo**, ne nutně to, kde je kolize — kolize může být v 3. kole, hlavní (přihlašovaná) je 1. kolo. Pak by se přihlásit nemělo.

**Oprava:** Ve výchozí kontrole `zkontrolujZdaSeMuzePrihlasit` projít i kontroly pro kola turnaje (každé kolo má vlastní kapacita + collision). Logiku má `zkontrolujPrihlaseniNavazujicichAktivit`, ale je volaná **pouze pokud `$navazujiciAktivity` neprázdné** — ve volání z `prihlas()` se předává **prázdné pole**, ne `$dalsiAktivityTymu`.

Konkrétně `Aktivita::prihlas` (řádek 2388-2396) volá `zkontrolujZdaSeMuzePrihlasit(... $tym, $dalsiAktivityTymu)` se 7 argumenty:
```php
$this->zkontrolujZdaSeMuzePrihlasit(
    $uzivatel,
    $prihlasujici,
    $parametry,
    $jenPritomen,
    $hlaskyVeTretiOsobe,
    $tym,
    $dalsiAktivityTymu,
);
```
Signatura je s 8 argumenty — 7 je OK, 8. `$navazujiciAktivity = []` má default. Vypadá to, že **`$dalsiAktivityTymu` se posílá na pozici `$tym`?** Ne — pozice 6 je `$tym`, pozice 7 je `$navazujiciAktivity`. Takže `$dalsiAktivityTymu` se v tomto volání předává jako `$navazujiciAktivity`. ✅

Ale ve `zkontrolujPrihlaseniNavazujicichAktivit` (řádek 2549-2587) se v cyklu volá `navazujiciAktivita->zkontrolujZdaSeMuzePrihlasit(...)` **bez** `$navazujiciAktivity` argumentu (řádek 2581 končí `$tym,`, 8. arg chybí). Tj. rekurze jen do hloubky 1, žádné tranzitivní navazující aktivity dál. To je v praxi OK pro turnaj (jediná úroveň).

Hlavní problém zůstává: kontrola kolize aktivit pro každé kolo se sice **udělá** (`zkontrolujKolidujeSAktivitouUzivatele`), ale … jen pro tým-aktivity navíc se odpouští `IGNOROVAT_LIMIT` (řádek 2569). Ostatní kontroly proběhnou ✅.

**Závěr:** kolize se v kontrolní fázi zachytí, atomicita stále chybí (B.3). `IGNOROVAT_KONTROLY` v rekurzivním `prihlas()` je tedy *po* validační fázi.

### C.6 Kapacita slotů (`volno()`) v re-checku po FOR UPDATE — týmová specifika

Recheck v transakci (řádek 2421):
```php
if (!IGNOROVAT_LIMIT && volno() !== 'u' && volno() !== pohlavi()) {
    dbCommit(); throw 'plno';
}
```
Pro týmovku to znamená: i když kapacita aktivity = 40 (5 týmů × 8 členů), poslední tým má těsně před sebou jiný tým, kde místo některému nestihl člen rezervovat. **Týmovka rezervuje sloty per člen**, ne per tým. Tým založený s 0 členy nezabírá `kapacita`, jen `team_kapacita`. To je OK.

Ale: pro **rozvětvený turnaj** kapitán může vybrat různé aktivity (kola). Aktivita má `kapacita` = počet slotů. Když kolo má více aktivit, různé týmy můžou volit různé. Recheck v `prihlas()` kontroluje hlavní aktivitu, ne ostatní rekurzivně volané. Rekurze ale taky FOR UPDATE má (řádky 2414–2424 — **každé** volání `prihlas` to dělá), tj. atomicita per řádek aktivity je OK.

Stále zůstává: pokud jedno z volání `prihlas` v rekurzi spadne na „plno", commitované předchozí volání zůstává — viz B.3.

### C.7 Volné/zaplněné aktivity vs. týmové sloty

`volno()` kontroluje `kapacita - počet_přihlášených`. U týmovky se `kapacita` typicky nastavuje jako násobek `team_max * team_kapacita`, ale to není vynucené. Pokud je `kapacita = 30` a `team_max=10, team_kapacita=5`, mohlo by se přihlásit 5 týmů s plnými 10 lidí ⇒ 50 přihlášených, ale `kapacita=30` to dříve zablokuje. Vznikne stav „některé týmy jsou „částečně přihlášené" a tým je zamčený, i když nemá min. počet členů". Není to inkonzistence, ale gotcha pro org.

**Oprava (validace):** při ukládání aktivity ve vědom kontroly: `kapacita >= team_max * team_kapacita`.

### C.8 `prihlasen()` se vyhodnocuje na cache před transakcí

`prihlas()` na řádku 2361 (`if ($this->prihlasen($uzivatel)) return false`) čte z cache. Při paralelních requestech jeden zachytí, druhý projde do transakce. V transakci `INSERT INTO akce_prihlaseni` spadne na duplicate key — `akce_prihlaseni` má `UNIQUE KEY (id_akce, id_uzivatele)` (`migrace/000.php:1309`), takže DB stav zůstane konzistentní. ✅ Stav DB je tedy v pořádku, ale uživatel dostane generickou DBException místo přehledné hlášky („už jsi přihlášen").

**Oprava:** V transakci po `FOR UPDATE` znovu zkontrolovat `prihlasen($uzivatel)` (po `refresh()`) a vrátit early-out / smysluplnou Chybu. Stejně tak odchytit duplicate key error.

---

## D. Nestandardní uživatelské cesty

Cesty, které dnes nejsou hlavním flow, ale uživatel je může vyvolat. Nižší priorita než C.

### D.1 Vícekolové aktivity s více aktivitami v jednom kole

Per zadání: tohle je explicitně mimo dnes řešený scope, řešit ručně. Pro úplnost sem patří:
- `priradTymNaAutomatickaKola` vrátí `false`, kapitán pak `potvrdVyberAktivit` v UI (B.8).
- Atomicita výběru kol + přihlášení kapitána chybí (B.8).
- Změna výběru kol po prvním přihlášení člena není ošetřena: `pridejNaAktivitu` jen přidá bez kontroly, že tým je rozpracovaný / že nemá v kole už jinou aktivitu. Pokud kapitán omylem zavolá `potvrdVyberAktivit` znovu, do `akce_tym_akce` přibudou další záznamy. **Odhlásit toho hráče z předchozí aktivity v kole nikdo neudělá.**

### D.2 Kapitán se přihlásí na hlavní aktivitu, kapitán pak chce kolo přemapovat

Není podporováno UI. Ale `pridejNaAktivitu` je veřejná metoda — kdokoli s přístupem do kódu / API endpointu pustí přidání. Kontroly chybí (D.1).

### D.3 Sledování (`prihlasenJakoSledujici`) na týmovou aktivitu s více koly

`docs/tymovky.md:62-63` říká: hráč není odhlášen ze sledování pokud ve všech kolech může sledovat aspoň jednu aktivitu. Aktuálně `odhlasZeSledovaniAktivitVeStejnemCase` (řádek 2058) pravděpodobně odhlašuje pouze na základě časové kolize. Implementace logiky pro vícekolové není dohledaná (TODO v dokumentu). Mimo scope tohoto rozboru, pouze poznámka.

### D.4 Hráč se přidá do týmu kódem, ale tým ještě neměl všechna kola vybraná

Viz C.3 — kontrola chybí. UI pravděpodobně nedovolí (kapitán musí dokončit výběr před zveřejněním kódu), ale BE to nevynucuje. Doporučená oprava v C.3.

### D.5 Hráč zruší přihlášení individuálně z jednoho kola turnaje

`Aktivita::odhlas` na turnajové aktivitě — dnes (kvůli B.1) odhlásí pouze z konkrétního kola. UI by mělo nabízet odhlášení z celého turnaje, ale BE to neposkytne ani jako alternativu, dokud není B.1 vyřešený.

### D.6 Hráč je v `akce_prihlaseni` jako `POZDE_ZRUSIL` (storno) a chce se přidat do týmu

`zrusPredchoziStornoPoplatek` se volá v `prihlas()` po INSERTu (řádek 2439). Smaže `POZDE_ZRUSIL` záznam v `akce_prihlaseni_spec`. Pro týmovku to funguje shodně. ✅

### D.7 Tým má v `akce_tym_akce` aktivity z jiného turnaje (importem nebo bugem)

`maPrirazeneVsechnaKolaTurnaje` (`AktivitaTymService.php:240-286`) určuje turnaj podle **první** aktivity, která má `tournament`. Pokud tým omylem (např. přes adminský bug v B.11 / D.2) má aktivity z více turnajů, vyhodnotí jen první nalezený. Cizí aktivity zůstávají přiřazené, `idDalsichAktivit()` je vrátí, rekurzivní `prihlas()` na ně přihlásí uživatele. Nastane reálný cross-tournament cross-registration.

**Oprava:** V `pridejNaAktivitu` ověřit, že nově přidávaná aktivita je z téhož turnaje jako stávající aktivity týmu (nebo žádný turnaj na obou stranách).

### D.8 Změna kapitána po přihlášení dalších členů — kapitán mimo `akce_tym_prihlaseni`

`AktivitaTym::nastavKapitana` ověří, že nový kapitán je členem (`teamMemberRegistrationRepository->findByUzivatelAndTeam`). ✅ Ale původní kapitán **zůstává** v `akce_tym_prihlaseni` (pokud byl) i po předání. Kdo byl ne-kapitán, nemá pravomoci, ale stále člen — žádoucí stav. ✅

Pozor: kapitán nemusí být členem (`AktivitaTym.php:44` komentář). Při zakládání `vytvorNovyTym` se kapitán přidá pouze jako `kapitan_id` v `akce_tym`, **ne** do `akce_tym_prihlaseni`. Tj. čerstvě založený tým má 0 členů (`pocetClenu=0`, `jeRozpracovany=true`). První member-row vznikne až `Aktivita::prihlas` → `prihlasUzivateleDoTymu` v transakci.

Důsledek: pokud `prihlas` selže mezi `zalozPrazdnyTym` a `prihlasUzivateleDoTymu` (kontroly, kolize, ...), kapitán je v týmu jen jako `kapitan_id`, není v `akce_tym_prihlaseni` ani v `akce_prihlaseni`. Cleanup `smazRozpracovaneTymy` smaže tým po 15 min. Mezitím tým drží slot v `team_kapacita`. Viz B.4.

### D.9 Snížení limitu týmu pod počet členů

`AktivitaTymService::nastavLimitTymu` ověří `if ($limit < $pocetClenu)` → throw. ✅ Ale neověří, že nový limit je >= `team_min` aktivity. **Ano ověří**: řádek 504-507. ✅

### D.10 Pregenerace kódu týmu

`pregenerujKodTymu` (`AktivitaTymService.php:370-394`) má stejnou race condition jako B.6 (collision). Frontend ukáže nový kód, ale kdokoli, kdo má starý kód, ho už nepoužije. ✅ funkčně, ale duplikát kódu může vzniknout.

---

## E. Admin

Nejnižší priorita per zadání. Záznamy:

### E.1 `admin/scripts/modules/aktivity/_Import/ImporterUcastnikuNaAktivitu.php`

Importer volá `Aktivita::prihlas` přímo. Pro tymové aktivity to založí tým per uživatel (každý import řádek = nový tým). Žádná logika spojení existujícího týmu / vícenásobných uživatelů do jednoho. TODO v `docs/tymovky.md:376` ("Rozšíření stávajícího importeru o podporu týmů"). Mimo aktuální scope.

### E.2 Admin "rozebrat tým" v `admin/scripts/modules/aktivity/tymy.php`

```php
AktivitaTym::najdiPodleKodu($idAkce, $kodTymu)->rozebratTym();
```
`rozebratTym` volá `em->remove($team)` → CASCADE smaže `akce_tym_akce` i `akce_tym_prihlaseni`. Ale **nevolá `Aktivita::odhlas`** pro členy → `akce_prihlaseni` zůstává! Členové týmu jsou pořád zapsaní jako účastníci aktivity, jen mimo tým. To může být záměr (admin ručně odhlásí), ale je to inkonzistence: pro hráče vypadá tým "rozpadl se" a oni jsou stále přihlášení.

**Oprava:** `rozebratTym` před `em->remove` projít členy a `aktivita->odhlas($clen, $admin, ...)` na všech aktivitách týmu. Případně přidat parametr „smazatPouzeTym vs. odhlasitVse".

### E.3 Admin přihlašování přes `program-uzivatele.php`

TODO v `docs/tymovky.md:316`: „Admin program posílá `id_uzivatele` vybraného uživatele do API". Aktuálně admin akce „přihlásit účastníka jménem orga" je řešená přes `uPracovni` (`web/moduly/api/aktivitaTym.php:17`). Funkčně OK, jen chybí oprávnění/role kontrola.

### E.4 Šéf infa — odemčení týmu

API endpoint `odemkni` v `aktivitaTym.php:97-101` má kontrolu `Role::SEF_INFOPULTU`. ✅ Admin verze přes `tymy.php` zatím chybí (TODO v `docs/tymovky.md:244-246`). Nízká priorita.

### E.5 Admin nemůže editovat zamčený tým

Výslovný TODO v `docs/tymovky.md:322-325`. Aktuálně `aktivitaTym.php:36` volá `$tym->zkontrolujZeNeniZamceny()` u všech mutujících akcí (kromě cesty přes `akce` který nezačíná `idTymu`), tj. ani admin (přes `uPracovni`) nemůže zamčený tým upravit. To je v rozporu se záměrem.

**Oprava:** Pro role šéfa infa rozhodit kontrolu na `if (!$jeAdmin) tym->zkontrolujZeNeniZamceny()`.

---

## F. Doporučené pořadí oprav (z pohledu hlavního flow)

1. **B.1** — fix `&` místo `|` v `odhlas()` (jeden znak, vysoká hodnota).
2. **B.2** — doplnit `& $parametry` do kontroly zamčení v `zkontrolujZdaSeMuzePrihlasitDoTymuNaTetoAktivite`.
3. **B.3 + B.4 + B.7 + B.8** — sjednotit transakční hranice. Návrh:
   - Vytvoření týmu (insert akce_tym + akce_tym_akce + případné `pridejNaAktivitu` pro automatická kola) provést uvnitř `dbBegin / dbCommit` (jediné spojení – Doctrine i legacy musí sdílet PDO nebo je drženo přes savepointy).
   - Rekurzivní `prihlas()` na další kola turnaje sjednotit do jediné transakce (ne 1 transakce per kolo).
   - Při exception → `dbRollback()` + `em->rollback()` musí vrátit i `Team` a `TeamMemberRegistration`.
4. **B.5 + B.6** — `SELECT … FOR UPDATE` na `akce_seznam` před countem týmů + count kódů. UNIQUE index na `(id_akce, kod)` v kombinované tabulce (akce_tym_akce nejspíš je nejvhodnější doplnit unique key přes view nebo přesunout `kod` do akce_tym_akce; nejjednodušší = generovat kód až po insertu a opakovat při unique violation z DB chyby).
5. **B.9** — otočit podmínku v `vyresExpirovaneTymyHromadne`.
6. **B.10** — sjednotit konstantu `CAS_NA_PRIPRAVENI_TYMU_MINUT`.
7. **B.11** — přidat verifikaci `tym ↔ aktivita` ve volání `najdi`. Odstranit nepoužívaný 2. arg.
8. **C.2** — ověřit kapacitu kola v `priradTymNaAutomatickaKola`.
9. **C.3** — odmítnout přihlášení do týmu, který nemá kompletní kola turnaje.
10. **C.5** — pro kola turnaje volat plnou validaci (kolize) i v ne-hlavní aktivitě, ne jen `IGNOROVAT_KONTROLY` rekurzivní fast-path.
11. **C.7** — guard `kapacita >= team_max * team_kapacita` při ukládání aktivity.
12. **C.8** — re-check `prihlasen()` v transakci po `FOR UPDATE`.
13. **D.7** — guard same-tournament v `pridejNaAktivitu`.
14. **E.2** — `rozebratTym` → odhlásit všechny členy ze všech aktivit týmu předtím, než se tým smaže.
15. **E.5** — admin (šéf infa) může editovat zamčený tým.

---

## G. Otevřené otázky / k ověření

- Zda Doctrine connection a legacy `dbBegin` sdílí stejné PDO (klíčové pro B.7).
- Zda existuje ON DELETE CASCADE od `akce_tym → akce_tym_prihlaseni` a `akce_tym → akce_tym_akce` — z migrací vypadá že ano (`2026-03-23-145000_…` má `ON DELETE CASCADE` na `akce_tym_akce`, `2026-03-11-tabulka-akce_tym.php` na `akce_tym_prihlaseni` ne — má jen FK bez ON DELETE → smazání týmu při neodstraněných členech selže). **Ověřit a sjednotit.**
- Zda se po odemčení (`setZamceny(false)`) má resetovat i `verejny` (aktuálně se nemění). Souvisí s B.9.
- Zda `prihlasenJakoSledujici` má pro tym-aktivity odhlašovat watchlist na všech kolech (aktuálně jen ne-kolize per aktivita).






