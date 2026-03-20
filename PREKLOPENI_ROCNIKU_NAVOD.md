**ZÁLOHOVÁNÍ DATABÁZE**

Přihlaš se přes SSH na gamecon.cz server

```bash
ssh gamecon.cz
```

Zkopíruj ostrou do zálohy

```bash
cp -r -L /srv/ftp/gamecon.cz/www/gamecon.cz/ostra /srv/ftp/gamecon.cz/www/gamecon.cz/2024
chown -R www-data:www-data /srv/ftp/gamecon.cz/www/gamecon.cz/2024
```

Vytvoř zálohu databáze

```bash
mysqldump -u root --result-file="gamecon_STARY_ROCNIK_`date +'%Y-%m-%d_%H-%M-%S'`-dump.sql" --extended-insert --routines --triggers --delayed-insert --no-tablespaces "d16779_gcostra"
```

Na své PC si zkopíruj zálohu databáze

```bash
scp 'gamecon.cz:gamecon_*.sql' .
```

Nahraj zálohu databáze
na [Gamecon Gdrive](https://drive.google.com/drive/folders/1QZIzXCrOQ2JMYi0EjI5EMbri0KHTJ9Nv?usp=drive_link) do složky
_Záloha {STARY_ROCNIK}_

---

Přidej novou databázi `gamecon_STARY_ROCNIK` a subdoménu `STARY_ROCNIK.gamecon.cz` do [Gamecon Ansible](https://github.com/gamecon-cz/ansible)
```bash
ansible-vault edit secrets.yaml
```

Spusť [Ansible playbook](https://github.com/gamecon-cz/ansible) ze své lokální kopie pro vytvoření nového ročníku

```bash
./deploy.sh
```

Nahraj zálohu databáze na nově vytvořenou databázi

```bash
mysql -u root "gamecon_STARY_ROCNIK" < "gamecon_STARY_ROCNIK_YYYY-DD-MM-HH-ii-ss-dump.sql"
```

Uprav domény
v `/srv/ftp/gamecon.cz/www/gamecon.cz/ostra /srv/ftp/gamecon.cz/www/gamecon.cz/2024/nastaveni/verejne-nastaveni-produkce.php`

```bash
define('URL_WEBU', 'https://STARY_ROCNIK.gamecon.cz');
define('URL_ADMIN', 'https://admin.STARY_ROCNIK.gamecon.cz');
define('URL_CACHE', 'https://cache.STARY_ROCNIK.gamecon.cz');
```

Uprav přístupy
v `/srv/ftp/gamecon.cz/www/gamecon.cz/ostra /srv/ftp/gamecon.cz/www/gamecon.cz/2024/nastaveni/nastaveni-produkce.php`

```bash
// uživatel se základním přístupem
define('DB_USER', 'gamecon_STARY_ROCNIK');
define('DB_PASS', 'HESLO_K_DATABAZI_gamecon_STARY_ROCNIK');
define('DB_NAME', 'gamecon_STARY_ROCNIK');
define('DB_SERV', 'localhost');

// uživatel s přístupem k změnám struktury
define('DBM_USER', '');
define('DBM_PASS', '');

// ...
define('FIO_TOKEN', '');
define('VAROVAT_O_ZASEKLE_SYNCHRONIZACI_PLATEB', false);

define('CRON_KEY', '');
define('GOOGLE_API_CREDENTIALS', '');
```
Vygeneruj si certifikáty pro starý ročník
```bash
certbot certonly --webroot
```
, domény `STARY_ROCNIK.gamecon.cz admin.STARY_ROCNIK.gamecon.cz cache.STARY_ROCNIK.gamecon.cz`,
cesta na serveru `/run/certbot`

Ověř, že záloha webu funguje na adrese [https://{STARY\_ROCNIK}.gamecon.cz/](https://stary_rocnik.gamecon.cz/)
a [https://admin.{STARY\_ROCNIK}.gamecon.cz/](https://admin.stary_rocnik.gamecon.cz/)

**PŘÍPRAVA NOVÉHO ROČNÍKU**

- Znovu si zkontroluj, že máš aktuální zálohu databáze s ostré a případně si stáhni novou, viz začátek tohoto README.

    - A pak jdi na stránku [https://admin.gamecon.cz/nastaveni](https://admin.gamecon.cz/nastaveni), dole najdi sekci
      „SQL update pro uzavření financí" a klikni na tlačítko pro vygenerování SQL příkazů
        - _Tato stránka pouze vygeneruje kód, kterým později upravíš zůstatky peněz u všech lidí. Tento kód je potřeba
          si zkopírovat a uložit. V posledním kroku po nasazení webu tento skript pustíme na ostré databázi._
    - Skript zkopíruj a ručně spusť na ostré databázi
      - Pozor ⚠️, poté už negeneruj a hlavně nespouštěj nový skript, do zůstatků by se znovu započetly už započtené změny!

- Z gitu si stáhni aktuální repozitář projektu Gamecon a vytvoř novou větev, do které budeš commitovat změny.

- V souboru [`./nastaveni/verejne-nastaveni-produkce.php`](./nastaveni/verejne-nastaveni-produkce.php) změň konstanty dle požadavků, hlavně nastav
  konstantu `define('ROCNIK', {NOVY_ROCNIK});`
    - Pozor ⚠️, nový ročník musí být změněn až po spuštění skriptu na ostré databázi, viz výše, jinak se zůstatky
      nepřevedou správně.

- Commitni veškeré změny do vytvořené branch v GITu a vytvoř Pull Request.

**NASAZENÍ ZMĚN NA WEB**

- Na Githubu potvrď Pull Request a zmerguj do aktuální „main“ větve
  *💡 Tím se spustí automatický deploy na ostrou*
    1. Pozor ⚠️, tím přestaly platit netrvalé (ročníkové) role, například “letošní vypravěč” a tím se změní zůstatky
       uživatelů, negeneruj už nový skript
       z [https://admin.gamecon.cz/nastaveni](https://admin.gamecon.cz/nastaveni), musíš
       použít ten z předchozího ročníku, viz některý z předchozích kroků

- Otevři si oficiální stránku [gamecon.cz](https://gamecon.cz/) a zkontroluj, zda vše funguje jak má.

- Všichni jsou happy!
