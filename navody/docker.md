# Docker

## Lokální nastavení aplikace
- ve tvé kopii Gameconu si do složky `nastaveni` přidej soubor `nastaveni-local.php` a v něm změn co potřebuješ, například
```php
<?php
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'gamecon');
define('DB_SERV', 'sql.gamecon');

define('DBM_USER', 'root');
define('DBM_PASS', 'root');
```

## Lokální nastavení Dockeru
- zkopíruj si `docker-compose.dev.yml` jako `docker-compose.override.yml` a přepiš nebo přidej, co potřebuješ

### Porty
- "Na venek" otevřené porty lze rošířit přes `docker-compose.override.yml`. Ale nejde tak odebrat ty, které jsou už určené přes `docker-compose.yml`. Pro změnu portů ulož nové hodnoty do `.env` souboru pod názvy, které najdeš v `docker-compose.yml`, například:
```
WEB_HTTP_HOST_MAPPING_PORT=81
```

### Připojení k databázi
Do SQL databáze máš pravděpodobně otevřený port 13306. K databázi se můžeš připojit třeba přes [HeidiSQL](https://www.heidisql.com/), kde zvol

- typ databáze MariaDB (nebo MySQL, jestli tam MariaDB není)
- URL zadej 127.0.0.1 a port 13306 (nebo co je zrovna platné, viz `docker ps`)
- jméno a heslo je `root`, `root` (nebo co máš napsané v `nastaveni/nastaveni-local.php`

Podobně se připojíš i přes příkazový řádek, pokud máš mysql klienta nainstalovaného ve svém počítači. Například pro nahrání anonimizované databáze použiješ něco jako `mysql --user=root --password=root --host=127.0.0.1 --port=13306 gamecon < /home/jaroslav/Downloads/gamecon_anonym_2019_05_08.sql`.

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

Zkopíruj si `docker-compose.dev.yml` jako `docker-compose.override.yml`. Otevři `docker-compose.override.yml` a uprav IP adresu v textu `xdebug.remote_host=172.0.0.2` na tu, kterou jsi našel ve svýc sítích pod názvem `docker0`. V našem příkladu by z toho vzniklo `xdebug.remote_host=10.10.0.1`.

Soubor ulož a pusť docker přes `docker-compose up` (přes příkazovou řádku, musíš být v adresáři s gameconem, respektive se soubory `Docker` a `docker-compose.override.yml`).
