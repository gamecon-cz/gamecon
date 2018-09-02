
# Testy

Testy se spouští příkazem:

```bash
php udrzba/testuj.php
```

Testy pracují i s databází, na Windows je tedy nutné tedy mít zapnutý Wamp.

## Automatické spouštění

Testy by se měly spouštět před každým commitem, abychom necommitovali smetí (failující testy by pak stejně akorát zdržely review). Aby to nebylo nutné dělat ručně, je možné to nastavit do gitu automaticky.

Když pak dám `git commit`, automaticky se mi na pozadí spustí testy, a pokud failnou, git vypíše chybu a commit neudělá. Pokud projdou, commit se vytvoří normálně.

Pokud jsem v rootu repozitáře, automatické testy před commitem nastavím následovně:

```bash
cd .git/hooks/
ln -s ../../udrzba/pre-commit
```

## Technické detaily

Testy využívají knihovnu [PHPUnit](https://phpunit.de/). Skript `udrzba/testuj.php` zařídí stažení phpunitu do cache složky a jeho spuštění. Pokud máte nainstalován PHPUnit globálně, stačí v rootu repa spustit příkaz `phpunit` bez parametrů.

Testy probíhají nad databází pomocí transakcí, k čemuž využívají vlastní rozšíření [DbTest](https://github.com/godric-cz/db-test) (podporuje vnořování transakcí, je tedy možné využívat transakce i v testovaném kódu).

Testy si vytvoří automaticky vlastní prázdnou databázi `gamecon_test`. Migrace jsou při testech taky aplikovány automaticky. V případě problémů stačí celou databázi `gamecon_test` smazat a spustit testy znovu.
