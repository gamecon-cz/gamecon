
## Vstupní body programu

Skripty, které se dají spustit z nějaké url, tzn. které může nějak vyvolat apache (přímo, nebo pomocí nějakého override v `.htaccess`):

- `admin/cron.php`
- `admin/index.php`
- `admin/deploy/migrace.php`
- `web/index.php`

## Struktura souborů

Struktura složek a souborů v projektu:

- admin – administrační rozhraní GC webu.
- cache – složka pro cacheování. Do podsložek `private` a `public` musí mít webserver možnost zapisovat a soubory z složky `public` jsou veřejně dostupné pomocí url.
- migrace – skripty k upgradování struktury databáze. Pokud je v souvislosti s commitem např. nutné vytvořit nový sloupec a naplnit ho daty, v commitu je i migrační skript, který dané změny provádí. Při nasazení se pak skript provede (pomocí `admin/deploy/migrace.php`).
- model – třídy reprezentující základní entity na GameConu (uživatel, aktivita, …). Zbývající ne-php soubory jsou pouze doplňkové soubory k třídám.
- nastaveni – soubory s přístupovými údaji k databázi, konstantami udávajícími začátek GameConu a další parametry.
  - zavadec.php – soubor, který se includuje v adminu a webu a zpřístupní databázi, třídy a konstanty, které potřebujeme. Uvnitř tohoto souboru se rozhoduje, které nastavení se načte (beta, produkce, lokální).
  - nastaveni.php – nastavení bussines logiky GC (kdy začíná, čísla účtů pro platbu, …). Sdíleno pro všechny prostředí (lokální, beta, produkce).
  - nastaveni-local-default.php – technická nastavení pro lokální vývoj. Tento soubor je výchozí varianta uložená v repu, která by měla rovnou fungovat po instalaci projektu podle návodu. Možno "přetížit" pomocí `nastaveni-local.php`.
  - nastaveni-local.php – v tomto souboru je možné "přetížit" local-default nastavení (např. nastavit vlastní heslo do databáze). TODO: zatím nefunguje korektně, k přetížení konstant je nutné odladit potlačení error reportingu.
  - nastaveni-{cokoli}.php – soubory specifické pro konkrétní server (beta, produkce), které spravuje výhradně člověk, který dělá deployment. Neměnit přes FTP, neukládat lokálně (obsahují hesla). Výjimku má člověk, který dělá deployment – soubory podléhají automatickému deploymentu.
- udrzba – skripty provádějící různé operace údržby, např. deployment.
- vendor – složka s knihovnami spravovaná _výhradně_ pomocí composeru. Neměnit.
- web – veřejné rozhraní GC webu.
  - moduly – skripty reprezentující podstránky webu (např. `blog.php` starající se o adresu `/blog`). TODO: odkaz a přesnější vysvětlení funkce - že se includují dovnitř metody, kontextové proměnné atd. Spadá do nějakého textu o MVC.
  - sablony – šablony názvem odpovídající modulům. Ne každý modul musí mít šablonu.
  - soubory – soubory dostupné z webu přes url. Krom *.js a stylu nepodléhají automatickému deploymentu. Ve složce `systemove` jsou soubory nahrávané přes php, tzn. webserver do nich musí mít povolený zápis.
  - tridy – pomocné třídy pro zobrazení webu.

## Odkazování souborů

Pokud je potřeba v kódu použít odkaz na soubor (url k javascriptu, obrázku, …), musí být tento soubor taky v repozitáři (a podléhat [automatickému deploymentu](ftp-a-deployment.md#deployment)).

Velké soubory, které se často mění (např. tématické obrázky stylu a titulky) by ale postupně zvětšovaly velikost repa¹ až na nepřijatelnou úroveň. Řešením je vložit odkaz na soubor mimo repo (např. nějaký obrázek v `web/soubory/titulka`), ale uložit v repu nějakou výchozí variantu, která se nebude měnit (např. netematizovaný obrázek). V kódu se potom testuje přítomnost tématického obrázku a v případě jeho neexistence se použije výchozí obrázek, který v repu je. TODO: Toto pravidlo zatím není úplně realizováno. Pokud se v kódu narazí na odkaz mimo repo, který nemá výchozí alternativu, měla by se tato alternativa vytvořit a kód upravit.

Výš uvedené pravidlo se dá aplikovat i na automatizaci změny tématu v souvislosti s ročníkem: odkaz mimo repo bude například `web/soubory/tema/2017`, při čemž číslo 2017 se bude měnit podle aktuálního ročníku. Při změně ročníku se tak web bez dalšího zásahu přepne do výchozího stylu až do doby, než bude vytvořeno téma pro rok 2018.

---

¹ Děje se tak proto, že git ze svojí podstaty musí mít uloženu každou verzi každého souboru, který kdy do repozitáře nacommituji (abych se mohl vrátit v historii na daný commit a vidět, jak soubory tehdy vypadaly). Pokud takto ukládám binární soubory (např. obrázky), velikost repa se zvýší o součet všech obrázků, které se v něm kdy objevily (protože se moc nedají komprimovat). Proto se obecně nedoporučuje používat git na velké, často se měnící binární soubory.
