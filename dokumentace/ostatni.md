
## Poznámky & TODO

Seznam věcí, které jsme zatím nezapsali jinam, protože je potřeba je rozvést, nebo se nenašlo vhodné místo, kam je dát:

- různá prostředí na ftp (beta, ux, ...)
- popis tabulek databáze
- použitá implementace MVC
- použitá implementace ORM
- překlápění ročníku - aktuálně v google docs
- rozjíždění: port 80 může být blokovaný nebo může být potřeba spustit server jako administrátor

### Šifrování

- K šifrování je použitá knihovna [Defuse](https://github.com/defuse/php-encryption).
- Prozatím šifrujeme pouze `číslo občanky` uživatelů.
- Šifrovací klíč je možné vygenerovat pomocí příkazu `$ vendor/bin/generate-defuse-key` a je pak uložen v konstantě `SECRET_CRYPTO_KEY`.
- Klíč který je v repozitáři je **TESTOVACÍ!**, na produkci musí být vygenerovaný nový.
- Pro šifrování máme obalující třídu `Sifrovatko` se statickými metodami `zasifruj($text)` a `desifruj($tajnyText)`.
- Pokud se text nepodaří dešifrovat metoda `desifruj($tajnyText)` vrací string '*Text se nepodařilo dešifrovat*'.
