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
define('DB_NAME', 'gamecon');
define('DB_SERV', 'sql.gamecon');
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
Do SQL databáze máš otevřený port 3306. K databázi se můžeš připojit třeba přes program [HeidiSQL](https://www.heidisql.com/), kde zvol

- typ databáze MariaDB (nebo MySQL, jestli tam MariaDB není)
- URL zadej 127.0.0.1 a port 3306
- jméno a heslo je `root`, `root` (nebo co máš napsané v `nastaveni/nastaveni-local.php`

Podobně se připojíš i přes příkazový řádek, pokud máš mysql klienta nainstalovaného ve svém počítači. Například pro nahrání anonimizované databáze použiješ něco jako `mysql --user=root --password=root --host=127.0.0.1 --port=3306 gamecon < /home/jaroslav/Downloads/gamecon_anonym_2019_05_08.sql`

### XDebug
Při vývoji se často hodí [XDebug](https://deliciousbrains.com/xdebug-advanced-php-debugging/), kterým jde odkrokovat jednotlivá volání a prohlédnout si hodnoty v proměnných.

Přednastavený Docker kontejner už v sobě XDebug má aktivovaný, takže ho potřebuješ "jen" nastavit u sebe.

#### Jak funguje XDebug
Je dobré vědět, jak věci fungují, snáze se to pak nastavuje a opravuje.

Xdebug běží v PHP a hlásí **ven**, co se v něm děje. Sám ale nečeká, až ho někdo kontaktuje a začne ho žádat, jestli by mohl reportovat i jemu. Xdebug buďto hlásí všechno od začátku, nebo vůbec nic a hlásí to po jediném kanálu, který má od začátku (běhu PHP instance) nastavený.

My se tedy k XDebugu nepřipojujeme, on se připojuje k **nám**.
Když si chceme zobrazit Gamecon, tak náš prohlížeč je klient, který se chce připojit k serveru (tady Apache na portu 80) v Dockeru. Ovšem Xdebug je ten klient, co se chce připojit k nám, k našemu IDE (například k PHPStormu), které dělá **server** (obvykle na portu 9000).

A stejně jako my potřebujeme vědět adresu serveru, na který se chceme připojit, třeba gamecon.cz:80, tak i XDebug potřebuje znát adresu serveru, tedy našeho stroje.

#### Adresa našeho stroje (hosta) z Dockeru

Protože je ale Docker svět sám pro sebe, tak má vlastní síť a vlastní rozsahy IP adres, takže XDebug v Dcokeru neuvidí tvůj počítač pod adresou 127.0.0.1 - pod tou má sám sebe.

Bránu a její IP adresu, přes kterou komunikuje Docker s tvým počítačem, najdeš u sebe v přehledu sítí.

- na Windows dej v příklazovvém řádku `ipconfig`
- na Linuxu `ifconfig`, nebo `ip address`

a hledej síť s názvem `docker0`. Bude u ní něco jako `inet 10.10.0.1/24`, což znamená internal network s bránou 10.10.0.1 a nějakým rozsahem podadres. Zajímá nás samozřejmě ta IP adresa brány - to je IP adresa, na které každý náš Docker kontejner vidí náš počítač.

Ta se hodí například pro XDebug.

#### Nastavení adresy pro XDebug
Máme adresu našeho počítače, ke kterému se má z Dockeru připojit XDebug. Teď už ji jenom potřebujeme PHP nějak předat.

Zkopíruj si `docker-compose.override.template.yml` jako `docker-compose.override.yml`. Otevři `docker-compose.override.yml` a uprav IP adresu v textu `xdebug.remote_host=172.0.0.2` na tu, kterou jsi našel ve svýc sítích pod názvem `docker0`. V našem příkladu by z toho vzniklo `xdebug.remote_host=10.10.0.1`.

Soubor ulož a pusť docker přes `docker-compose up` (přes příkazovou řádku, musíš být v adresáři s gameconem, respektive se soubory `Docker` a `docker-compose.override.yml`).

### Potíže s Dockerem

#### Konflikt portů
Pokud už na svém počítači máš obsazený port 80, nebo port 13306, tak je můžeš změnit v `docker-compose.override.yml` (zkopíruj `docker-compose.override.template.yml`).

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
