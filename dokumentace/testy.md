
## Testy

Testy se spouští příkazem:

```bash
php udrzba/testuj.php
```

> TODO pro testy je zatím nutné mít ručně vytvořenou prázdnou kopii databáze s názvem `gamecon_test`, která ale musí obsahovat práva, židle, akce_prihlaseni_stavy a položku ID 0 v tabulce texty. <!-- jakmile se vyřeší, tento odstavec smazat -->

Testy využívají knihovnu [PHPUnit](https://phpunit.de/). Uvedený příkaz zařídí stažení phpunitu do cache složky a jeho spuštění.
