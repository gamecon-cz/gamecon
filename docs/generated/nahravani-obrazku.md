Nahrávání obrázků z administrace místo FTP: kde který obrázek leží, kdo ho čte, kterým modulem se spravuje a jak funguje společná služba `NahravaniObrazku` (kontrola typu, velikosti, cest, použití v textech).

## Vstupní body v kódu

- `model/Web/NahravaniObrazku.php`: společná služba. Ověří upload (`overNahranySoubor`) a uloží ho (`ulozJakoJediny`, `ulozNovy`, `nahrad`), případně smaže (`smaz`, `smazVsechnyVarianty`). Továrny `proFotkyUzivatelu`, `proObrazkyLinii`, `proObrazkyEshopu`, `proObsahWebu`.
- `model/Web/PouzitiObrazku.php`: kde texty webu odkazují na soubor (stránky, novinky a blog, popisy aktivit všech ročníků, medailonky).
- `model/Web/ObrazkyObsahu.php` + `obrazky-obsahu.xtpl`: widget „Obrázky" pod editorem stránky (`web/editace-stranek`) a novinky (`web/novinka`).
- `model/Web/ZpracovaneObrazky.php`: cesty k adresářům (`adresarFotekUzivatelu`, `adresarObrazkuLinii`, `adresarObrazkuEshopu`, `adresarObsahu`, loga), `popisekObrazkuEshopu`, `cestaVuciWebu`, `urlNaWebu`.
- Moduly: `admin/scripts/modules/aktivity/medailonky.php` (fotka vypravěče), `finance/_obrazky-eshopu.php` (vložené do `finance/shop.php`), `web/hlavicky-linii.php`, `web/loga.php` (jen mazání přes `cestaKSouboru`).

## Kde obrázky leží

| Co | Soubor | Čte | Spravuje |
|---|---|---|---|
| fotka vypravěče (medailonek) | `systemove/fotky/<id_uzivatele>.<jpg\|png\|gif\|webp>` | `Uzivatel::fotka()` (glob, první shoda), stránka vypravěče `web/moduly/aktivity.php`, widgety `drd-pj`/`dnd-pj` (vypravěče bez fotky vynechají) | Aktivity → Medailonky, práva 102 nebo 105 |
| obrázek aktivity | `systemove/aktivity/<url_akce>.jpg` | `Aktivita::obrazek()` | editor aktivity, vlastní mechanismus `\Obrazek` (GD, převod do JPEG, zmenšení na 2048 px) |
| obrázek linie | `systemove/linie-ikony/org_<id_typu>.<přípona>`, starší `<id_typu>.png` | `cestaObrazkuLinie()` | Web → Hlavičky linií (105) |
| avatar | `systemove/avatary/<id>.jpg` | jen admin, box pracovního uživatele | nikdo: nahrávání zmizelo s redesignem registrace v únoru 2021, `avatarNactiPost()` a `avatarSmaz()` nikdo nevolá |
| galerie e-shopu v přihlášce | `obsah/materialy/<ROCNIK>/eshop/*` | `web/moduly/prihlaska/prihlaska.php` | Finance → Shop (108) |
| obrázky v textech | `obsah/**` (volná struktura z doby FTP), nové do `obsah/stranky/<url>/`, `obsah/blog/<url>/`, `obsah/novinky/<url>/` | Apache přímo podle odkazu v textu | widget pod editorem stránky/novinky (105) |
| loga | `obsah/{sponzori,partneri}/` a `…/titulka/` | `ZpracovaneObrazky` | Web → Loga (112, ale otevře ho i 105) |

## Pravidla

- Typ se určuje z obsahu (`getimagesize`), přípona se podle něj dopočítá. Přípona od prohlížeče se nikdy nepoužije, takže `.php` ani `.htaccess` na disk neprojdou.
- SVG jen u obsahu webu, a to přes `LogoUpload::validujSvgSoubor()`.
- Limit `OBRAZEK_MAX_MB` (8), u linií `OBRAZEK_LINIE_MAX_MB` (2). Skutečný limit snižuje `upload_max_filesize` a `post_max_size`; ostrá má `post_max_size = 8M`. Dál limit 50 Mpx (ochrana paměti při výrobě náhledu).
- Zápis a mazání jen uvnitř kořene služby. Odmítá `..`, skryté soubory i symlink ven, a to i při vytváření adresářů.
- „Jediný obrázek daného názvu" (fotka, linie, e-shop): nový obrázek smaže varianty s jinou příponou, jinak by `glob` ve čtení vybral tu starou.
- Mazání i nahrazení jinou příponou se zablokuje, když na soubor odkazuje text (`PouzitiObrazku`). Hledá se cesta `soubory/…` i v URL kódování a `_` v `LIKE` je escapované. Šablony a kód se neprohledávají.
- Widget obsahu nahrazuje jen obrázky, na které text odkazuje, nebo obrázky z adresáře stránky. Nahrazení musí mít stejný formát, aby odkaz dál sedl. Maže jen nepoužité obrázky z adresáře stránky. Adresáře log a e-shopu vynechává, ty mají vlastní modul s jiným právem.
- (záměr) Loga se do společné služby převádí jen tehdy, když se nezmění chování modulu. Převedené je jen mazání (ošetření cesty). Upload log dál bere příponu z názvu souboru, nehlídá skutečný typ ani velikost.
- (záměr) Titulka a stránka se sponzory mají oddělené sady log, nesjednocovat.

## Gotchas

- **`post_max_size`**: větší požadavek PHP zahodí celý, `$_POST` i `$_FILES` jsou prázdné a modul by mlčel. Proto `NahravaniObrazku::bylPozadavekPrilisVelky()` na začátku každého modulu s uploadem.
- **Moduly adminu běží ve scope `admin/index.php`.** Proměnná `$stranka` v modulu přepíše název stránky adminu a sestavení submenu spadne (`Object of class Stranka could not be converted to string`). Tak to rozbil QR commit 1d64dbd5e v `web/editace-stranek.php`, opraveno přejmenováním na `$upravovanaStranka`.
- **Editor `DbForm` se odesílá AJAXem a ne jako multipart**, proto má upload vlastní formulář. Na pořadí nezáleží: skript s `$('form').last()` v `db-form.xtpl` běží inline hned za formulářem editoru a pozdější formuláře ještě nevidí.
- **Galerie e-shopu umí jen JPG, PNG a GIF**: `Obrazek::jeToPodporovanyObrazek()` jede přes GD bez WebP. Popisek je název souboru bez přípony a bez úvodního čísla, proto e-shop zachovává v názvu diakritiku i mezery.
- **Náhledy (`\Nahled`) se přegenerují podle mtime originálu**, nahrazení se projeví hned. Přímé odkazy z textů (`soubory/obsah/…`) ale může mít prohlížeč chvíli v cache.
- **Preview**: nahrané soubory leží jen ve filesystému kontejneru, redeploy je zahodí. Na ostré je ftp-deployment nesmaže, maže jen soubory, které sám nasadil.
- **Testy**: `AbstractTestWeb::loginToAdmin()` posílá POST na `/admin`. Apache ho přesměruje (`DirectorySlash` a pravidlo pro koncové lomítko v `admin/.htaccess`), curl z něj udělá GET a přihlášení se tak vůbec neprovede. `tests/web/NahravaniObrazkuVAdminuTest` se proto přihlašuje POSTem na adresu modulu a úspěch ověřuje.

## Otevřené otázky

- Aktivity: když PHP upload odmítne (velikost), `postFile()` vrátí prázdný řetězec a aktivita se uloží bez obrázku a bez hlášky.
- EXIF (i GPS) zůstává ve fotkách, originál je veřejně na odhadnutelné adrese `systemove/fotky/<id>.jpg`.
- Anonymizace ani slučování uživatelů soubory fotek neřeší.
- Avatary: rozhodnout, jestli je ještě chceme. Pokud ne, smazat mrtvý kód.
