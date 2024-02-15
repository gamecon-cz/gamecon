
<p align="center"><a href="http://gamecon.cz" target="_blank"><img width="346" height="55" src="http://gamecon.cz/soubory/styl/logo-github.png" alt="GameCon"></a></p>

_Web a informační systém (největšího československého) festivalu nepočítačových her [GameCon.cz](https://gamecon.cz)_

## Návod na rozjetí

### Docker

1. Zprovozni si `git` https://git-scm.com/downloads
2. Stáhni si repozitář přes `git clone git@github.com:gamecon-cz/gamecon.git`
   - nebo jestli máš potíže s SSH klíčem, tak v nouzi `git clone https://github.com/gamecon-cz/gamecon.git`
3. Zprovozni si Docker https://dockerwebdev.com/tutorials/install-docker/
4. **Spusť Gamecon v Dockeru `docker compose up`**
5. Oslavuj 🥳
   - http://localhost/web
   - http://localhost/admin

Další [vychytávky pro Docker](./dokumentace/docker.md)

### Windows
-  [na holých Windows](./dokumentace/windows.md) (to nechceš 🙄)

## Návod na přispívání

### Git
- Potřebuješ alespoň základy Gitu. Dobrá je online knížka [Pro Git](https://git-scm.com/book/cs/v2) (důležité jsou hlavně první tři kapitoly).

### Code style
- Abychom měli kód konzistentní, používáme `.editorconfig`

### Tvoje změny
- Aby ti šlo rovnou vytvářet větve kódu v našem repositáři, nech se přidat do _Gamecon Github_ organizace https://github.com/gamecon-cz,
  - Můžeš samozřejmě repositář forknout "někam k sobě" a poslat pull request ze svého forku do našeho repositáře, ale to je dobré spíše pro občasné přispěvatele.

### Jak poslat změny
- Standardní způsob, jak něco přidat:
  - Vytvořím si novou větev `git checkout -b nejaky-nazev`
    - 💡 pro název větve použij ideálně URL karty z Trella, například `1069-zobrazení-financí-účastníka` (diakritiky se neboj, od toho máme unicode)
  - Do dané větve nacommituji změny jak je v gitu zvykem přes `git add soubor` (git si "ofotí" současný stav souboru) a `git commit -m "upraven překlep v adminu"` (git změnu uloží do historie včetně tvého popisu)
    - 💡Pohodlnější je ovšem nějaké IDE, například [PHPStorm](https://www.jetbrains.com/phpstorm/download/#section=linux) (placený, subjektivně nejlepší) nebo [Visual Studio Code](https://code.visualstudio.com/download)
  - Danou větev pushnu na github `git push`
    - 📖 respektive na hlavní remote, viz `git remote -v` a protože máš repositář naklonovaný z gihubu, je remote stejný (a jmenuje se dle zvyku `origin`)
  - Otevřu si https://github.com/gamecon-cz/gamecon a vytvořím pull request (většinou se mi tam rovnou nabídne možnost v záhlaví)
  - V žádosti nastavím někoho jako reviewer, nebo požádám někoho přes Trello v související kartě
  - Počkám na kontrolu a případné připomínky (připomínek se neboj, už jenom to že každá skupina má jiné zvyky může přinést žádost o úpravu dle Gamecon nářečí)
  - Pokud se objeví v review připomínky, přidám je do kódu jako nové commity a pushnu je do stejné větvě, viz výše
  - Změny se nasadí automaticky v okamžiku zmergování pull requestu do `master` větve, viz [Github Actions](https://github.com/gamecon-cz/gamecon/actions/workflows/deploy-ostra.yml)
- Jakmile je vše vyřešeno a schváleno, vrátím se do větve master pomocí `git checkout master` a pomocí `git pull` si v ní stáhnu nejnovější změny.

## Návod k externím zálohám databáze

- Viz [Borg](./dokumentace/borg.md)
