# Indexace veřejného webu vyhledávači

TL;DR: každá stránka veřejného webu s rámem (ne API a ne cesty obsluhované Symfony) nese jednu kanonickou adresu (`<link rel="canonical">` a `og:url`), `/robots.txt` generuje aplikace podle prostředí. Proč: Search Console hlásil kolem dvou třetin známých adres jako neindexované, z toho téměř polovinu jako „Duplicitní stránka bez kanonické verze“.

## Vstupní body

- `model/Web/Info.php::getKanonickaUrl()` — adresa stránky, kterou vypisuje `html()`; `url()` je adresa bez dotazu, `setKanonickyParametr()` přidá parametr, který mění obsah.
- `model/Web/Info.php::getKanonickaUrlZCesty()` — `URL_WEBU` + cesta bez koncového lomítka; volá ji `web/index.php` s cestou z `Url::cela()` (u linie a CMS stránky s uloženou adresou) a modul `titulka` s prázdnou cestou, takže `/program`, `/program/`, `/program?utm_source=…` a `/index.php` vs. `/` mají každá jednu adresu. Adresu nastavuj tímto voláním, ne ručním skládáním řetězce.
- `model/Web/RobotsTxt.php` + větev na začátku `web/index.php` (`$_GET['req'] === 'robots.txt'`).
- `model/Web/CestyKSitemape.php` sbírá cesty (úvodní stránka, veřejné moduly, linie z menu, stránky ze správy bez přesměrování, vydané blogy), `model/Web/Sitemap.php` z nich skládá XML s kanonickými adresami (stejná `Info::getKanonickaUrlZCesty()`), obsluhuje ho větev `sitemap.xml` v `web/index.php`.

## Pravidla

- Do kanonické adresy patří jen parametr, který vybírá jiný obsah: `aktivity` přidává `rocnik` (jen pro minulý ročník), `novinky` přidává `start`. **Nový modul, jehož obsah řídí dotaz, to musí přes `$this->info()->setKanonickyParametr()` říct sám**, jinak se jeho další stránky prohlásí za duplicitu té první.
- Router najde linii a CMS stránku bez ohledu na velikost písmen (`/RPG`, `/rpg`), proto jejich kanonická adresa je uložený tvar (`$typ->url()`, `$stranka->url()`), nastavený jednou v `web/index.php`, ne to, co přišlo v požadavku. Uložený tvar se tam dá použít, protože router ho sám najde zpátky. Modul `titulka` je vždy úvodní stránka (`/titulka` ji jen opakuje).
- Stránka bez `Url` (adresa s nepovolenými znaky) kanonickou adresu nemá.
- Do sitemapy patří úvodní stránka, veřejné moduly (`CestyKSitemape::VEREJNE_MODULY`; seznam je ručně psaný, protože o veřejné stránce nic v kódu modulu neříká, proto `ModulyVSitemapeTest` porovnává `web/moduly` se seznamem a se seznamem vynechaných modulů a selže, dokud se nový, přejmenovaný nebo zrušený modul nerozhodne), linie z menu, stránky ze správy bez přesměrování a vydané blogy. Správa rozhoduje, co je veřejné: zástupná nebo skrytá stránka se vypíše, dokud ji tam někdo nesmaže. Adresu, kterou by router nevrátil (nepovolený znak podle `Url::povolena()`, první část je soubor nebo adresář v `web/moduly`, který vyhraje nad stránkou), sitemapa vynechá; cesty, které `web/index.php` předává Symfony (`/zapomenute-heslo`, `/obnova-hesla`, `/altcha-challenge`), nehlídá. Profily vypravěčů (jejich kanonická adresa je otevřená, viz níže), dny programu (`/program/<den>`) a sekce vypravěčů (`/vypraveci/<linie>`) v ní nejsou, hledač je najde odkazy. Bez `lastmod`, protože žádný zdroj nemá spolehlivé datum poslední změny. Při překročení limitu 50 000 adres by bylo potřeba rozdělit na víc souborů.
- `robots.txt`: na ostré povoluje vše kromě `/finance`, `/qrka`, `/api/` (to jsou stránky pro přihlášené a JSON) a `/admin` (na ostré je admin na vlastním hostu `admin.gamecon.cz`, kde tento `robots.txt` neplatí, `/admin` hlídá jen cestu na hlavním hostu a přihlašovací stránku adminu `robots.txt` nezavře); beta, preview a lokál mají `Disallow: /`, aby nesoutěžily s ostrou. Na preview a v archivních ročnících `/robots.txt` vrací brána před aplikací (konfigurace serveru mimo tento repozitář), aplikace tam o něm nerozhoduje. (nejisté, v tomto repozitáři neověřitelné)
- 401 u archivních ročníků (`NNNN.gamecon.cz`) a preview je záměr (brána); Search Console je přesto počítá mezi neindexované stránky. (záměr)

## Gotchas

- Sitemapa se do Search Console neodesílá sama: po nasazení ji tam jednou přidat ručně (`https://gamecon.cz/sitemap.xml`), `robots.txt` na ni odkazuje jen na ostré.
- Modul, který nečte podcestu, odpoví 200 na jakýkoli přívěsek (`/novinky/cokoliv`, `/aktivity/x`, `Modul::zNazvu()` ho tiše zahodí) a jeho kanonická adresa je ta přívěsková. Zavřít to jde jen v routeru (404 nebo přesměrování), což mění chování celého webu, proto to tu není. (otevřené)
- Profil vypravěče zůstává na adrese z požadavku. `Uzivatel::zUrl()` ho najde i přes `/<id>`, `/<id>-<cokoliv>`, starý přívěsek a bez ohledu na velikost písmen, ale `Uzivatel::url()` nemusí vrátit adresu, která se k témuž člověku vrátí (bez řádku v `uzivatele_url` je to nově složený přívěsek, který se může srazit s modulem nebo jinou stránkou). Špatná kanonická adresa by uškodila víc než duplicita, proto se tu zatím nenastavuje. (otevřené)
- `novinky?start=` bere jakékoli kladné číslo, i takové, které není začátkem stránky (`?start=7`) nebo vrací prázdný výpis; kanonická adresa je pak ta samá. Odkazy stránkují po 20. (otevřené)
- Stránka `nenalezeno` (404) kanonickou adresu také vypíše; Google ji u 404 ignoruje.
- Přihlašovací stránka adminu na ostré (`admin.gamecon.cz`) odpovídá 200, nemá `robots.txt` ani `noindex`, takže ji Google může indexovat. `Disallow` tam nepomůže (crawler by `noindex` nikdy neviděl); řešením je hlavička `X-Robots-Tag: noindex` z adminu. `Disallow: /admin` v `robots.txt` hlavního webu hlídá jen cestu `/admin` na hlavním hostu. (otevřené)
- `/admin` se přesměrovává na `http://gamecon.cz:8080/admin/` — v kódu repozitáře pravidlo není, vypadá to na serverové přesměrování adresáře. (nejisté)
- Po sloučení dvojjazyčného webu pod `/en/` musí kanonická adresa zůstat v jazyce stránky; ten PR už má pro `hreflang` adresu stránky v jiném jazyce bez dotazu (`JazykPozadavku::urlVJazyce()`). (nejisté, závisí na jeho sloučení)
- Efekt se pozná až po týdnech v přehledu indexování Search Console; počet „Duplicitní stránka bez kanonické verze“ u hlavního webu má klesat k nule.
