# Gamecon na Windows

💡 Dej šanci Dockeru. Samotné spuštění Gameconu je v něm mnohem jednodušší [jak GameCon rozjet v Dockeru](../README#Docker).

⚠️ _zastaralý návod, potřebnou verzi PHP a MySQL/MariaDB si zkontroluj v [docker-compose.yml](./../docker-compose.yml)_ ⚠️

- Nainstalovat wamp
    - Nainstalovat [VC12 knihovny](https://www.microsoft.com/en-us/download/details.aspx?id=30679) – vyžadováno pro GUI wampu.
    - Nainstalovat [VC15 knihovny](https://www.microsoft.com/en-us/download/details.aspx?id=48145) – vyžadováno pro PHP 7.0 pro Windows.
    - Nainstalovat samotný [wamp](http://www.wampserver.com/en/)
    - Wamp je od verze 3.2 podporován pouze pro Windows 10 (na starší Windows si musíte [vygooglit starší verzi](https://www.google.com/search?q=wampserver+older+versions) / pro Windows 7 postačí [verze 3.1.9](https://wampserver.en.uptodown.com/windows/download/2132957)
    - před instalací je dobré zkontrolovat, že vše potřebné je nainstalované [WAMP-checkerem](https://wampserver.aviatechno.net/files/tools/check_vcredist.exe), který najdete na velmi povedné stránce [WampServer Aviatechno](https://wampserver.aviatechno.net/?lang=en), kde najdete všechny verze php, mysql a C++ knihoven, které by vám před instalací chyběly.
    - Spustit > objeví se a zezelená ikona v trayi > http://localhost/ na ověření
- Nastavit wamp – vždy levý klik na ikonu v trayi a následně provést co je popsané
    - PHP > Version > 7.(něco) – přepnout na php7 (informujte se u IT týmu, jaká verze je aktuální)
    - PHP > PHP settings > upload_max_filesize > vybrat 8M – navýšit limit post souboru
    - Apache > Apache modules > zaškrtnout expires_module – zapnout mod_expires
    - MySQL > MySQL settings > sql-mode > none – hlavně kvůli povolení negroupnutých sloupců při GROUP BY a doplňování nulových hodnot i do sloupců, co nemají default
    - MySQL > my.ini - najít a nastavit `default-storage-engine=InnoDB` (pokud nic takového v my.ini nemáš, tak to dej na samostatný řádek hned pod `[mysqld]`). Nastavuje se proto, že na některých strojích neprobíhá migrace korektně (bez InnoDB nejde provázat tabulky cizími klíči).
- Nainstalovat [Composer](https://getcomposer.org/download/)
    - Při instalaci zvolit php7 (nabídne se to předinstalované wampem)
- Nainstalovat [Git](https://git-scm.com/downloads)
    - Při instalaci zvolit "checkout as-is, commit as-is"
    - Pokud používáte vlastní git (nějaké gui), je potřeba aby vám standardní git šel pustit z commandline napsáním `git`, nebo případně udělat níž popsané věci ručně
- Nastavit si účet tady na GitHubu
    - Zaregistrovat se z titulky https://github.com/ a potvrdit ověřovací mail
    - Vytvořit si ssh klíč – spusťte git gui (přidalo se vám do startu) a v něm pak help > show SSH key > generate key > copy to clipboard.
    - SSH klíč si spárovat s githubem – vpravo nahoře vaše ikona > settings > SSH and GPG keys > new SSH key. Do políčka Key vložíte, co jste vykopírovali z git gui. Title je jedno, to slouží jen jako popis pro vás, kdybyste klíčů měli víc.
- Vytvořit databázi
    - Nejdřív je potřeba sehnat si od někoho zálohu DB (opět IT tým)
    - Nastavit heslo, třeba "root", pro uživatele root pro MySQL (Adminer (níže) nedovoluje přihlašování bez hesla)
        - Spustit MySQL konzoli (WAMP ikona v trayi - levý klik > mysql > konzole)
        - Příhlásit se jako root / (bez hesla)
        - Spustit příkaz `ALTER USER 'root'@'localhost' IDENTIFIED BY 'root';`
        - `exit`
    - Otevřít si http://localhost/adminer, přihlásit se jako root/root
    - Create new database > zadat gamecon, collate utf8_czech_ci > Save
    - Import > vybrat soubor s zálohou (vlevo) a pustit
- Stáhnout si repozitář pomocí následujících příkazů v commandline
```
cd C:\wamp\www
git clone git@github.com:gamecon-cz/gamecon.git
cd C:\wamp\www\gamecon
composer install
```
- Nastavit přístup vašeho webu do lokální databáze
    - ve složce gamecon\nastaveni zkopírujte soubor `nastaveni-local-default.php` do stejného adresáře pod jménem `nastaveni-local.php`
    - v souboru `nastaveni-local.php` nastavte heslo pro uživatele root na dvou místech `@define('DB_PASS', 'root');` a `@define('DBM_PASS', 'root');`
- Ověřit funkčnost http://localhost/gamecon/web
    - pokud běží, měla by se objevit výzva na aktualizaci databáze (migraci) - potvrďte provedení a aktualizujte databází
    - po reloadu http://localhost/gamecon/web můžete nejspíš konečně jít spát ;)

- Poznámky k předchozímu:
    - `composer install` není instalace composeru, ale stažení knihoven do složky s gc webem
    - _v Linuxu povolit zápis do složek `/web/soubory/systemove/*`, `/cache/*`_
- Další informace o struktuře repozitáře a architektuře kódu je možné si přečíst v [složce dokumentace](dokumentace).
