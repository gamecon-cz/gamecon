- [x] zkusit vm jednotky
    - řádek obsahující vm se přepíše na vw a dogeneruje pod něj mediaselector s minimem
    - dořešit nastavení minimální šířky
    - poladit taky podle martinových ne/protestů na mobilní chování _zazijes_
    - možná raději "rel" (pixel relative) vůči 1920 a ten min width vkládat jako agument
- [ ] relativní cesty v .less souborech
- [x] doplnit do sponzorů partnery
- [ ] v modulu prihlaseni.php registrace na GC
    - mít tam vysvětleno, jestli registrace na GC běží a pochopitelný link
    - případně řešit jinak (původně link "přihláška" na titulce)
    - [ ] flow přihlašování -> registrace -> zadám existujícího a správné údaje -> (chyba / nic se nestane)
- [ ] TODO odstranit nebo přesunout tyto poznámky

- [ ] menu
    - [ ] menu uživatele
    - [ ] indikace zbyktu financí (?) a přihlášenosti na GC
    - [ ] (minor) v menu položka "přihláška na GC"
        - podle ne/přihlášenosti, případně spuštění regu
    - [ ] hover na mobilu
        - na některých mobilech možná nejde "kliknout" na rozbalovací části menu, otestovat, ověřit

- [ ] titulka
    - [x] proporciální hlavička
    - [ ] texty na hover linií
    - [ ] novinka
    - [ ] fotky linií
    - [ ] animace
    - [ ] (minor) ne/zobrazit CTA "přihlásit se" dle situace
        - jestli je uživatel přihlášen na web, jestli GC reg ne/běží, ...
    - [x] dodělat responzivy, kde nejsou
        - [x] menu
        - [x] hlavička
        - [ ] (future) fotky -- pro MVP na mobil jenom skrýt
    - [x] hovery
        - [x] hovery na liniích
        - [x] texty na hovery na liniích + ikony
        - [x] uživatelské menu
        - ...
    - [ ] načítat reálná data
    - [ ] skákání při načtení (asi výška menu a fonty)
    - [ ] srovnat, jaké prvky chybí proti stávající titulce / indexu
        - (viz stará šablona a zakomentovaný kód v titulka.php)
    - [ ] (minor) prozkoumat / vyhodit old_titulka.xtpl

- [ ] program
    - [x] scrollery
    - [x] dny
    - [x] pamatování horizontálního scrollu
        - sneak nefunguje, nutno napsat vlastní
    - [x] server nebo js rendering? server
        - jako nastavení třídy program, v adminu výpis všeho
    - [x] osobní program
        - [x] throw new Neprihlasen()
        - [x] UrlNotFound -> Nenalezeno
        - [x] naskinovat nebo předělat do jednotného kódu s normálním programem
    - [x] sledování / náhradnictví
    - [x] sidebar
        - [-] skákání F5: kliknu náhled, nascrolluji doprava, refresh, kliknu znova, skočí (děje se jen po F5, testováno ve FF, nedělá to js ale sám prohlížeč)
        - [x] skákání přihlásit: kliknu náhled, nascrolluju vpravo, dám přihlásit
        - [x] styl
        - [ ] (future) přihlašovací tlačítko
    - [x] integrace v adminu
        - možná posuvníky v adminu ani nepoužívat
        - [x] magic ajax nahradit obnovením scrollu
        - [x] výběr týmu v programu
    - [x] přesunutí souborů do jedné složky, uklizení
        - nápady: metoda css() jak teď, nebo cssSoubor()
        - souvisí s integrací v adminu
    - [ ] (minor) filtry (viz také server rendering / url)
    - [x] výška položek
    - [x] odkaz na tisk
    - [x] legenda, infosymboly, barvy
        - možná ne legenda? intuitivita / užitečnost pro uživatele?
        - [x] muži / ženy rozlišovák
        - [ ] (minor) symbol čekání na další vlnu s datumem a/nebo hoverem
    - [x] responziva
        - viz také vertikální názvy linií a chování sidebaru
        - [x] vertikální názvy linií
        - [x] menší scrollery
        - [x] menší políčka
    - [ ] (minor) co, když program není?
        - [ ] vůbec není program
        - [ ] nejsou žádné aktivity daný den
    - [ ] (future) zamyšlení, kdy zobrazovat přihlašovátka
        - aby uživatel věděl, že se musí přihlásit na GC apod.
    - [x] chybové hlášky (máš už aktivitu v daný čas apod.)
        - uprostřed dole nebude problém s překryvem u formulářů?
    - [x] projet TODOs
    - [ ] (minor) dávat alternativní datumy do náhledu?
    - [ ] (minor) Cookie „CHYBY_CLASS“
        - bude brzy blokována, protože obsahuje atribut „SameSite“ s neplatnou hodnotou nebo hodnotou „None“, která není bez bez atributu „secure“ povolená. Podrobnosti o atributu „SameSite“ najdete na https://developer.mozilla.org/docs/Web/HTTP/Headers/Set-Cookie/SameSite
    - [ ] (minor) drag scroll
    - [ ] (minor) v adminu použít $program->zpracujPost()
        - v admin/scripts/zvlastni/program-uzivatele.php (a možná dalších) použít výš zmíněnou metodu
        - do metody přesunout logiku s parametry přihlašovátka (už jsou jednou nastaveny jako parametry pro program, vyčíst z toho)
    - [ ] dodělávky
        - https://docs.google.com/document/d/1T28GpTWB_wtOXr1RD3jKY73ULswPKo4y5nu_EAp7-8g/edit#
        - [x] 4 responzivní záhlaví
        - [ ] (minor) 3 pozadí časů - nejde kvůli border-spacingu
        - [x] 3 vzhled dnů
        - [x] 3 průhlednost scrollerů
            - 2 odsazení a zarovnání - ne, vypadá divně podjíždění aktivit, snižuje místo, bez podbarvení časů funguje hůř
        - [x] 3 sidebar až po menu
        - [x] 3 scrollbar textu víc vpravo
        - [ ] (future) 3 přihlásit - nelze asi
        - [x] 2 vertikální linie už i na 1366
        - [ ] (future) 2 plynulá animace lišty - obalování etc hodně práce
        - [x] 1 zarovnat $$
        - [x] 1 barva nadpisu černá
        - [ ] (minor) 1 změna pozadí záhlaví

- [ ] společné prvky
    - zejména dropdowny, input
    - taky ale výchozí velikost písma, vzdálenost písmen, řádkování
    - čím později, tím větší riziko fuckupu velikostí / marginů někde

- [ ] přihlášení, registrace, přihláška
    - [ ] csrf (zkusit, možná jde nějak headrem omezit)
    - [ ] (minor) tlačítko (+/-) pro js přidávání/ubírání triček za letu
    - [ ] (minor) trvalé přihlášení (možná nastavit jako default)

- [ ] aktivity
    - [ ] po přepsání možno odstranit scroll sneak z webu úplně
    - ...

- [ ] projet TODOs
- [ ] (future) zrušit povinnost přezdívek

Minor

- [ ] favicon
- [ ] htaccess a cacheování resourců (nějaká funkce na modify parametr)
- [ ] projet TODOs again
- [ ] lazy loading
    - jestli se načítají všechny obrázky při čerstvém zobrazení stránky
    - nativní lazy loading (+fonty), viz https://developer.mozilla.org/en-US/docs/Web/Performance/Lazy_loading

Migrace

- [ ] překopírovat obrázky + texty linií, sponzory a partnery

Future

- [ ] admin last minute tabule (zatím nemá styl ale nějak jede)
