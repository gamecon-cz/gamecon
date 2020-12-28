- [ ] srovnat, jaké prvky chybí proti stávající titulce / indexu
    - (viz šablona a kód)
- [x] dodělat responzivy, kde nejsou
    - [x] menu
    - [x] hlavička
    - [-] fotky -- pro MVP na mobil jenom skrýt
- [x] hovery
    - [x] hovery na liniích
    - [x] texty na hovery na liniích + ikony
    - [x] uživatelské menu
    - ...
- [ ] načítat reálná data
- [x] zkusit vm jednotky
    - řádek obsahující vm se přepíše na vw a dogeneruje pod něj mediaselector s minimem
    - dořešit nastavení minimální šířky
    - poladit taky podle martinových ne/protestů na mobilní chování _zazijes_
    - možná raději "rel" (pixel relative) vůči 1920 a ten min width vkládat jako agument
- [ ] relativní cesty v .less souborech
- [ ] indikace zbyktu financí (?) a přihlášenosti na GC
- [ ] novinka
- [x] doplnit do sponzorů partnery
- [ ] v modulu prihlaseni.php registrace na GC
    - mít tam vysvětleno, jestli registrace na GC běží a pochopitelný link
    - případně řešit jinak (původně link "přihláška" na titulce)
    - [ ] flow přihlašování -> registrace -> zadám existujícího a správné údaje -> (chyba / nic se nestane)
- [ ] TODO odstranit nebo přesunout tyto poznámky
- [ ] animace
- [ ] fotky linií
- [ ] menu: menu uživatele
- [ ] skákání při načtení (asi výška menu a fonty)
- [x] proporciální hlavička

- [ ] program
    - [x] scrollery
    - [x] dny
    - [x] pamatování horizontálního scrollu
        - sneak nefunguje, nutno napsat vlastní
    - [x] server nebo js rendering? server
        - jako nastavení třídy program, v adminu výpis všeho
    - [ ] osobní program
        - [x] throw new Neprihlasen()
        - UrlNotFound -> Nenalezeno?
        - [ ] naskinovat nebo předělat do jednotného kódu s normálním programem
    - [ ] sledování / náhradnictví
    - [ ] přesunutí souborů do jedné složky, uklizení
        - nápady: metoda css() jak teď, nebo cssSoubor()
    - [ ] sidebar
    - [ ] integrace v adminu
        - možná posuvníky v adminu ani nepoužívat
    - [ ] (minor) filtry (viz také server rendering / url)
    - [ ] výška položek
    - [ ] legenda (možná ne? intuitivita / užitečnost pro uživatele?)
        - možná nějaké symboly s hoverem, třeba :hodiny: 12.5., :hodiny: :otazník:
        - [ ] muži / ženy rozlišovák
    - [ ] responziva
        - viz také vertikální názvy linií a chování sidebaru
    - [ ] (minor) co, když program není?
    - [ ] (future) zamyšlení, kdy zobrazovat přihlašovátka
        - aby uživatel věděl, že se musí přihlásit na GC apod.
    - [ ] chybové hlášky (máš už aktivitu v daný čas apod.)
    - [ ] projet TODOs, necommitovat
    - [ ] (minor) dávat alternativní datumy do náhledu?
    - [ ] (minor) Cookie „CHYBY_CLASS“
        - bude brzy blokována, protože obsahuje atribut „SameSite“ s neplatnou hodnotou nebo hodnotou „None“, která není bez bez atributu „secure“ povolená. Podrobnosti o atributu „SameSite“ najdete na https://developer.mozilla.org/docs/Web/HTTP/Headers/Set-Cookie/SameSite

- [ ] aktivity
    - [ ] po přepsání možno odstranit scroll sneak z webu úplně
    - ...

- [ ] projet TODOs

Minor

- [ ] favicon
- [ ] htaccess a cacheování resourců (nějaká funkce na modify parametr)
- [ ] projet TODOs again
- [ ] lazy loading
    - jestli se načítají všechny obrázky při čerstvém zobrazení stránky
    - nativní lazy loading (+fonty), viz https://developer.mozilla.org/en-US/docs/Web/Performance/Lazy_loading

Migrace

- [ ] překopírovat linie, sponzory a partnery
