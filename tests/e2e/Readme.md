

# lokální vývoj

V nastavení je potřeba povolit testování

V `nastaveni\nastaveni-local.php` přiadat
```
define('TEST', true);
```

Pokud je test nastavený správně tak se na webu v adrese ["test"](http://localhost/web/test) zobrazí ui pro management testovácí databáze

# Cypress

## Prerekvizity

TODO: prerekvizity
TODO: dokument na instalaci node + yarn do vlastního dokumentu použitelné pro ui i e2e
      rozchodit nvm pro management verzí node

Po vyklonování nbeo změně branche v gitu nebo změnách závislostí je potřeba dotáhnout závislosti:
```
yarn install
```


## spuštění cypressu

Ve složce tests/e2e spustit `yarn dev`



