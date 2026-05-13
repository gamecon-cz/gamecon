
# TODO:

PRIO
  - ignorovat reporty/exporty/importy a další porty, jít čistě po logice program v adminu taky neřešit
  - [ ] trello 1
    - [X] Nedalo to na výběr ze dvou aktivit, rovnou to přihlásilo na první
    - [X] Kapitán nevidí důvod proč ho to nechce přihlásit (mě by vidět že má kolizi s jinou už přihlášenou aktivitou)
    - ~~[ ] Možná přejmenovat Kód týmu prostě na PIN ?~~
    - [X] Není podpora export a import - minimum je vyřadit podporu pro teamovky z exportu a importu
      - [ ] potřeba dotestovat
    - [X] Zvýraznit chybějící členy týmu do nutného minima
    - [ ] Nefunguje přihlášení na týmy přes menu Aktivity - týmová aktivita v tomto přehledu by taky měla umožnit přihlášení
    - [ ] Reporty zatím nejsou upravené - řešit jen nejnutnější, zeptat se na Discordu davu co potřebují nutně
    - [ ] Když má kapitán k dispozici jedinou aktivitu, na kterou se může přihlásit, tak ho to na ní přihlásí automaticky a skrytě - raději bych mu nechal přehled možných aktivit (takže tu jednu), aby viděl, co mu je vybráno
  - [ ] trello 2
    - [ ] Kontrola chyb nic nenašla, ale aktivovaná aktivita mizí z programu (subpage /program).
    - [ ] 1. Po přihlášení to spustí aktivitu a nabídne mi se přihlásit jako kapitán, ale tlačítko „Přihlásit se jako kapitán“ vyhodí chybovou hlášku, podle které jsem již přihlášen. (Přitom aktivita je prázdná). Po Ctrl+F5 mě to přihlásí jen na druhou aktivitu.
    - [ ] 2. Tlačítko smazat tým nefunguje v rozhraní výše nefunguje. Píše to „Neznámá akce“.
    - [ ] 3. Po zadání kódu týmu za někoho jiného to daného člověka nepřipojí do týmu ani po Ctr+F5.
    - [ ] 4. Ani po zveřejnění týmu se do něj nejde připojit, F5 ani Ctrl+F5 nepomáhá.
  - [ ] skrýt odemknout z ui na webu
  - [ ] hezké UI
  - [ ] názvy týmů
  - [ ] termíny týmu
  - [ ] odladit UI
  - [ ] háže chybové hlášky ?
  - [ ] počet účasníků
  - [ ] bez poslání kódu nemá vytvářet nový tým
  - [ ] týmový modal v /aktivita
  - [X] checkbox na smazání do amdinu
  - [ ] rebase

# Celkové stavy:
  Stav určuje:
    - týmy
    - přihlášením hráčů na aktivity
    - přihlášením týmu na aktivity
    - přihlášením hráčů v týmu
    - aktuální čas


# Stavy týmu:
  - rozpracovaný R
    - tým má přiřazeno alespoň jedno kolo aktivity (ale nemusí mít všechna)
      - "přiřazeno kolo" = tým→aktivita vazba existuje
    - žádný hráč není přihlášen na žádnou aktivitu týmu
    - žádný hráč není přihlášen v týmu
    - není starší než 30 min. (automaticky smazán po 30 min)
    - vždy neveřejný
  - připravený P
    - kapitán je přihlášen v týmu
    - všichni členové týmu jsou přihlášeni na všechny aktivity týmu
    - má přiřazenu právě jednu aktivitu pro každé kolo turnaje
    - čas expirace je nastaven a je v budoucnu (nastavuje se při přechodu R→P, výchozí 72h od přechodu)
    - může být veřejný/neveřejný
  - zamčený Z
    - immutable pro běžné uživatele
    - vždy neveřejný (nastaveno automaticky při zamčení)
    - nelze měnit členy/kapitána/limit
    - může být odemčen pouze šéfem infa nebo systémem (odhlášení neplatiče)
  - expirovaný E (tohle je asi nezávýslý stav tak nemá smysl ho tu mít takhle. Skoro ve všech směrech je expirovaný tým to samé co připravený)
    - čas expirace v minulosti
    - nejde nastavit jako neveřejný podkud je expirace zveřejnění
  - smazaný S
    - tým neexistuje
    - nastane z R (30 min timeout nebo rozpuštění), z E (podle nastavení aktivity), nebo adminem

## akce
  RP: vybrání aktivit pro všechna kola + přihlášení kapitána: R → P
    - čas expirace se nastaví na NOW + 72h
  PZ: zamčení připraveného týmu (min kapacita naplněna): P → Z
  EZ: zamčení expirovaného týmu (min kapacita naplněna): E → Z
    - záměrné — umožňuje zachránit tým po expiraci před smazáním
  ZP: odemčení zamčeného týmu (pouze šéf infa): Z → P
    - čas expirace se resetuje na NOW + 72h
  ZP (systém): odhlášení neplatiče ze zamčeného týmu: Z → P (nebo S pokud byl poslední člen)
  PE: expirace nepřipraveného zamčení: P → E (vypršení času expirace)
  P: prodloužení expirace

  RS, PS, ES: poslední člen opustí tým: R/P/E -> S
    - nebo kapitán opustí tým jako poslední člen
  RS: smazání rozpracovaného týmu: R → S
    - automaticky po 30 min
  ES: smazání expirovaného týmu: E → S
    - automaticky podle nastavení aktivity po expiraci
    - nebo adminem

# Stavy hráče v týmu
  - je kapitán (ale ještě není v týmu)
    - stav pouze během R fáze
    - kapitán tým vlastní ale není přihlášen na aktivity
    - nemůže zakládat další týmy pokud už je kapitán na aktivitě z turnaje
  - je kapitán a je v týmu
    - přihlášen na všechny aktivity týmu
    - platí od P fáze
  - je člen (v týmu, není kapitán)
    - musí být přihlášen na všech aktivitách týmu
      - prihlasen, prihlasenADorazil, dorazilJakoNahradnik, prihlasenAleNedorazil, pozdeZrusil
  - není v týmu
    - nemůže být na týmové aktivitě nijak přihlášen

# nevalidní stavy a eskalace
  - stavy hráčů
    - hráči jsou v týmu ale nejsou přihlášeni na všechny aktivity
    - hráč je na týmové aktivitě ale není v týmu nebo je ve více týmech
  - stavy týmu
    - tým má přihlášené lidi ale nemá přihlášeného kapitána
    - tým má hráče ale nemá všechna kola turnaje (nebo má v jednom kole více aktivit)

# základní flow
  - zakládání a přihlašování týmu:
    - kapitán otevře nastavení týmů a tam klikne založit tým
    - vybere pro svůj tým termíny
      - může být přeskočeno pokud v žádném kole není víc termínů na výběr
      - současně není potřeba pro Lkd turnaj, ten je lineární (je to v podstatě jako dva samostatné turnaje)
    - sám se přihlásí na aktivitu
      - je potřeba pouze v moment kdy by nešel přihlásit protože má v čas turnaje jinou aktivitu
    - tým je připraven
      - pokud od založení není tým připraven do 30min. pak je smazán
        - důrazné varování v UI
    - všichni členové týmu vidí kód který pošlou kamarádům aby se mohli přihlásit
    - pro přihlášení do tohoto týmu vloží hrač kód co dostal od člověka který v týmu už je
    - kapitán může upravit limit týmu
      - _??? je tahle featura opravdu potřeba když stejně musí být tým zamčený aby byl hotový taky může být naintuitivní pro používání_
      - limit týmu je kapitánem nastavená hodnota kolik může být celkem v týmu lidí
      - lze nastavit v rozmezí týmová kapacita min-max
    - uzamčení týmu
      - tým lze uzamknout pokud má alespoň min kapacitu
      - musí být manuálně uzamčen do 72h
        - výrazný vizuální indikátor zbývajícího času
        - po 72h je tým zveřejněn/smazán (podle nastavení aktivity)


  - posílání mailu při:
    - v moment odemčení týmu
    - zbývá 24h ze 72h do doby kdy musí kapitán zamknout tým

  - kapitán může
    - předat kapitána
    - vyhodit (a odhlásit) člověka z týmu
    - odhlásit sám sebe (kapitán se předá někomu jinému nebo pokud je poslední tak se tým rozpustí)
    - změnit limit týmu

  - zamčený tým
    - nelze nijak dál editovat (vypne odhlašování/přihlašování předávání kapitána etc.)
    - odemčení
      - pouze šéf infa nebo automaticky odhlášením neplatiče
      - při odhlášení neplatiče odemkne a vyhodí neplatiče
      - při odemčení běží limit 72h znova
    - každý zamčený tým je automaticky nastavený jako neveřejný

  - týmová aktivita:
    - týmová kapacita - koluk může být na aktivitě přihlášeno týmů
    - min a max kapacita týmu kolik musí mít každý tým lidí

  - vícekolové aktivita/turnaj:
    - aktivita může být součástí turnaje, pak musí mít určené ve kterém kole se nachází
    - aby mohl být hráč přihlašený na vícekolovou aktivitu tak musí mít v každém kole přihlášenou právě jednu aktivitu
    - přihlašování na všechny kola se provádí jako jedna akce
      - v případě týmovek je výběr termínů před přihlášením kapitána do týmu
      - pro netýmové se nepočítá s výběrem z více možností
    - různé kola aktivity můžou mít různou kapacitu

  - veřejný tým
    - tým který je zobrazený v seznamu týmu a dá se do něj přihlásit bez kódu

  - anonymizace pro přihlášené lidi - zobrazit pouze přezdívku nebo jak to je

  - sledování týmové aktivity
    - ano, pošle email když se uvolní místo pro přidání týmu
    - sledování týmové aktivity s více koly
      - hráč není odhlášen od sledování vícekolové aktivity pokud ve všech kolech může sledovat alespoň jednu aktivitu. (pokud by se udělalo místo tak by se mohl přihlásit bez odhlášení jiné aktivity)

  - program v adminu může dělat změny i mimo pravidla
    - jako asi editovat zamknutý tým nebo ho alespoň odemknout
    - přidávat lidi nad max týmu ?

  - prezenčky
    - todo

  - otázky:
    - je potřeba výběr kola pro netýmové aktivity ?
      - výběr kola se provádí v ui pro tým, pokud by netýmová aktivita potřebovala taky výběr kola tak by bylo potřeba dovymyslet
      - jinak řečeno může nějaká netýmová vícekolová aktivita mít v jednom kole více aktivit ?
    - je potřeba upravování limitu aktivity ?
      - stejně aktivitu zamknu když mám lidi co chci takže limity jsou jen kroky navíc
      - limit dává asi možná trochu smysl pro veřejné týmy co chcou hrát v menším počtu
    - co vše by mělo jít dělat přes admin ?
      - alespoň vše co by mohl normálně dělat kapitán
      - šef infa může odemknout tým
    - Má se zobrazovat kdo je v cizím veřejném týmu, nebo jen počet členů?
      - asi s anonymizací v pohodě

  - todo
    - importy
    - reporty

## soupist zákládních testovacích scénářů, popis fungování nového systému:

Turnajové
- lineární přihlášení
  - prerekvizity
    - více aktivit ve stejném turnaji kde každé kolo má právě jednu aktivitu
    - všechny aktivity jsou alespoň publikované a jedna aktivita je přihlašovatelná
  - pro přihlášení uživatel klikne na tým v programu
  - v ui založí vlastní tým
  - od teď je v týmu a může ho jako kapitán editovat a sdílet kód
- přihlášení dalšího účastníka
  - prerekvizity
    - existuje tým který není zamčený a má volné místo pod limitem
    - uživatel není ještě v žádném týmu na aktivitě
  - v rozhraní týmů u aktivity
  - uživatel zadá kód týmu a přihlásí se
- rozvětvené přihlášení
  - prerekvizity
    - stejně jako v lineární přihlášení
    - navíc bude existovat kolo co má více aktivit k výběru
  - v ui týmu na aktivitě
  - založit tým
  - místo týmu se zobrazí nejdříve výběr možných aktivit
  - kapitán vybere aktivity pro každé kolo jednu a potvrdí výběr
  - po potvrzení výběru se kapitán automaticky přihlásí do týmu
- kapitán už má kativitu v čase turnaje
  - pokud při vytváření / vyběru aktivit týmu se nemůže přihlásit na všechny aktvity
  - zobrazí se tlačíko přihlásit kapitána kterým se může sám přihlásit
- smazání rozpracovaného týmu
  - kdykoliv není kapitán přihlášený nebo nejsou vybrané aktivity pro tým, tak jde tým smazat
- akce na týmu
  - přegenerovat kód
    - při přegenerování se vytvoří nový kód a starý přestane fungovat
  - snížení zvýšení limitu
    - limit jde nastavit mezi min/max aktivity
    - tým nemůže přihlásit dalšího hráče pokud je limit plný
  - odebrat hráče s týmu
  - předat kapitána
  - zamknout tým
    - tým jde zamknout pouze pokud má alespoň min kapacitu
    - zamknutý tým nejde už nijak editovat
- editace týmu z adminu
  - po výběru uživatele z týmu by měl v adminu být tým editovatelný jako by byl ten uživatel přihlášený
- kontrola jaké jdou vidět aktivity v programu v různých podmínkách


## Určení stavu týmů a detekce týmů a hráčů v nevalidních stavech v admin ui
  - [ ] určení stavu týmu
    - tým je připravený pokud má na sobě přihlášené hráče
  - [ ] tlačítko kontrola stavu týmů v adminu
    - v záložce týmy na aktivity bude tlačítko které prohledá všechny týmy a hráče týmech a zkontroluje jestli zde neexistují žádné z následujích chyb a vypíše pro každý typ chyby jaké týmy jsou v tomto chybném stavu a pokud je to možné tak nabídne řešení
    - [ ] týmy
      - [ ] tým nemá ani jenu aktivitu
        - vypsat takové týmy
        - [ ] eskalace tlačítko smazat
      - [ ] připravené týmy
        - [ ] nemá přihlášeného kapitána
          - [ ] vypsat čelny s tlačítkem předat kapitána
          - [ ] nebo tlačítko předat náhodně
        - [ ] nemá ve všech kolech turnaje právě jednu aktivitu
          - [ ] vypíše kola aktivit turnaje
    - [ ] hráči
      - [ ] hráči jsou v týmu ale nejsou přihlášeni na všechny aktivity
  - [ ] otestovat detekci inkonzistencí

## Základní přihlašovací flow
- [X] Kapitán může založit tým přes nastavení týmů v UI
  - [X] API endpoint `zalozPrazdnyTym` v `aktivitaTym.php`
  - [X] Preact UI tlačítko v `NastaveniTymuView.tsx`
  - [X] `AktivitaTym::zalozPrazdnyTym()` — generuje 4-místný kód, nastaví kapitána
- [X] Přihlášení kapitána na aktivitu
  - [X] `Aktivita::prihlas()` přijímá `?AktivitaTym $tym` parametr
  - [X] Ošetření chyby přihlášení po založení týmu — pokud selže přihlášení, tým visí prázdný (`aktivitaTym.php:77`)
    - [X] pokud je aktivita týmová
      - [X] nejdříve vždy založit tým
      - [X] automaticky přiřadit všechny aktivity týmu pokud má každé kolo pouze jednu možnou aktivitu
      - [X] automaticky přihlásit kapitána
    - [X] výběr kola týmu a přihlášení kapitána do týmu
      - [X] ui mock
      - [X] api
    - [X] sjednocení handlingu chyb z BE
- [X] ošetření práce s nepřipraveným týmem
- [X] Všichni členové vidí kód pro pozvání dalších hráčů
  - [X] API vrací `kod` v GET response
  - [X] UI zobrazuje kód v `NastaveniTymuView.tsx`
- [X] Hráč se může přihlásit do týmu zadáním kódu
  - [X] `AktivitaTym::najdiPodleKodu()` + `prihlasUzivateleDoTymu()`
  - [X] UI formulář pro zadání kódu v `PrihlaseniTymu`
- [X] Tým se automaticky smaže po 30 minutách pokud nebyl dokončen
  - [X] `AktivitaTym::rozpracovaneTymyIds()` a `smazRozpracovaneTymy()` — logika existuje
  - [X] Cron job / pravidelné spouštění mazání rozpracovaných týmů (viz sekce Cron joby)
  - [X] Výrazné varování v UI s odpočtem zbývajícího času
  - [ ] při kontrolách jestli je hráč v týmu kontrolovat na všechny aktivity turnaje

## Kapitánské akce
- [X] Předání kapitána
  - [X] `AktivitaTym::nastavKapitana()` + API `predejKapitana`
  - [X] UI v `NastaveniTymuView.tsx`
  - [X] Blokování na zamčeném týmu (po implementaci zamykání)
- [X] Vyhození (a odhlášení) hráče z týmu
  - [X] API `odhlasClena` + `AktivitaTym::odhlasUzivateleOdTymu()`
  - [X] UI tlačítko u každého člena
  - [X] Blokování na zamčeném týmu (po implementaci zamykání)
  - [X] Odhlášení z aktivity při vyhození z týmu (ověřit že funguje)
- [X] Odhlášení kapitána sám sebe
  - [X] Pokud je v týmu víc lidí → automatické předání kapitána (nejstarší člen)
    - [X] `AktivitaTymService::findOldestClen()` existuje
    - [X] Napojení na flow odhlášení kapitána
  - [X] Pokud je kapitán poslední → rozpuštění týmu
    - [X] `AktivitaTym::rozebratTym()` existuje
    - [X] UI flow: "Opravdu chcete opustit a rozpustit tým?"
  - [X] API endpoint / rozšíření existujícího endpointu
- [X] Úprava limitu týmu (rozmezí min–max kapacity)
  - [X] `AktivitaTym::nastavLimit()` + API `nastavLimit`
  - [X] UI slider/input v `NastaveniTymuView.tsx`
  - [X] Blokování na zamčeném týmu (po implementaci zamykání)

## Veřejné týmy
- [X] Přepínání veřejnosti týmu
  - [X] `AktivitaTym::nastavVerejnost()` + API `nastavVerejnost`
  - [X] UI toggle v `NastaveniTymuView.tsx`
- [X] Seznam veřejných týmů zobrazený v UI
  - [X] API vrací `vsechnyTymy` s info o veřejných týmech
  - [X] `AktivitaTymService::findVerejneByAktivita()` existuje
- [X] Přihlášení do veřejného týmu bez kódu
  - [X] Backend logika existuje (kód není nutný pokud tým veřejný?)
  - [X] Ověřit UI flow — kliknutí na veřejný tým → přihlášení bez zadání kódu

## Zamykání týmu
- [X] DB sloupec `zamcen` (TINYINT / DATETIME) v tabulce `akce_tym`
  - [X] Migrace pro přidání sloupce
  - [X] Aktualizace Doctrine entity `Team.php`
  - [X] Aktualizace `AktivitaTymService.php` a `AktivitaTym.php`
- [X] Analýza jestli už existuje nějaké zamykání (v kódu je `HAJENI_TEAMU_HODIN = 72` ale žádný stav zamčení)
- [X] Backend logika zamykání
  - [X] Metoda `zamknout()` v `AktivitaTym` — validace min kapacity
  - [X] Metoda `jeZamceny()` — kontrola stavu
  - [X] Blokování všech editačních operací na zamčeném týmu (přihlášení, odhlášení, předání kapitána, změna limitu)
    - tohle se děje na api
- [X] API endpoint pro zamčení týmu (POST akce v `aktivitaTym.php`)
- [X] UI tlačítko "Zamknout tým" v `NastaveniTymuView.tsx`
  - [X] Tlačítko disabled pokud tým nemá min kapacitu
    - [X] info proč nejde zamknout
  - [X] Potvrzovací dialog — zamčení je nevratné pro hráče
- [X] Vizuální indikátor zbývajícího času do povinného zamčení (72h odpočet)
  - [X] `casZalozeniMs()` existuje a UI ho využívá pro odpočet
  - [X] Výrazná vizuální urgence (barva, ikona) když zbývá málo času
- [X] Zamčený tým nelze editovat (odhlašování, přihlašování, předávání kapitána)
  - [X] Kontrola `jeZamceny()` ve všech mutujících metodách `AktivitaTymService`
    - kontrola je na API
  - [X] API vrací chybu při pokusu o editaci zamčeného týmu
  - [X] UI skryje/zašedí editační prvky pro zamčený tým
- [X] Každý zamčený tým je automaticky nastaven jako neveřejný
  - [X] `zamknout()` nastaví `verejny = false`
- [X] Automatická akce po 72h pokud tým nezamčen
  - [X] DB sloupec na aktivitě: zda po 72h zveřejnit nebo smazat (`AkceSeznamSqlStruktura.php:32`)
  - [X] Migrace pro přidání sloupce do `akce_seznam`
  - [X] Cron job / pravidelná kontrola expirovaných týmů (viz sekce Cron joby)
    - [X] `AktivitaTym::expirovaneTymyIds()` — detekce existuje
    - [X] Akce nad expirovanými: zveřejnění nebo smazání (podle nastavení aktivity)
- [X] kontrola na všechny akce na straně BE že není tým zamčený

## Odemčení týmu
- [X] Backend logika odemčení
  - [X] Metoda `odemknout()` v `AktivitaTym` — reset `zamcen`, reset `zalozen` na `NOW()` (nový 72h limit)
  - [X] Oprávnění: pouze šéf infa (admin) nebo systém (odhlášení neplatiče)
- [ ] Šéf infa může tým odemknout přes admin (zatím stačí jen s pohledu vybreného uživatele)
  - [X] Tlačítko "Odemknout" v admin panelu `tymy.php` / `tymy.xtpl`
  - [ ] Kontrola oprávnění (šéf infa)
- [X] Automatické odemčení při odhlášení neplatiče
  - [X] Odemknout tým + vyhodit neplatiče z týmu
  - [X] Po odemčení běží limit 72h znova (reset `zalozen`)

## Cron joby / automatizace
- [ ] Pravidelné mazání rozpracovaných týmů (starší než 15 min, 0 členů)
  - [X] Logika `smazRozpracovaneTymy()` existuje
  - [ ] Cron job nebo hook který ji pravidelně volá
  - [ ] Přidan do nějakého seznamu volání přímo na serveru
- [X] Pravidelná kontrola 72h expirace nezamčených týmů
  - [X] Logika `expirovaneTymyIds()` existuje
  - [X] Cron job: zveřejnit nebo smazat podle nastavení aktivity

## Vícekolové aktivity / turnaje
- [X] Aktivita jako součást turnaje s definicí kola
  - [X] Tabulka `akce_tym_akce` propojuje tým s více aktivitami
  - [X] Definice čísla kola na aktivitě (DB sloupec? nebo odvozeno z pořadí?)
  - [X] Validace: každý tým má v každém kole právě jednu aktivitu
- [ ] Výběr termínů pro tým (skip pokud v žádném kole není víc možností)
  - [X] API endpoint `potvrdVyberAktivit`
  - [X] `jeTrebaPredpripravitTym()` detekce zda je výběr potřeba
  - [ ] UI pro výběr termínů ve vícekolových turnajích (netestováno / nedokončeno?)
  - [ ] UI flow: nejdřív výběr kol → pak založení týmu → pak přihlášení
- [ ] Přihlašování na všechna kola jako jedna akce
  - [X] API `potvrdVyberAktivit` existuje
  - [ ] Ověřit atomicitu — pokud jedno kolo selže, rollback všech
- [ ] Různá kapacita pro různá kola
  - [ ] Každá aktivita (kolo) má vlastní `kapacita` — ověřit že se respektuje
  - [ ] UI zobrazuje kapacitu per kolo
- [ ] Sledování týmové vícekolové aktivity
  - [ ] Pošle email když se uvolní místo pro nový tým
  - [ ] Neodhlaš ze sledování pokud hráč může ve všech kolech sledovat alespoň jednu aktivitu

## Admin program
- [ ] dělení programu po místnostech
- [ ] uživatel co udituje nemusí být ten samý co je editován (uPracovni vs u)
  - tady asi není potřeba posílat celého uživatele co dělá přihlašování, jen jeho oprávnění
- [ ] zobrazení podle místností (místo linií, levý sloupec ale pořád zobrazuje všechny dny)
  - [ ] které jsou řazené podle lokace.poradi
  - [ ] pokud je aktivita ve více lokacích tak se ve všech vykreslí
  - [ ] vykreslení prázdných místností (zapnout vypnout)
- [ ] skryt zobrazit viditelne aktivity
- [ ] změna title Program den, Můj program, Program místnosti, Program účastník
- [ ] autorefresh
- [ ] možnost zobrazit program bez uživatele (i když je vybraný tak zobrazit jako by byl nepřihlášený)
- [ ] na všech místech otevřít program s odpovídajícím nastavením
  - [ ] proram-obecny -> nepříhlášený
  - [ ] program-uzivatele -> vsechny dny
  - [ ] program-osobni -> muj(ucastnik)
  - [ ] program-po-mistnostech -> mistnosti
- [ ] css ke smazani pokud budou v preactu tak nejsou potřeba tady
  ```
  web/soubory/blackarrow/_spolecne/hint.css
  web/soubory/blackarrow/program/program-trida.css
  ```
- [ ] tisk místnosti pro orgy <div class="program_lokace">' . $lokace . '</div>
- [ ] parametry vykreslování
  - ~~DRD_PJ (asi netřeba s tymovkami)~~
  - ~~DRD_PRIHLAS (pro tymovky se org jevi jako kapitan)~~
  - ~~PLUS_MINUS (+- je v ui kapitana)~~
  - [X] OSOBNI - můj program/účastník
  - [ ] INTERNI
    - [ ] přidat někam do filtrů
    - [ ] kdy jsou interní přihlašovatelné ??
  - [X] SKUPINY - určuje co je vlevo - den(můj-program), linie, místnost
  - [ ] PRAZDNE - zobrazovat prázdné skupiny
  - [ ] ZPETNE - smí měnit přihlášení zpětně
  - [ ] NEOTEVRENE (a DOPREDNE) -  jestli smí přihlašovat na aktivity které ještě jsou teprve aktivované
  - [X] DEN zobrazení konkrétního dne
- [ ] Přihlašování/odhlašování v admin programu pro vybraného uživatele, ne přihlášeného orga (`program-uzivatele.php:117`)
  - [ ] Admin program posílá `id_uzivatele` vybraného uživatele do API
  - [ ] API akceptuje admin operace jménem jiného uživatele
- [ ] Definovat co vše admin program musí podporovat (`program-uzivatele.php:118`)
  - [ ] Minimálně: vše co kapitán (přihlášení/odhlášení členů, předání kapitána, změna limitu, zamčení týmu)
  - [ ] Navíc (šéf infa a další vyšší org role): odemčení týmu, přidání nad max kapacitu (ignorovatLimity), rozpuštění týmu, editace zamčeného týmu (přihlášení/odhlášení členů bez ohledu na zamčení)
- [ ] Admin může editovat zamčený tým nebo ho alespoň odemknout
  - [X] Admin rozebírání týmu existuje v `tymy.php`
  - [ ] Tlačítko odemknout v admin panelu
  - [ ] Editace zamčeného týmu (přihlašování/odhlašování členů přes admin)
- [ ] Admin může přidat lidi nad max týmu (otázka — flag `ignorovatLimity`)
  - [X] `zalozPrazdnyTym()` a `prihlasUzivateleDoTymu()` mají parametr `ignorovatLimity`
  - [ ] Admin UI pro přidání člena s overridem limitu
- [ ] Tisk programu z adminu
  - [ ] Souvisí s refaktoringem tisku programu (`Program.php` TODO)
- [ ] možnost otevřít program i bez vybraného uživatele
- [ ] pokud je otevřený
- [ ] zobrazení podle místností
  - [ ] pokud je ve více místnostech zobrazí se vícekrát
  - [ ] zobrazení pro jednotlivé dny nebo všechny
- [ ] zbytek tlačítek pro logiku

## Technický dluh / refaktoring
- [X] Odstranit systém "dětí" aktivit (`Aktivita.php` — ~20 výskytů `todo(tym): odstranit deti`)
  - [X] Nahradit `dite` sloupec novým turnajovým systémem (tabulka `akce_tym_akce` + kola)
  - [X] Odstranit metody: `deti()`, `maDite()`, `detiIds()`, `detiDbString()`, `pridejDite()`
  - [X] Odstranit `parseUpravyTabulkaDeti()`, `parseUpravyTabulkaRodice()`
  - [X] Přepsat přihlašovací logiku: `zkontrolujPrihlaseniNavazujicichAktivit()` bez dětí
  - [X] Přepsat odhlašovací logiku: odhlášení z potomků → odhlášení z turnaje
  - [X] Odstranit sloupec `dite` z DB (migrace)
  - [X] Aktualizovat `AkceSeznamSqlStruktura::DITE`
- [X] Odstranit/refaktorovat tisk programu (`Program.php` — 5 výskytů `todo(tym): odstraněný tisku programu`)
  - [X] Metody `tiskniTabulku()`, `tiskniObsah()`, `tiskniAktivitu()`, `prazdnaMistnost()`
  - [X] Nahradit novým renderovacím systémem pro program s podporou týmů
- [ ] Přihlašovací flow přes nový způsob pro týmové aktivity (`Aktivita.php:3131`)
  - [ ] Nahradit hardcoded HTML zámku za nový přihlašovací widget
  - [ ] Sjednotit flow přihlášení pro týmové i netýmové aktivity

## Maily
- [ ] Mailové šablony pro týmové události
  - [ ] Šablona: tým byl zamčen (potvrzení všem členům)
  - [ ] Šablona: tým byl odemčen (upozornění všem členům — akce potřebná)
  - [ ] Šablona: zbývá 24h do povinného zamčení (upozornění kapitánovi)
- [ ] Backend odesílání mailů
  - [ ] Odeslání při zamčení týmu (v metodě `zamknout()`)
  - [ ] Odeslání při odemčení týmu (v metodě `odemknout()`)
  - [ ] Odeslání 24h připomínky z cron jobu
    - [ ] Deduplikace — neposlat připomínku víckrát
    - [ ] DB sloupec `pripomenuti_odeslano` nebo jiný mechanismus

## další
- [ ] přidat nějaké další kontroly před zveřejňováním aktivity ? jako například že každé kolo turnaje má nějakou aktivitu až do max čísla jinak musí org přečíslovat turnaj ?
- [ ] nedostatek - tým je vždy přihlášen na aktivitu ve které kapitán tým zakládal (kdyby šlo o kolo z výběrem tak tohle je jako by si vybral tenhle konrétní termín) tohle neumožňuje explicitnost výběru


## Prezenčky
- [ ] Prezenčky pro týmové aktivity
  - [ ] Zobrazení týmů a jejich členů v prezenční listině
  - [ ] Hromadné označení celého týmu jako přítomný
  - [ ] Individuální označení přítomnosti členů týmu

## Importy & reporty
- tohle je třeba testovat na beta gameconu (na locale asi nepůjde moc rochodit)
- [ ] Importy pro týmové aktivity
  - [ ] Rozšíření stávajícího importeru o podporu týmů (`ImporterUcastnikuNaAktivitu.php:163`)
  - [ ] Import celých týmů (kapitán + členové + přiřazení na aktivity)
  - [ ] Formát CSV/importu: přidat sloupec pro tým (kód nebo název)
  - [ ] Validace importovaných dat (min/max kapacita, duplicity)
- [ ] Reporty pro týmové aktivity
  - [ ] Export seznamu týmů a členů (CSV/Excel)
  - [ ] Statistiky: počet týmů, průměrná velikost, zamčené vs otevřené
  - [ ] Report pro organizátory turnaje: přehled týmů per kolo
- [ ] Bfsr
  - [ ] Funkce jeToDalsiKolo, jak přesně se má chovat ???
    ```
    return in_array($this->typId(), [TypAktivity::LKD, TypAktivity::DRD], true)
    && empty($this->detiDbString());
    ```
  - [ ] co znamená když se testuje na typ LKD DND, nemělo by to bý tobecnější ten test ?

## dodělky (po nasazení první verze)

### Anonymizace
- [ ] Zobrazovat pouze přezdívku přihlášených hráčů v týmu
  - [ ] API vrací přezdívku místo plného jména pro neadmin uživatele
  - [X] API aktuálně vrací `jmeno` — potřeba změnit na nick/přezdívku
  - [ ] V admin panelu zobrazovat plné jméno (beze změny)
- [ ] oveření že uživatel nemůže být ve více týmech na jedné aktivitě

### Admin další funkcionality
- [ ] zobrazení turnajů

### TODO po nasazení testování
- [ ] přidat do cronu mazani_nepripravenych_tymu
- [ ] program-k-tisku využívá se ? https://github.com/gamecon-cz/gamecon/blob/d167f7e845424e064d040c58cf0778403dfb00ae/model/Aktivita/Program.php#L159
- [ ] jaký význam má Uzivatel::prednactiUzivateleNaAktivitach ? je potřeba někde ? (bylo to v iterátoru aktivit)
- [ ] co je placeholder-pro-roztazeni-radku a je potřeba ?
- [ ] je potřeba zobrazovat jména pju ?
- [ ] zrychlení api programu použitím dřívějšího přístupu k db
- [ ] Je potřeba výběr kola pro netýmové vícekolové aktivity?
- [ ] Je úprava limitu týmu kapitánem opravdu potřeba?
  - limit se může odstranit dodatečně zatím ničemu nevadí.
- [ ] Co přesně má jít dělat přes admin?

# budouci vylepšení
  - [ ] program
    - [ ] nějaký throtling pro dotahování dat
  - [ ] nejde odebrat vypravěč z aktivity (pouze nahradit jiným)
