Nahrávání obrázků z administrace místo FTP: kde který obrázek leží, kdo ho čte, kterým modulem se spravuje a jak funguje společná služba `NahravaniObrazku` (kontrola typu, velikosti, cest, použití v textech).

## Vstupní body v kódu

- `model/Web/NahravaniObrazku.php`: společná služba. Ověří upload (`overNahranySoubor`) a uloží ho (`ulozJakoJediny`, `ulozNovy`, `nahrad`), případně smaže (`smaz`, `smazVsechnyVarianty`). Továrny `proFotkyUzivatelu`, `proObrazkyLinii`, `proObrazkyEshopu`, `proObsahWebu`.
- `model/Web/PouzitiObrazku.php`: kde texty webu odkazují na soubor (stránky, novinky a blog, popisy aktivit všech ročníků, medailonky).
- `model/Web/ObrazkyObsahu.php` + `obrazky-obsahu.xtpl`: widget „Obrázky" pod editorem stránky (`web/editace-stranek`) a novinky (`web/novinka`).
- `model/Web/ObrazkyEshopu.php` + `obrazky-eshopu.xtpl`: galerie e-shopu pod tabulkou `finance/shop`; `zpracujPost()` běží před vykreslením stránky, `html()` až za ní.
- `model/Web/ZpracovaneObrazky.php`: cesty k adresářům (`adresarFotekUzivatelu`, `adresarObrazkuLinii`, `adresarObrazkuEshopu`, `adresarObsahu`, loga), `jeAdresarJinehoModulu`, `popisekObrazkuEshopu`, `cestaVuciWebu`, `urlNaWebu`.
- Moduly: `admin/scripts/modules/aktivity/medailonky.php` (fotka vypravěče), `web/hlavicky-linii.php`, `web/loga.php` (jen mazání přes `cestaKSouboru`).

## Kde obrázky leží

| Co | Soubor | Čte | Spravuje |
|---|---|---|---|
| fotka vypravěče (medailonek) | `systemove/fotky/<id_uzivatele>.<jpg\|png\|gif\|webp>` | `Uzivatel::fotka()` (glob, první shoda), stránka vypravěče `web/moduly/aktivity.php`, widgety `drd-pj`/`dnd-pj` (vypravěče bez fotky vynechají) | Aktivity → Medailonky, práva 102 nebo 105 |
| obrázek aktivity | `systemove/aktivity/<url_akce>.jpg` | `Aktivita::obrazek()` | editor aktivity, vlastní mechanismus `\Obrazek` (GD, převod do JPEG, zmenšení na 2048 px) |
| obrázek linie | `systemove/linie-ikony/org_<id_typu>.<přípona>`, starší `<id_typu>.png` | `cestaObrazkuLinie()` | Web → Hlavičky linií (105) |
| avatar | `systemove/avatary/<id>.jpg` | jen admin, box pracovního uživatele | nikdo: nahrávání zmizelo s redesignem registrace v únoru 2021, `avatarNactiPost()` a `avatarSmaz()` nikdo nevolá |
| galerie e-shopu v přihlášce | `obsah/materialy/<ROCNIK>/eshop/*` | `web/moduly/prihlaska/prihlaska.php` | Finance → Shop (108), `ObrazkyEshopu` |
| obrázky v textech | `obsah/**` (volná struktura z doby FTP), nové do `obsah/stranky/<url>/`, `obsah/blog/<url>/`, `obsah/novinky/<url>/` | Apache přímo podle odkazu v textu | widget pod editorem stránky/novinky (105) |
| loga | `obsah/{sponzori,partneri}/` a `…/titulka/` | `ZpracovaneObrazky` | Web → Loga (112, ale otevře ho i 105) |

## Pravidla

- Typ se určuje z obsahu (`getimagesize`), přípona se podle něj dopočítá. Přípona od prohlížeče se nikdy nepoužije, takže `.php` ani `.htaccess` na disk neprojdou.
- SVG jen u obsahu webu, a to přes `LogoUpload::validujSvgSoubor()`.
- Limit `OBRAZEK_MAX_MB` (8), u linií `OBRAZEK_LINIE_MAX_MB` (2). Skutečný limit snižuje `upload_max_filesize` a `post_max_size`; ostrá má `post_max_size = 8M`. Dál limit 50 Mpx (ochrana paměti při výrobě náhledu), u galerie e-shopu 12 Mpx, protože přihláška každý její obrázek celý převádí do WebP.
- Zápis a mazání jen uvnitř kořene služby. Odmítá `..`, skryté soubory i symlink ven, a to i při vytváření adresářů.
- „Jediný obrázek daného názvu" (fotka, linie, e-shop): nový obrázek smaže varianty s jinou příponou, jinak by `glob` ve čtení vybral tu starou.
- Mazání i nahrazení jinou příponou se zablokuje, když na soubor odkazuje text (`PouzitiObrazku`). Hledá se cesta `soubory/…` i v URL kódování a `_` v `LIKE` je escapované. Šablony a kód se neprohledávají. Víc souborů najednou (`kdeJsouPouzity`) projde každou tabulku jednou; shodu pro každý soubor vyhodnotí databáze, aby platila stejná kolace jako v `LIKE`.
- Widget obsahu nahrazuje jen obrázky, na které text odkazuje, nebo obrázky z adresáře stránky. Nahrazení musí mít stejný formát, aby odkaz dál sedl. Maže jen nepoužité obrázky z adresáře stránky. Adresáře log a e-shopu vynechává (`ZpracovaneObrazky::jeAdresarJinehoModulu`), ty mají vlastní modul s jiným právem.
- (záměr) Loga se do společné služby převádí jen tehdy, když se nezmění chování modulu. Převedené je jen mazání (ošetření cesty). Upload log dál bere příponu z názvu souboru, nehlídá skutečný typ ani velikost.
- (záměr) Titulka a stránka se sponzory mají oddělené sady log, nesjednocovat.

## Gotchas

- **`post_max_size`**: větší požadavek PHP zahodí celý, `$_POST` i `$_FILES` jsou prázdné a modul by mlčel. Proto `NahravaniObrazku::hlidejPrilisVelkyPozadavek()` na začátku každého modulu s uploadem. V lokálním dev prostředí se to vyzkoušet nedá: PHP tu před hlavičkami vypíše varování, session nejde nastartovat a Tracy ukáže chybovou stránku. S vypnutým `display_errors` (jako na ostré) to funguje; zkoušelo se dočasným `php_flag display_errors off` v `admin/.htaccess`.
- **Chyby uploadu jdou vždy přes `NahravaniObrazku::sOhlasenimChyby()`**: zpráva výjimky není escapovaná pro HTML (může obsahovat název souboru nebo stránky) a helper ji escapuje a ohlásí na jednom místě.
- **Moduly adminu běží ve scope `admin/index.php`.** Proměnná `$stranka` v modulu přepíše název stránky adminu a sestavení submenu spadne (`Object of class Stranka could not be converted to string`). Tak to rozbil QR commit 1d64dbd5e v `web/editace-stranek.php`, opraveno přejmenováním na `$upravovanaStranka`.
- **Editor `DbForm` se odesílá AJAXem a ne jako multipart**, proto má upload vlastní formulář. Na pořadí nezáleží: skript s `$('form').last()` v `db-form.xtpl` běží inline hned za formulářem editoru a pozdější formuláře ještě nevidí.
- **Galerie e-shopu umí jen JPG, PNG a GIF**: `Obrazek::jeToPodporovanyObrazek()` poznává typ jen z hlavičky souboru (`exif_imagetype`), obrázek nedekóduje, takže ho nezastaví ani obří rozlišení. Poškozený soubor s platnou hlavičkou tak projde. Co s ním pak udělá `Nahled`, záleží na poškození: vadná struktura skončí výjimkou, kterou přihláška i Shop zachytávají, useknutý konec dá náhled s poškozenou spodní částí. Popisek je název souboru bez přípony a bez úvodního čísla, proto e-shop zachovává v názvu diakritiku i mezery.
- **Soubor se zpětným lomítkem v názvu** (FTP ho dovolí) služba nevypisuje, protože v něm vidí oddělovač adresářů a neuměla by ho smazat. Galerie v přihlášce ho dál ukáže, odstranit jde jen přes FTP.
- **Náhledy (`\Nahled`) se přegenerují podle mtime originálu**, nahrazení se projeví hned. Přímé odkazy z textů (`soubory/obsah/…`) ale může mít prohlížeč chvíli v cache.
- **`\Nahled::pasuj()` obrázek nikdy nezmenší**: `$this->s <= $s` je u čerstvého náhledu `null <= číslo`, tedy vždy pravda, a metoda vrátí originál. Náhledy v administraci proto používají `pokryjOrez()`. Veřejný web `pasuj()` dál volá (logo, aktivity, přihláška) a posílá tak originály v plné velikosti; oprava mění veřejné stránky, patří do samostatného úkolu.
- **`\Nahled` obrázek otočí podle EXIF orientace** (`autoOrient`), stejně jako prohlížeč u originálu. Fotka z telefonu bez toho ve výřezu leží na boku.
- **Kořen služby nebo adresář nad ním může být symlink** na sdílené úložiště. Služba pracuje se skutečnými cestami, které pak vedou mimo web, proto `kdeJePouzity` a `urlSouboru` cestu nejdřív přepočtou pod kořen zadaný při vytvoření služby (`cestaPodKorenem`).
- **Editor textu se odesílá AJAXem, formuláře widgetu načtou stránku znovu.** Widget proto přes `beforeunload` porovná obsah editoru s tím z načtení stránky a prohlížeč před nahráním obrázku upozorní na neuložený text. Nepoužívá `jquery.dirty` (`prevent-leaving-without-save`): ten při odeslání formuláře nastaví `submitting` a po neúspěšném AJAXovém uložení ho už nenuluje, takže by pak varování navždy mlčelo. Při ukládání je tlačítko Uložit zablokované (`DbForm`), po chybě ne; podle toho se pozná, že se ukládá.
- **Preview**: nahrané soubory leží jen ve filesystému kontejneru, redeploy je zahodí. Na ostré je ftp-deployment nesmaže, maže jen soubory, které sám nasadil.
- **Testy**: `AbstractTestWeb::loginToAdmin()` posílá POST na `/admin`. Apache ho přesměruje (`DirectorySlash` a pravidlo pro koncové lomítko v `admin/.htaccess`), curl z něj udělá GET a přihlášení se tak vůbec neprovede. `tests/web/NahravaniObrazkuVAdminuTest` se proto přihlašuje POSTem na adresu modulu a úspěch ověřuje.

## Otevřené otázky

- Aktivity: když PHP upload odmítne (velikost), `postFile()` vrátí prázdný řetězec a aktivita se uloží bez obrázku a bez hlášky.
- EXIF (i GPS) zůstává ve fotkách, originál je veřejně na odhadnutelné adrese `systemove/fotky/<id>.jpg`.
- Anonymizace ani slučování uživatelů soubory fotek neřeší.
- Avatary: rozhodnout, jestli je ještě chceme. Pokud ne, smazat mrtvý kód.
