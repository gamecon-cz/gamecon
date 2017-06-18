
## Vstupní body programu

Soubory, které se dají spustit z nějaké url, tzn. které může nějak vyvolat apache (přímo, nebo pomocí nějakého override v `.htaccess`):

- `admin/cron.php`
- `admin/index.php`
- `admin/migrace.php`
- `web/index.php`

## Struktura souborů

Struktura složek a souborů v projektu:

- admin – administrační rozhraní GC webu.
- cache – složka pro cacheování. Do podsložek `private` a `public` musí mít webserver možnost zapisovat a soubory z složky `public` jsou veřejně dostupné pomocí url.
- migrace – skripty k upgradování struktury databáze. Pokud je v souvislosti s commitem např. nutné vytvořit nový sloupec a naplnit ho daty, v commitu je i migrační skript, který dané změny provádí. Při nasazení se pak skript provede (pomocí `admin/migrace.php`).
- model – třídy reprezentující základní entity na GameConu (uživatel, aktivita, …). Zbývající ne-php soubory jsou pouze doplňkové soubory k třídám.
- nastaveni – soubory s přístupovými údaji k databázi, konstantami udávajícími začátek GameConu a další parametry.
  - zavadec.php – soubor, který se includuje v adminu a webu zpřístupní databázi, třídy a konstanty, které potřebujeme. Uvnitř tohoto souboru se rozhoduje, které nastavení se načte (beta, produkce, lokální).
  - nastaveni.php – nastavení bussines logiky GC (kdy začíná, čísla účtů pro platbu, …). Sdíleno pro všechny prostředí (lokální, beta, produkce).
  - nastaveni-local-default.php – technická nastavení pro lokální vývoj. Výchozí varianta uložená v repu, která by měla rovnou fungovat po instalaci projektu podle návodu. možno "přetížit" pomocí `nastaveni-local.php`.
  - nastaveni-local.php – v tomto souboru je možné "přetížit" local-default nastavení (např. nastavit vlastní heslo do databáze). TODO: zatím nefunguje korektně, k přetížení konstant je nutné odladit potlačení error reportingu.
  - nastaveni-{cokoli}.php – soubory specifické pro konkrétní server (beta, produkce), které spravuje výhradně člověk, který dělá deployment. Neměnit přes FTP, neukládat lokálně (obsahují hesla). Výjimku má člověk, který dělá deployment – soubory podléhají automatickému deploymentu.
- udrzba – skripty provádějící různé operace údržby, např. deployment.
- vendor – složka s knihovnami spravovaná _výhradně_ pomocí composeru.
- web – veřejné rozhraní GC webu.
  - moduly – skripty reprezentující podstránky webu (např. `blog.php` starající se o adresu `/blog`). TODO: odkaz a přesnější vysvětlení funkce - že se includují dovnitř metody, kontextové proměnné atd.
  - sablony – šablony názvem odpovídající modulům. Ne každý modul musí mít šablonu.
  - soubory – soubory dostupné z webu přes url. Krom *.js a stylu nepodléhají automatickému deploymentu. Ve složce `systemove` jsou soubory nahrávané přes php, tzn. webserver do nich musí mít povolený zápis.
  - tridy – pomocné třídy pro zobrazení webu.
