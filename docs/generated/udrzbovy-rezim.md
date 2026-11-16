# Údržbový režim

TL;DR: soubor `maintenance.html` ve složce webu na serveru zavře web, admin i soubory z `cache/public` odpovědí `503` se stránkou `maintenance.off.html`, bez reloadu a bez zásahu do konfigurace serveru; zapíná ho workflow `maintenance-ostra.yml` nebo `ln -s` na serveru. Postup pro lidi je v `dokumentace/nasazeni.md` ("Údržbový režim"), tady je, proč je to postavené tak, jak je.

## Vstupní body v kódu

- `.htaccess`, `web/.htaccess`, `admin/.htaccess`, `cache/public/.htaccess` — blok údržby na začátku každého (pravidla přepisování se do podsložek nedědí)
- `maintenance.off.html` (stránka, v gitu) a `admin/maintenance.off.html` (odkaz na ni, v gitu)
- `udrzba/udrzbovy-rezim.php` (vstup `on|off`) a `udrzba/rsync-udrzbovy-rezim.php` (příkazy rsync, ověření odpovědí); sdílí s nasazením `withDeploySshFiles()` a `buildRsyncSshCommand()` z `udrzba/rsync-nasazeni.php`
- `getRsyncProtectedPaths()` a `buildIgnoreMasks()` — nasazení `maintenance.html` nemaže ani nenahrává
- `.github/workflows/maintenance-ostra.yml`
- `tests/web/UdrzbovyRezimTest.php` + `tests/web/ApacheJakoNaOstre.php`; přepínání testuje `tests/Dev/RsyncNasazeniTest.php`

## Pravidla

- Ostrá, kterou pro `gamecon.cz` a `admin.gamecon.cz` obsluhuje jeden adresář (ansible, `roles/webserver`: `VirtualDocumentRoot /srv/www/%0`, `admin.gamecon.cz` je odkaz na `ostra/admin`). Admin má proto za kořen `admin/` a kořenový `.htaccess` se na něj nevztahuje; z `admin/.htaccess` je soubor údržby o úroveň výš (`%{DOCUMENT_ROOT}/../maintenance.html`, přes odkaz to Apache ověřil), pod `/admin` na jménu webu v kořeni.
- Za údržby projdou jen existující soubory v `admin/deploy/` (skripty nasazení; chráněno heslem). Cron a vše ostatní dostane `503`, i brána previews a archivů (`/api/preview-vstup`, viz `preview-vstup-pres-adminu.md`): organizátor se tam za údržby dostane jen přes basic auth. Rozhodnuto při zavedení údržbového režimu, zapsáno v `dokumentace/nasazeni.md`.
- Workflow nahrává obyčejný soubor a vypíná ho smazáním; po přepnutí ověří odpovědi pěti adres (`getMaintenanceCheckUrls()`, základ čte z `nastaveni/verejne-nastaveni-produkce.php`, aby nebyl dvakrát); při zapnutí vyžaduje `503` s `Retry-After` a se značkou stránky údržby (viz Gotchas). Jen ostrá, beta workflow nemá.

## Gotchas

- **`/maintenance.html` je na serveru obsazené.** Vhost má `Alias /maintenance.html` na obecnou stránku pro případ, že neběží PHP-FPM. Alias se vyhodnotí dřív než `.htaccess`, takže `ErrorDocument 503 /maintenance.html` ukáže obecnou stránku serveru a tu naši nikdy. Proto `ErrorDocument` míří na `/maintenance.off.html`. Ověřeno v Apache s vhostem jako v ansible; test na to padá, když se `ErrorDocument` vrátí na `/maintenance.html`.
- **`ErrorDocument` jen za údržby.** Je v `<If "-f …maintenance.html">`; bez toho přebije obecnou stránku serveru i při pádu PHP-FPM a Apache ohlásí chybu o chybě `ErrorDocument` (`bezUdrzbyPadPhpFpmUkazeObecnouStrankuServeru`).
- `cache.<doména>` má za kořen `cache/public`, kde `maintenance.html` není; blok to neřeší, protože Caddy tato jména přesměrovává na `/cache/public` hlavního jména (ansible, `roles/caddy/README.md`). Až by zase došla do Apache, musí `cache/public/.htaccess` hledat o dvě úrovně výš a mít tam stránku.
- `admin/.htaccess` stojí na tom, že `admin.<doména>` je relativní odkaz `<doména>/admin` (ansible, `vhost_links.yaml`); `..` rozhoduje fyzický rodič cíle odkazu. Kdyby to ansible změnil, pozná to kontrola workflow, která se ptá i na admin.
- **Stránka musí být vidět v každém kořeni.** Kořen admina je `admin/`, kde stránka z kořene webu není, proto tam leží odkaz `admin/maintenance.off.html`. Nasazení (`--copy-links`) z něj udělá obyčejný soubor.
- **Odkaz z rsync nejde.** Klíč nasazení drží `rrsync -munge` (ansible, `roles/rsync_deployer`), který odkazy od klienta zneškodní, takže workflow nahrává kopii stránky. `-f` v `RewriteCond` odkaz na existující soubor pozná.
- **Nový `.htaccess` s `RewriteEngine` musí mít blok údržby**, jinak jeho složka údržbu obejde; test projde všechny `.htaccess` v projektu a hlídá i to, že blok je před prvním `RewriteRule`. Kořenový `.htaccess` posílá celé `/symfony/` na front controller (`^symfony(/|$)` s `END`, takže se nepodá žádný soubor ze stromu Symfony, hlídá to `SymfonyPodAdresaremTest`); blok údržby musí zůstat nad tím pravidlem, jinak by `/symfony/` údržbou prošlo.
- `(nejisté)` Caddy před Apache stránku údržby nepřepisuje: `handle_errors` v `apache_site` (ansible, `roles/caddy/templates/Caddyfile.j2`) se týká jen chyb, které vyrobí sám Caddy, a `503` od Apache projde i s tělem a `Retry-After`. Ověřeno čtením Caddyfile a v Apache bez Caddy, ne na serveru; první spuštění workflow `on` na ostré to ověří požadavkem na obě adresy.
- `(nejisté)` Testovací Apache se od ostré liší: bývá novější než Apache na serveru, nemá Caddy a PHP-FPM je v něm jen mrtvý handler jednoho skriptu. Direktivy `Header … setifempty … expr=%{REQUEST_STATUS}` už používá vhost v ansible, takže na serveru fungují; `<If "-f …">` a `RewriteCond -f` jsou starší. Ověřeno je rozložení kořenů, `Alias`, odkazy a `Retry-After` z `.htaccess`; průchod přes Caddy ověří až první běh workflow.
- Server servíruje nasazenou stránku, ne tu, kterou workflow nahrává (nahrává jen přepínač). Kontrola proto poznává stránku podle značky `<meta name="gamecon-maintenance">` (`MAINTENANCE_PAGE_MARKER`), ne podle těla, a před zapnutím ověří, že ji má nasazená stránka na `admin.gamecon.cz/maintenance.off.html` (tam je jako soubor i bez údržby); zastaví ho jen jasná odpověď `404` nebo `200` bez značky (starší, nebo jiná stránka; údržbu pak jde zapnout ručně přes `ln -s`), admin, který neodpovídá nebo hlásí chybu, údržbu nebrání (je dalším důvodem k ní). Ověřuje kopii adminu; kořenovou kopii, kterou servíruje web, nasazuje rsync spolu s ní. Kdo stránku pro případ upraví, značku nechá.
- Výjimka pro `admin/deploy` platí jen pro existující soubory (`RewriteCond %{REQUEST_FILENAME} !-f`). Bez ní by neexistující adresa s příponou pod `deploy/` neprošla na 503, ale na 404 Apache, a bez přípony by ji před údržbou zachránilo jen to, že se po přepsání na `index.php` blok vyhodnotí znovu.
- Bez chráněné cesty by rsync `--delete` při prvním nasazení `maintenance.html` smazal a údržba by skončila; hlídá to `theMaintenanceSwitchOnTheServerIsNeitherDeletedNorUploaded`.
