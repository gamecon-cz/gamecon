**ZÁLOHOVÁNÍ DATABÁZE**

Přihlaš se přes SSH na gamecon.cz server

```bash
ssh gamecon.cz
```

Zkopíruj ostrou do zálohy. Kontejner archivu z ní čte nahraný obsah `web/soubory/obsah` a `web/soubory/systemove`, který v gitu není

```bash
cp -r -L /srv/www/ostra /srv/www/STARY_ROCNIK
chown -R www-data:www-data /srv/www/STARY_ROCNIK
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

**ARCHIVACE STARÉHO ROČNÍKU**

Kód starého ročníku neběží z té kopie, ale jako Docker kontejner na adrese
`https://STARY_ROCNIK.gamecon.cz/`, postavený z větve `archive/STARY_ROCNIK`
(viz [`Dockerfile.archive`](./Dockerfile.archive) a workflow [`deploy-year-archive.yml`](./.github/workflows/deploy-year-archive.yml)).
Admin je pod cestou `/admin` a css, js a obrázky pod cestou `/cache/public` téhož jména, takže `admin.STARY_ROCNIK.gamecon.cz`
ani `cache.STARY_ROCNIK.gamecon.cz` (a jejich certifikáty) se nezakládají.

Větev `archive/STARY_ROCNIK` vytvoř z posledního commitu `main`, který má ještě starý ROCNIK, tedy před zmergováním
PR s novým ROCNIKem (viz níže). Jak ji upravit, ukazují předchozí archivní větve
(`git log origin/main..origin/archive/POSLEDNI_ARCHIVOVANY_ROCNIK`) a v repozitáři
[Gamecon Ansible](https://github.com/gamecon-cz/ansible) `docs/year-archive-plan.md` a role `year_archive_deployer`.
Databázi `gamecon_STARY_ROCNIK` s uživatelem založí při nasazení deploy skript.

Nahraj do té databáze zálohu

```bash
mysql -u root "gamecon_STARY_ROCNIK" < "gamecon_STARY_ROCNIK_YYYY-DD-MM-HH-ii-ss-dump.sql"
```

Ověř, že archiv funguje na adrese [https://{STARY\_ROCNIK}.gamecon.cz/](https://stary_rocnik.gamecon.cz/)
a [https://{STARY\_ROCNIK}.gamecon.cz/admin](https://stary_rocnik.gamecon.cz/admin)

**PŘÍPRAVA NOVÉHO ROČNÍKU**

- Znovu si zkontroluj, že máš aktuální zálohu databáze s ostré a případně si stáhni novou, viz začátek tohoto README.

    - Pozor ⚠️, úplně nejdřív, před generováním SQL a před jakoukoli změnou rolí nebo slev, je potřeba dokončit rozpracované
      objednávky e-shopu: do přepnutí totiž změna role přeceňuje rozpracovanou objednávku aktuálního roku. Hromadné
      dokončení zatím chybí (viz [Issue #1308](https://github.com/gamecon-cz/gamecon/issues/1308)). Objednávku z košíku dokončí
      jen tlačítko „Odjel z GC" („Odjela z GC") na infopultu, u přítomného účastníka, takže objednávky těch, kdo nepřijeli nebo odjezd
      nezapsali, zůstávají rozpracované.
    - A pak jdi na stránku [https://admin.gamecon.cz/nastaveni](https://admin.gamecon.cz/nastaveni), dole najdi sekci
      „SQL update pro uzavření financí" a klikni na tlačítko pro vygenerování SQL příkazů
        - _Tato stránka pouze vygeneruje kód, kterým později upravíš zůstatky peněz u všech lidí. Tento kód je potřeba
          si zkopírovat a uložit. Skript se pouští na ostré databázi před přepnutím `ROCNIK`, viz níže._
    - Skript zkopíruj a ručně spusť na ostré databázi
      - Pozor ⚠️, poté už negeneruj a hlavně nespouštěj nový skript, do zůstatků by se znovu započetly už započtené změny!
    - Katalog e-shopu se při přepnutí nearchivuje. Nový katalog se importuje až před otevřením e-shopu (kolem května) a
      import archivuje všechno, co v listu chybí. Cenou je, že statistika prodeje ukáže u minulého ročníku nuly pro placky,
      kostky a trička (ověřeno čtením dotazu, ne na datech). Podrobnosti jsou v [`docs/generated/prepnuti-rocniku-a-eshop.md`](./docs/generated/prepnuti-rocniku-a-eshop.md).

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
    2. Pravidla slev se nekopírují: platí v každém ročníku, takže nový ročník je má hned. Jen placku a kostku zdarma
       pojmenovává každý ročník zvlášť, a to import e-shopu před jeho otevřením, ale jen když list obsahuje sloupec
       `je_letosni_hlavni` a je v něm právě jeden předmět na druh (jinak import vypíše varování a pravidlo zůstane bez
       kódu). Do té doby tato dvě pravidla nic nedají, ostatní fungují hned.

- Otevři si oficiální stránku [gamecon.cz](https://gamecon.cz/) a zkontroluj, zda vše funguje jak má.

- Všichni jsou happy!
