# Docker

Pokud potřebuješ spouštět Gamecon z Dockeru (třeba protože máš potřebné porty už obsazené), tak si připrav

- nainstalovaný [docker](https://docs.docker.com/install/)
	- samotná virtualizace, na které vše poběží
- nainstalovaný [docker-compose](https://docs.docker.com/compose/install/)
	- pomocník pro zprovoznění Dockeru

A pokračuj těmito kroky

- ve tvé kopii Gameconu si do složky `nastaveni` přidej soubor `nastaveni-local.php` a do něj napiš
```php
<?php
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'gamecon');
define('DB_SERV', 'sql.gamecon'); // Docker image s PHP a Apache "vidi" na druhy image s SQL pod timto nazvem, viz nazvy v services v docker-compose.yml

define('DBM_USER', 'root');
define('DBM_PASS', 'root');
define('DBM_NAME', 'gamecon');
define('DBM_SERV', 'sql.gamecon');
```
- otevři si příkazový řádek
- přejdi do složky s Gameconem
	- třeba `cd C:\www\gamecon`
- zkopíruj si lokální nastavení Dockeru (detaily později) `cp docker-compose.override.template.yml docker-compose.override.yml`
- spusť `docker-compose up`
	- první puštění bude trvat dlouho, musí se stáhnout hodně souborů
- otevři si ve svém prohlížeči adresu http://localhost/gamecon/web/
	- hotovo!
	
Kdykoli později budeš chtít pustit Gamecon u sebe, spusť `docker-compose up` v adresáři s Gameconem.

### Připojení k databázi
Do SQL databáze máš otevřený port 13306. K databázi se můžeš připojit třeba přes program [HeidiSQL](https://www.heidisql.com/), kde zvol

- typ databáze MariaDB (nebo MySQL, jestli tam MariaDB není)
- URL zadej 127.0.0.1 a port 13306
- jméno a heslo je `root`, `root` (nebo co máš napsané v `nastaveni/nastaveni-local.php`

### XDebug
Při vývoji se často hodí [XDebug](https://deliciousbrains.com/xdebug-advanced-php-debugging/), kterým jde odkrokovat jednotlivá volání a prohlédnout si hodnoty v proměnných.

Přednastavený Docker kontejner už v sobě XDebug má aktivovaný, takže ho potřebuješ "jen" nastavit u sebe.

TODO

#### Vypnutí XDebugu
Xdebug dost zpomaluje, což většinou nevadí, ale při náročnějších operacích je prodleva už nepříjemná.

Pokud ho chceš vypnout, přepiš ve svém  `docker-compose.override.yml` řádek
- `entrypoint: /.docker/xdebug.gamecon-run.sh` na `entrypoint: /.docker/gamecon-run.sh`
	- není to žádná magie, prostě se jen použije jiný spouštěcí skript z naší složky `.docker`, schválně se do ní podívej

### Potíže s Dockerem

#### Konflikt portů
Pokud už na svém počítači máš obsazený port 80, nebo port 13306, tak je můžeš změnit v `docker-compose.override.yml`

- v části `ports` je něco jako `80:80`, to levé číslo je port na tvém počítači a to tě zajímá (to pravé pak port, na kterém běží Apache v Dockeru)
	- změň ho na co chceš, třeba `8080` pokud máš takový port volný (zjištíš spuštěním)
	- vypni a zapni docker s Gameconem
		- `Ctrl+C` v příkazové řádce, kde máš docker s Gameconem puštěný 
		- `docker-compose up` v příkazové řádce v adresáři, kde máš Gamecon
	- otevři si prohlížeč s adresou, do které nově přidáš změněný port, http://localhost:8080/gamecon/web/ (dej tam port, který jsi nastavil)

Port `13306:3306` je pro připojení k SQL databázi z tvého počítače. Pokud ti tohle způsobuje konflikt v portech, tak zase změň levý port. Nebo jestli se k databázi připojovat nechceš, tak řádky s portem prostě smaž: 
```
ports:
   - 13306:3306
```
