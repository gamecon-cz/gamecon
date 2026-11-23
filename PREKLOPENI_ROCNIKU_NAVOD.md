# Překlopení ročníku

Starý ročník (`STARY`, například 2026) se zazálohuje a odejde do archivu na `https://STARY.gamecon.cz`, ostrá
přejde na nový ročník (`NOVY`). Kroky jdou v tomto pořadí. Příprava v kroku 1 nic nenasazuje, uzavření financí
(krok 4) a nasazení nového ročníku (krok 5) musí jít **hned po sobě**.

Předpoklad: zázemí má hotovou účetní uzávěrku a nedodělky z infopultu jsou zpracované, zůstatky tedy sedí.

Na server se přihlašuje jako root, příkazy na serveru jsou proto bez `sudo`:

```bash
ssh -i ~/.ssh/id_ed25519_gamecon root@gamecon.cz
```

Na serveru i u sebe si nejdřív nastav ročník, ostatní příkazy s ním počítají:

```bash
STARY=2026
```

Dumpy stahuj **mimo repozitář**, obsahují osobní údaje (níže `~/gamecon-zalohy`).

---

## 1. Příprava (nic nenasazuje)

### PR s novým ročníkem

Větev z aktuálního `main`, **zatím nemergovat**. Změň `ROCNIK`:

- [`nastaveni/verejne-nastaveni-produkce.php`](./nastaveni/verejne-nastaveni-produkce.php): `define('ROCNIK', NOVY);`
- [`nastaveni/verejne-nastaveni-preview.php`](./nastaveni/verejne-nastaveni-preview.php) a
  [`nastaveni/verejne-nastaveni-beta.php`](./nastaveni/verejne-nastaveni-beta.php): výchozí ročník
  `$rocnikOverride ?: NOVY` (preview se má chovat jako ostrá). Kopie databáze z ostré ho na betě i preview
  ponechá, kopie archivního ročníku ho nahradí ročníkem archivu.

Anonymizovaný dump v repu (`migrace/pomocne/gc_anonymizovana_databaze.sql`) nesmí být starší než z ročníku
`NOVY - 1`, jinak CI spadne, viz [`docs/generated/anonymizovana-databaze.md`](./docs/generated/anonymizovana-databaze.md).

### Archivní větev `archive/STARY`

Kód starého ročníku běží jako Docker kontejner na `https://STARY.gamecon.cz/`, postavený z větve
`archive/STARY` (viz [`Dockerfile.archive`](./Dockerfile.archive) a workflow
[`deploy-year-archive.yml`](./.github/workflows/deploy-year-archive.yml)). Admin je pod `/admin` a css, js a
obrázky pod `/cache/public` téhož jména, `admin.STARY.gamecon.cz` ani `cache.STARY.gamecon.cz` se nezakládají.

Větev vytvoř z posledního commitu `main`, který má ještě starý `ROCNIK`, tedy před mergem PR s novým ročníkem.
**Pushnout ji až v kroku 3**, push na `archive/**` rovnou nasazuje.

```bash
git fetch origin main
git switch -c "archive/$STARY" origin/main
```

1. Adresy archivu v [`nastaveni/verejne-nastaveni-produkce.php`](./nastaveni/verejne-nastaveni-produkce.php)
   (`URL_CACHE` se z `URL_WEBU` odvodí sám):

   ```bash
   sed -i -e "s|define('URL_WEBU', 'https://gamecon.cz');|define('URL_WEBU', 'https://$STARY.gamecon.cz');|" -e "s|define('URL_ADMIN', 'https://admin.gamecon.cz');|define('URL_ADMIN', 'https://$STARY.gamecon.cz/admin');|" nastaveni/verejne-nastaveni-produkce.php
   grep -n "URL_\|ROCNIK" nastaveni/verejne-nastaveni-produkce.php
   git commit -am "Archive $STARY: serve the year from $STARY.gamecon.cz with /admin and /cache/public"
   ```

2. Dev balíčky z `vendor/` musí zůstat v obrazu. Commitnutý autoloader (`vendor/composer/autoload_files.php`)
   natvrdo načítá soubory z `phpstan`, `rector`, `symplify`, `phpunit`, `myclabs` a `zenstruck`, `.dockerignore`
   je ale do obrazu nepustí a `Dockerfile.archive` autoloader nepřegeneruje. Archiv navíc nedostává `APP_ENV`,
   Symfony v něm tedy běží jako `dev` a potřebuje Foundry i MakerBundle. Bez toho archiv padá na každém požadavku.

   ```bash
   sed -i '/^vendor\//d' .dockerignore
   grep -c '^vendor/' .dockerignore   # má vyjít 0
   git commit -am "Archive $STARY: keep the dev vendor packages the committed autoloader requires"
   ```

3. Volitelně obraz vyzkoušej lokálně, oba `class_exists` mají vrátit `true`:

   ```bash
   docker build -f Dockerfile.archive -t "gc-archive-$STARY-test" .
   docker run --rm "gc-archive-$STARY-test" php -r 'require "/var/www/html/gamecon/vendor/autoload.php"; var_dump(class_exists("Zenstruck\\Foundry\\ZenstruckFoundryBundle"), class_exists("Symfony\\Bundle\\MakerBundle\\MakerBundle"));'
   ```

V `main` přidej do `deploy-year-archive.yml` řádek, který archivu zamkne základní obraz (PHP) jeho doby, ať ho
pozdější změna výchozího obrazu nepřestaví na novějším PHP. Jak se archivní větve upravovaly dřív, ukazuje
`git log origin/main..origin/archive/<rok>` a v repozitáři [Gamecon Ansible](https://github.com/gamecon-cz/ansible)
`docs/year-archive-plan.md` a role `year_archive_deployer`.

### Ansible předává archivu `DB_READONLY_*`

Od archivu 2026 čte kód `DB_READONLY_USER` a `DB_READONLY_PASS` (reporty přes `dbConnectReadOnly()`), bez nich
v archivu spadnou reporty. Deploy skript archivu je musí předávat (role `year_archive_deployer`, v `docker run`
vedle `DBM_*`). Ověření na serveru, mají se vypsat oba řádky:

```bash
grep -n 'DB_READONLY' /usr/local/sbin/deploy-year-archive.sh
```

Když nevypíše nic, uprav šablonu `roles/year_archive_deployer/templates/deploy-year-archive.sh.j2` v repozitáři
[Gamecon Ansible](https://github.com/gamecon-cz/ansible) a nasaď ji (`make deploy TAGS=year_archive_deployer`, jen
z `main`) dřív, než pushneš archivní větev: proměnné se předávají při `docker run`, už běžící archiv by se musel
nasadit znovu (`gh workflow run deploy-year-archive.yml -f branch=archive/$STARY`).

Deploy skript archivu nepředává ani `APP_ENV` a `APP_DEBUG`, takže archiv běží jako Symfony `dev` se zapnutým
debugem, ne jako ostrá (`prod`, bez debugu). Úprava `.dockerignore` výše je potřeba i tak, protože commitnutý
autoloader dev balíčky načítá vždy.

Až archiv poběží, zvedni v ansible `ARCHIVE_LAST_YEAR` v `.github/scripts/check-certificate-expiry.sh`, aby denní
kontrola certifikátů hlídala i nový archiv (dřív ne, jinak hned hlásí chybějící certifikát).

---

## 2. Záloha

### Soubory ostré (na serveru)

Archiv z kopie mountuje nahraný obsah `web/soubory/obsah` a `web/soubory/systemove`, který v gitu není.

```bash
ls -ld "/srv/www/$STARY"                      # cíl nesmí existovat
du -sh /srv/www/ostra /srv/www/ostra/backup   # backup/ jsou zálohy DB ostré, do kopie nepatří
df -h /srv/www
cp -r -L /srv/www/ostra "/srv/www/$STARY"
# nebo bez backup/, když je velký: rsync -aL --exclude=/backup/ /srv/www/ostra/ "/srv/www/$STARY/"
chown -R www-data:www-data "/srv/www/$STARY"
du -sh /srv/www/ostra/web/soubory/obsah "/srv/www/$STARY/web/soubory/obsah"   # velikosti se mají shodovat
```

### Databáze (na serveru)

```bash
cd ~ && mysqldump -u root --result-file="gamecon_${STARY}_$(date +'%Y-%m-%d_%H-%M-%S')-dump.sql" --extended-insert --routines --triggers --delayed-insert --no-tablespaces d16779_gcostra
DUMP=$(ls -t ~/gamecon_"$STARY"_*-dump.sql | head -1); echo "$DUMP"; ls -lh "$DUMP"
tail -n 1 "$DUMP"                                                   # -- Dump completed on …
grep -nE '^(CREATE DATABASE|USE |DROP DATABASE)' "$DUMP" || echo "OK: dump nemá CREATE DATABASE ani USE"
sha256sum "$DUMP"
```

### Na GDrive (u sebe)

```bash
mkdir -p ~/gamecon-zalohy && cd ~/gamecon-zalohy
scp -i ~/.ssh/id_ed25519_gamecon "root@gamecon.cz:gamecon_${STARY}_*-dump.sql" .
sha256sum gamecon_"$STARY"_*-dump.sql                               # stejný hash jako na serveru
```

Nahraj dump na [Gamecon GDrive](https://drive.google.com/drive/folders/1QZIzXCrOQ2JMYi0EjI5EMbri0KHTJ9Nv?usp=drive_link)
do složky _Záloha {STARY}_.

---

## 3. Archiv starého ročníku

Push spustí workflow, které postaví obraz, založí databázi `gamecon_STARY` s uživatelem a spustí kontejner
`gamecon-archive-STARY`. U sebe:

```bash
git push -u origin "archive/$STARY"
gh run list --workflow deploy-year-archive.yml --branch "archive/$STARY" --limit 1
gh run watch <id-běhu>
```

Na serveru ověř kontejner, jeho proměnné (`ENV=archive`, `DB_NAME`, `DB_READONLY_USER`) a databázi:

```bash
docker ps --filter "name=gamecon-archive-$STARY"
docker inspect "gamecon-archive-$STARY" --format '{{range .Config.Env}}{{println .}}{{end}}' | grep -E '^(ENV|DB_NAME|DB_READONLY_USER)='
mysql -u root -e "SHOW DATABASES LIKE 'gamecon_$STARY'"
```

Nahraj dump. Cílová databáze je napsaná výslovně, **nikdy ne `d16779_*`**:

```bash
DUMP=$(ls -t ~/gamecon_"$STARY"_*-dump.sql | head -1); echo "$DUMP"
grep -nE '^(CREATE DATABASE|USE |DROP DATABASE)' "$DUMP" || echo "OK: dump nemá CREATE DATABASE ani USE"
mysql -u root "gamecon_$STARY" < "$DUMP"
mysql -u root -e "SELECT (SELECT COUNT(*) FROM gamecon_$STARY.uzivatele_hodnoty) AS archiv, (SELECT COUNT(*) FROM d16779_gcostra.uzivatele_hodnoty) AS ostra"
```

Ověř archiv: `curl -s -o /dev/null -w '%{http_code}\n' "https://$STARY.gamecon.cz/"` vrací `401` (brána s heslem,
heslo je v ostrém adminu v Dev rozcestníku). V prohlížeči `https://STARY.gamecon.cz/` ukazuje program, na
`https://STARY.gamecon.cz/admin` projde přihlášení a otevře se report, v ostrém adminu Dev → Staré ročníky je
nový archiv a proklik do něj přihlásí.

---

## 4. Uzavření financí (těsně před nasazením nového ročníku)

Skript pro uzavření financí zapíše zůstatky spočítané **v okamžiku vygenerování**. Cokoli, co se na ostré změní
potom (platba stažená z FIO dostane rok `STARY`, objednávka, zásah v adminu), do zůstatku nepřejde a po přepnutí
ročníku se už nezapočte. Mezi spuštěním skriptu a nasazením nového ročníku zase ostrá počítá zůstatek i položky
starého ročníku dvakrát. Proto se od vygenerování skriptu do nasazení nového ročníku nesmí na ostré nic měnit:
crony jsou pozastavené, web i admin v údržbovém režimu ([`dokumentace/nasazeni.md`](./dokumentace/nasazeni.md),
„Údržbový režim“), a kroky 4 a 5 jdou hned po sobě. Za údržby projde jen nasazení (`admin/deploy`).

### Pozastavit crony

Na [console.cron-job.org](https://console.cron-job.org) pozastav **všechny úlohy ostré** (obecný cron se
stahováním plateb z FIO, `aktivity_hromadne` a další). Údržbový režim je sice také zastaví (`503`), ale cron-job.org
by mohl úlohu po sérii chyb sám vypnout.

### Vygenerovat skript

V adminu otevři Dev → **SQL update pro uzavření financí**
([https://admin.gamecon.cz/dev/update-zustatku](https://admin.gamecon.cz/dev/update-zustatku)) a vygeneruj SQL.
Obsah pole ulož do `~/gamecon-zalohy/uzavreni-financi-STARY.sql`.

- Pozor ⚠️, **znovu už negeneruj**. Po spuštění skriptu, a hlavně po nasazení nového ročníku (přestanou platit
  ročníkové role, například „letošní vypravěč“), by nový skript započítal do zůstatků už započtené změny.

Ověř, že má jeden `UPDATE` na uživatele:

```bash
grep -c '^UPDATE uzivatele_hodnoty' ~/gamecon-zalohy/uzavreni-financi-"$STARY".sql
```

a na serveru má vyjít stejné číslo:

```bash
mysql -u root -e "SELECT COUNT(DISTINCT id_uzivatele) FROM d16779_gcostra.uzivatele_hodnoty"
```

### Zapnout údržbový režim

Hned po vygenerování (admin pak už není dostupný). Spouští se z `main`:

```bash
gh workflow run maintenance-ostra.yml --ref main -f state=on
gh run list --workflow maintenance-ostra.yml --limit 1
gh run watch <id-běhu>
```

Běh sám ověří, že ostrá odpovídá `503` se stránkou údržby.

### Konečný dump

SQL smaže místnosti a vyčistí čísla OP, poznámky a ubytování, archiv proto musí dostat stav **před** ním. Je to
zároveň záloha pro návrat, kdyby se nasazení nového ročníku nepovedlo. Na serveru:

```bash
cd ~ && mysqldump -u root --result-file="gamecon_${STARY}_$(date +'%Y-%m-%d_%H-%M-%S')-dump.sql" --extended-insert --routines --triggers --delayed-insert --no-tablespaces d16779_gcostra
DUMP=$(ls -t ~/gamecon_"$STARY"_*-dump.sql | head -1); echo "$DUMP"; ls -lh "$DUMP"   # nejnovější, právě vytvořený
tail -n 1 "$DUMP"                                                                      # -- Dump completed on …
grep -nE '^(CREATE DATABASE|USE |DROP DATABASE)' "$DUMP" || echo "OK: dump nemá CREATE DATABASE ani USE"
sha256sum "$DUMP"
mysql -u root "gamecon_$STARY" < "$DUMP"   # do archivu; dump má DROP TABLE IF EXISTS, tabulky se přepíšou
```

Stáhni ho (u sebe, `scp -i ~/.ssh/id_ed25519_gamecon 'root@gamecon.cz:<název-dumpu>' ~/gamecon-zalohy/`), ověř hash
a nahraj ho na GDrive do _Záloha {STARY}_.

### Spustit skript na ostré

```bash
scp -i ~/.ssh/id_ed25519_gamecon ~/gamecon-zalohy/uzavreni-financi-"$STARY".sql root@gamecon.cz:
```

Na serveru, **jen jednou**. Celé v jedné transakci: když cokoli selže, `mysql` skončí, `COMMIT` se nespustí a
nezmění se nic.

```bash
{ echo 'START TRANSACTION;'; cat ~/uzavreni-financi-"$STARY".sql; echo 'COMMIT;'; } | mysql -u root d16779_gcostra && echo "SQL PROVEDENO"
mysql -u root d16779_gcostra -e "SELECT (SELECT COUNT(*) FROM lokace) AS lokace, (SELECT COUNT(*) FROM akce_lokace) AS akce_lokace"   # 0 a 0
```

Namátkou porovnej `zustatek` jednoho uživatele s hodnotou `SET zustatek=` ve skriptu
(`grep -m1 -B9 'WHERE id_uzivatele=' ~/uzavreni-financi-"$STARY".sql`).

---

## 5. Nasazení nového ročníku

Pozor ⚠️, nový ročník se smí nasadit až po spuštění skriptu z kroku 4, jinak se zůstatky nepřevedou správně.

Mergni PR s novým ročníkem do `main`, tím se spustí nasazení ostré (projde i za údržby). Před nahráním pouští
testy, trvá několik minut:

```bash
gh run list --workflow deploy-ostra.yml --limit 3
gh run watch <id-běhu>
```

Na serveru:

```bash
grep -n "define('ROCNIK'" /srv/www/ostra/nastaveni/verejne-nastaveni-produkce.php      # NOVY
mysql -u root d16779_gcostra -e "SELECT klic, hodnota FROM systemove_nastaveni WHERE rocnik_nastaveni = $((STARY + 1))"
```

Pak vypni údržbový režim a crony na cron-job.org zase zapni:

```bash
gh workflow run maintenance-ostra.yml --ref main -f state=off
gh run list --workflow maintenance-ostra.yml --limit 1
gh run watch <id-běhu>
```

Otevři [gamecon.cz](https://gamecon.cz/) a [admin.gamecon.cz](https://admin.gamecon.cz/) a zkontroluj, že vše funguje
a u pár uživatelů je stav financí roven zůstatku ze skriptu.

### Když se nasazení nepovede

Údržbový režim nech zapnutý (návštěvníci by viděli zdvojené zůstatky) a skript **znovu negeneruj ani nespouštěj**.
Oprav, co nasazení shodilo, a nasaď znovu (`gh run rerun <id-běhu>`, nebo nový commit přes PR).

Návrat zpět dává smysl, jen když nasazení spadlo dřív, než nahrálo kód, tedy když je na serveru pořád starý ročník
(`grep -n "define('ROCNIK'" /srv/www/ostra/nastaveni/verejne-nastaveni-produkce.php` ukazuje `STARY`). Pak vrať
ostrou do stavu před skriptem z konečného dumpu (vznikl za údržby, nic se od něj nezměnilo), revertni PR s novým
ročníkem, vypni údržbu, zapni crony a překlopení zopakuj od kroku 4 jindy:

```bash
mysql -u root d16779_gcostra < "$DUMP"
```

Když je na serveru už nový ročník, obnova dumpu by nechala nový kód nad neuzavřenými financemi. Nasazení pak
dotáhni dopředu.

---

## 6. Po překlopení

- V [adminu → Nastavení](https://admin.gamecon.cz/nastaveni) nastav data nového GameConu (začátek, konec,
  registrace, vlny). Na ostré jsou to vlastní hodnoty platné pro všechny ročníky, do změny tedy zůstávají ze
  starého ročníku. Stihnout před prosincovým PF.
- Místnosti jsou smazané, nahrají se pro nový ročník, až budou známé.
- Hodinový cron doplní přednášky starého ročníku do [archivu přednášek](https://gamecon.cz/prednasky/archiv),
  viz [`docs/generated/archiv-prednasek.md`](./docs/generated/archiv-prednasek.md).
- Až je vše na GDrive, smaž dumpy s osobními údaji ze serveru (`rm ~/gamecon_"$STARY"_*-dump.sql`). Skript
  uzavření financí si nech, kdyby se musely dohledávat zůstatky.

Všichni jsou happy!
