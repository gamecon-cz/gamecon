# Buildování:

## Prerekvizity

Příprava hostitelského stroje. Je potřeba pro buildování ui (ui bude vždy zbuildované na svém místě ať se nemusí při
nasazování pořád buildovat)

- [nodejs](https://nodejs.org/en/)
  - `node -v` vypíše verzi (alespoň 16)
- [yarn](https://yarnpkg.com/getting-started/install)
  - `yarn -v` vypíše verzi
  - na Debianu nebo Gamecon Docker image je namísto `yarn` binárka `yarnpkg`
- Pokud jeden z `-v` commandů nefunguje, je to s největší pravděpodobností že chybí v env path
  - https://github.com/yarnpkg/yarn/issues/8054#issuecomment-634153330

## Dependence

- `yarn install`
- volá se před prvním buildem a po každé změně v *package.json* *dependencies* a *devDependencies*

## Buildování

- `yarn build` nebo `yarn dev` spustí buildování ui. Po buildu je nutné vždy stránku znovu načíst (Ctrl+Shift+R nebo Ctrl+F5 pro většinu prohlížečů)
- `yarn dev`
  - zároveň sleduje změny a po každé provede build
  - zároveň spustí developement server
    - běží na `localhost:3000` (nebo na jiném portu pokud je zabraný)
    - vyžaduje nastavit prostředí v `index.html`
    - popř chce i nastavit správně *proxy* ve `vite.config.js` pokud gamecon api vůči kterému vyvýjím se nachází na jiném místě než localhostu

## Přidávání kódů

### Lintování

Po každé změně před commitem je třeba kód nastylovat.

Automatické opravy všeho co je možné, ostatní zobrazeno jako chyby co je nutné opravit
  - `yarn lint:fix`

Pouze výpis nalezených chyb bez opravy autoopravení chyb
  - `yarn lint`

Některé místa dávají smysl aby šli proti nastavení linteru. V takových případech jde linter potlačit komentářem.

## Struktura kódu

[docs/kod.md](./docs/kod.md)

## Debugování

[docs/debug.md](./docs/debug.md)
