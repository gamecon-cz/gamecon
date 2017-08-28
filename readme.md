
<p align="center"><a href="http://gamecon.cz" target="_blank"><img width="346" height="55" src="http://gamecon.cz/soubory/styl/logo.png" alt="GameCon"></a></p>

## Základní informace

Brzy…

## Návod na rozjetí

Návod je určený pro Windows, v Linuxu by mělo stačit nainstalovat všechno z balíčků a nastavit podle návodu.

- Nainstalovat wamp
  - Nainstalovat [VC12](https://www.microsoft.com/en-us/download/details.aspx?id=30679) – vyžaduje GUI wampu.
  - Nainstalovat [VC15](https://www.microsoft.com/en-us/download/details.aspx?id=48145) – vyžaduje PHP 7.0 pro Windows.
  - Nainstalovat samotný [wamp](http://www.wampserver.com/en/)
  - Spustit > objeví se a zezelená ikona v trayi > http://localhost/ na ověření
- Nastavit wamp – vždy levý klik na ikonu a následně provést co je popsané
  - PHP > Version > 7.(něco) – přepnout na php7
  - PHP > PHP settings > upload_max_filesize > vybrat 8M – navýšit limit post souboru
  - Apache > Apache modules > zaškrtnout expires_module – zapnout mod_expires
  - MySQL > MySQL settings > sql-mode > none – hlavně kvůli povolení negroupnutých sloupců při GROUP BY a doplňování nulových hodnot i do sloupců, co nemají default
- Nainstalovat [Composer](https://getcomposer.org/download/)
  - Při instalaci zvolit php7 (nabídne se to předinstalované wampem)
- Nainstalovat [Git](https://git-scm.com/downloads)
  - Při instalaci zvolit "checkout as-is, commit as-is"
  - Pokud používáte vlastní git (nějaké gui), je potřeba aby vám standardní git šel pustit z commandline napsáním `git`, nebo případně udělat níž popsané věci ručně
  - _TODO konfigurovat username / mail / klíče a github account_
- Vytvořit databázi
  - Nejdřív je potřeba sehnat si od někoho zálohu DB
  - Otevřít si http://localhost/adminer
  - Create new database > zadat gamecon, collate utf8_czech_ci > Save
  - Import > vybrat soubor s zálohou (vlevo) a pustit
- Stáhnout si repozitář
  - Otevřít si commandline a udělat níž uvedené příkazy
  - Poznámka: `composer install` není instalace composeru, ale stažení knihoven do složky s gc webem
  - _TODO v Linuxu povolit zápis do složek `/web/soubory/systemove/*`, `/cache/*`_
  - Ověřit funkčnost http://localhost/gamecon/web

```
cd C:\wamp\www
git clone git@github.com:gamecon-cz/gamecon.git
cd C:\wamp\www\gamecon
composer install
```
