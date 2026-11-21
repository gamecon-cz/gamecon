# Bingo z jednání rady

TL;DR: sekce Bingo v adminu (`admin/bingo`) pro orgy. Každé jednání rady je soutěž: před startem si org vygeneruje náhodnou kartu 5×5 s volným středem (uloží se mu), soutěž začne v 19:00 nebo když ji někdo spustí, kdo odškrtne, že fráze zazněla, odškrtne ji všem, a první bingo soutěž ukončí, vyhlásí a uloží vítěze.

## Vstupní body v kódu

- `model/Bingo/Bingo.php` (`Gamecon\Bingo\Bingo`): všechna pravidla, stav soutěže (`getStavSouteze()`, enum `StavSouteze`), stav pro diváka (`getStav()`), uložení vítěze (`prepocitejVyhru()`). Co kdo smí, rozhodují jen predikáty `is…Povoleno()` a `getZrusitelneFraze()`: z nich `getStav()` skládá příznaky `lze…` pro tlačítka a tytéž volají změny na serveru.
- `admin/scripts/modules/bingo.php` + `bingo.xtpl`: stránka, formuláře (akce v POST `akce`) a JSON stavu (`?stav=1&jednani=ID`).
- `admin/files/bingo.js`: odškrtnutí přes `fetch` (hlavička `X-Requested-With: fetch`, odpověď je stav), načítání stavu tak často, jak řekne server (`sledovatZa`), vyhlášení vítěze v `<dialog>` a vítězná karta; bez JS fungují formuláře, ale vítězná karta ani fráze ostatních se neukážou.
- `migrace/2026-11-17-034322_bingo.php`: tabulky, právo, první termíny.
- Testy: `tests/Bingo/BingoTest.php`.

## Datový model

- `bingo_fraze`: pool. Mazání je měkké (`smazano`), smazaná fráze zůstane na kartách, které ji mají, jen se nedostane na nové.
- `bingo_jednani`: termín jednání = jedna soutěž, `datum` unikátní, `spusteno` = ruční start (jinak začne v 19:00).
- `bingo_karta` (jednání × uživatel, unikátní) + `bingo_karta_pole` (pozice 0–24 po řádcích, 12 = volné pole bez řádku). `bingo_karta.vyhra` = karta vyhrála, čas binga.
- `bingo_zazneni`: (jednání, fráze), kdo a kdy odškrtl první. Patří k jednání, ne ke kartě, proto platí všem. `zruseno` + `id_zrusil` = odškrtnutí zrušené po bingu: řádek zůstane, aby šlo vrátit i s původním časem; co zaznělo, jsou jen řádky se `zruseno IS NULL`.

## Pravidla

- (záměr) Hrají jen orgové: právo `Pravo::ADMINISTRACE_BINGO` (116) dala migrace rolím s `TITUL_ORGANIZATOR` (Organizátor, půl-orgové, mini-org, pauzující full-org). Další role ho dostanou v Právech. 115 je zabrané větví 1510 (půjčovna kostýmů).
- (záměr) Soutěž = jednání rady, to bývá jednou za 14 dní. Termíny přidává kterýkoli org formulářem na stránce. Migrace založila 29. 11. a 13. 12. 2026, lednové termíny nebyly známé.
- (záměr) Soutěž začne, když ji někdo spustí, nebo v 19:00 (`Bingo::AUTOMATICKY_START`). Spustit ručně jde jen v den jednání. Běží do prvního binga, nejdéle do 6:00 dalšího dne (jednání po půlnoci).
- (záměr) Po startu už nejde měnit karty. Kdo kartu nemá, dostane za běhu jednu a dokud ji nemá, nevidí, co už zaznělo: `getStav()` mu nevrátí zazněné fráze ani fráze ve hře, stránka je nevykreslí. Co zaznělo předtím, se mu po vygenerování počítá, bingo má ale nejdřív v čase vygenerování karty.
- (záměr) První bingo soutěž ukončí, všem se ukáže vyhlášení a vítězná karta, vítěz zůstává v historii (`bingo_karta.vyhra`), další termín je nová soutěž. Dokončí-li jedno odškrtnutí řadu víc kartám, vyhrávají všechny.
- Odškrtává jen ten, kdo má kartu, a jen frázi z některé karty jednání. Za běhu soutěže jde odškrtnutí zrušit (překliknutí) a smaže se: až fráze opravdu zazní, platí čas nového odškrtnutí.
- Po bingu nejde nic nově odškrtnout. Zrušit jde, dokud jednání běží, jen odškrtnutí z vítězné řady (jen překliknutí v ní mohlo dát falešné bingo; zrušit cokoli jiného by nešlo vrátit). Když tím bingo zmizí, soutěž běží dál. Takové zrušení zůstane zapsané (`zruseno`) a kdokoli s kartou ho vrátí i s původním časem a tím, kdo odškrtl, i když mezitím vyhrál někdo jiný: omylem zrušené bingo tak nepřijde o svůj čas ani o výhru. Že fráze po zrušení zazněla znovu, se od vrácení nerozliší; vrácení proto chce potvrzení s původním časem.
- Změny soutěže jednoho jednání se řadí zámkem řádku `bingo_jednani` (`vTransakci()`): kdo odškrtne současně s bingem jiného, dostane „soutěž skončila“. Pravidla i zapsané časy se řídí časem, kdy změna zámek dostala (hodiny `ClockInterface`, ne `SystemoveNastaveni::ted()` ze začátku požadavku): kdo čekal ve frontě přes 19:00, kartu už nevymění. Odškrtnutí, které už někdo stihl, nic nemění a vítěze nepřepočítává.
- Jednání, na kterém se odškrtávalo (i zrušeně), nejde smazat (historie), ostatní se smažou i s kartami; kontrola i smazání jsou pod tímtéž zámkem.
- Stránka se ptá na stav každé 3 s jen za běhu soutěže; před startem v den jednání a po bingu po 10 s, jinak po minutě, před 19:00 nejpozději v čase startu; po jednání vůbec. Každý dotaz jde přes celý admin (menu, šablona), proto ne častěji.

## Gotchas

- Fráze jsou interní (porady rady): `bingo_fraze`, `bingo_karta`, `bingo_karta_pole` a `bingo_zazneni` jsou v `AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY`, do veřejného dumpu se nedostanou.
- Bingo je jediná stránka adminu pauzujícího full-orga. `admin/index.php` bez stránky přesměruje na první stránku menu, kterou smí (jemu Bingo). Právo je v `PristupDoAdminu::PRAVA_JEN_STRANKY`, ne v `PRAVA`: pustí ho na stránku a k výběru vzhledu, ale admin API a zvláštní stránky (interní program `program-mistnosti`) mu admin dál odmítá. Bingo API nepotřebuje, stav jde přes `bingo?stav=1`.
- Klasický vzhled kreslí v menu `admin/files/design/bingo.avif` (16×16), moderní ikonu `table-cells` z hlavičky modulu.
- Nad kartou je jen nadpis: popis stavu, spuštění, generování i vítězná karta jsou pod ní, protože se při startu a bingu mění a karta by poskočila pod prstem toho, kdo zrovna klepe. Vyhlášení je `<dialog>` (s `:not([open])` skrytím pro prohlížeč bez něj).
- Kartu stránka vykreslí jednou. Vygeneruje-li si org kartu jinde (telefon, jiná záložka), pozná to `bingo.js` podle `idKarty` ve stavu a stránku načte znovu.
- Tlačítka frází z karet ostatních `bingo.js` nepřestavuje, jen přidá nová a odebere zmizelá: přestavění při každém načtení stavu by sebralo rozpracované klepnutí a fokus.
- Chybové hlášky `BingoException` jsou text (i s frází, kterou kdo napsal); `chyba()` vypisuje HTML, proto je `bingo.php` escapuje.
