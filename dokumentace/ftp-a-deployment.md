
## FTP

__Editování kódu přes FTP je zakázáno.__ Kód se na FTP dostává automaticky (viz dál) a ruční nahrání by způsobilo skrytý rozdíl mezi obsahem gitu a FTP, což způsobuje hodně těžko odhalitelné bugy, které se mohou objevit s velkým spožděním a úplně nečekaně.

Ručně nahrávat přes FTP je možné fotky do `web/soubory/systemove/fotky` případně různý obsah do podsložek `web/soubory/obsah`.

__Editování struktury databáze přes Adminer je zakázáno.__ K změnám struktury databáze se používají skripty v složce `migrace`. Strukturou se rozumí přidávání sloupců, tabulek a podobně.

## Deployment

K nasazení (deploymentu) se používá knihovna FTP deployment. Nasazení na ostrou i betu (vč. upgrade databáze) provádí skript `udrzba/nasad.php`. Nasazení provádí jen jeden člověk, typicky po schválení pull requestu. K nasazení je nutné mít připraveny konfigurační soubory pro betu a produkci.

Skript pro nasazení se typicky používá jako git pre-push hook, tzn. nasazení probíhá z __lokálního stroje__ v okamžiku, kdy člověk pověřený nasazováním verze dá `git push`.

## Hotfix

Výjimku pro editaci přes FTP představuje hotfix. Pokud je nějaká kritická chyba, je možné kód změnit přímo na ftp a na githubu vytvořit pull request s odpovídajícími změnami. V pull requestu se uvede, že jde o hotfix.
