# Indexace veřejného webu vyhledávači

TL;DR: každá stránka veřejného webu nese jednu kanonickou adresu (`<link rel="canonical">` a `og:url`), `/robots.txt` generuje aplikace podle prostředí. Proč: Search Console hlásil kolem dvou třetin známých adres jako neindexované, z toho téměř polovinu jako „Duplicitní stránka bez kanonické verze“.

## Vstupní body

- `model/Web/Info.php::kanonickaUrl()` — adresa stránky, kterou vypisuje `html()`; `url()` je adresa bez dotazu, `kanonickyParametr()` přidá parametr, který mění obsah.
- `model/Web/Info.php::kanonickaUrlZCesty()` — `URL_WEBU` + cesta bez koncového lomítka; volá ji `web/index.php` s `Url::cela()`, takže `/program`, `/program/`, `/program?utm_source=…` a `/index.php` vs. `/` mají každá jednu adresu.
- `model/Web/RobotsTxt.php` + větev na začátku `web/index.php` (`$_GET['req'] === 'robots.txt'`).

## Pravidla

- Do kanonické adresy patří jen parametr, který vybírá jiný obsah: `aktivity` přidává `rocnik` (jen pro minulý ročník), `novinky` přidává `start`. **Nový modul, jehož obsah řídí dotaz, to musí přes `$this->info()->kanonickyParametr()` říct sám**, jinak se jeho další stránky prohlásí za duplicitu té první.
- Stránka bez `Url` (adresa s nepovolenými znaky) kanonickou adresu nemá.
- `robots.txt`: na ostré povoluje vše kromě `/finance`, `/qrka`, `/api/` (to jsou stránky pro přihlášené a JSON); beta, preview a lokál mají `Disallow: /`, aby nesoutěžily s ostrou. Na preview a v archivních ročnících `/robots.txt` vrací brána před aplikací (konfigurace serveru mimo tento repozitář), aplikace tam o něm nerozhoduje. (nejisté, v tomto repozitáři neověřitelné)
- 401 u archivních ročníků (`NNNN.gamecon.cz`) a preview je záměr (brána); Search Console je přesto počítá mezi neindexované stránky. (záměr)

## Gotchas

- Sitemap zatím není; `robots.txt` na ni neodkazuje.
- `/admin` se přesměrovává na `http://gamecon.cz:8080/admin/` — v kódu repozitáře pravidlo není, vypadá to na serverové přesměrování adresáře. (nejisté)
- Po sloučení dvojjazyčného webu pod `/en/` musí kanonická adresa zůstat v jazyce stránky; ten PR už má pro `hreflang` adresu stránky v jiném jazyce bez dotazu (`JazykPozadavku::urlVJazyce()`). (nejisté, závisí na jeho sloučení)
- Efekt se pozná až po týdnech v přehledu indexování Search Console; počet „Duplicitní stránka bez kanonické verze“ u hlavního webu má klesat k nule.
