
## Kód a data na serveru

__Editování kódu přímo na serveru je zakázáno.__ Kód se na server dostává automaticky (viz dál) a ruční úprava by způsobila skrytý rozdíl mezi obsahem gitu a serveru, což způsobuje hodně těžko odhalitelné bugy, které se mohou objevit s velkým spožděním a úplně nečekaně. FTP se k tomu nepoužívá: soubory se nahrávají z administrace a kód nasazením.

Obrázky a soubory ke stažení se nahrávají z administrace:

- fotky vypravěčů (`web/soubory/systemove/fotky`) v Aktivity → Medailonky,
- obrázky do textů stránek a novinek (`web/soubory/obsah`) v editaci stránky nebo novinky, část Obrázky,
- soubory ke stažení k textu stránky nebo novinky (PDF, dokumenty, zvuk, ZIP, třeba press kit nebo soubory k D&D) tamtéž, část Soubory ke stažení: nový soubor jde do adresáře stránky, starší soubor, na který text odkazuje, se nahradí na místě; velké soubory jdou po částech,
- obrázky galerie e-shopu v přihlášce (`web/soubory/obsah/materialy/<ročník>/eshop`) ve Finance → Shop,
- loga sponzorů a partnerů ve Web → Loga, obrázky linií ve Web → Hlavičky linií,
- soubory ke stažení ve složkách `download`, `materialy`, `celohra` a `drd` v `web/soubory/obsah` ve Web → Soubory: do každé složky PDF, XLSX, MP3, ZIP a obrázky.

Soubory jiných typů (třeba fonty) patří do gitu a nasazují se s kódem. Archivní ročníky jsou zmrazené, nenahrává se do nich a mají vlastní nasazení.

__Editování struktury databáze přes Adminer je zakázáno.__ K změnám struktury databáze se používají skripty v složce `migrace`. Strukturou se rozumí přidávání sloupců, tabulek a podobně.

## Deployment

Nasazení na ostrou i betu (vč. upgrade databáze) provádí skript `udrzba/nasad.php`. Spouští ho GitHub Actions: [`deploy-ostra.yml`](../.github/workflows/deploy-ostra.yml) po pushi do `main`, [`deploy-beta.yml`](../.github/workflows/deploy-beta.yml) po pushi do větve `beta`. Nasazení jedou postupně (`concurrency`), testy běží před nimi.

Soubory se nahrávají přes **rsync po SSH**, cíl je v secretu `DEPLOY_RSYNC_TARGET`. Server se ověřuje připnutým `known_hosts`. Bez cíle nasazení skončí chybou.

Které soubory se nahrávají, určují masky v `buildIgnoreMasks()` v [`udrzba/_pomocne.php`](../udrzba/_pomocne.php). Po nahrání nasazení vždy zavolá na serveru `admin/deploy/*`: smaže cache, spustí migrace databáze a přegeneruje cache programu.

### rsync

Ostrá a beta mají každá vlastní klíč, který smí zapisovat jen do složky svého prostředí. Secrets v GitHubu:

| Secret | Obsah |
| --- | --- |
| `BETA_DEPLOY_RSYNC_TARGET`, `OSTRA_DEPLOY_RSYNC_TARGET` | cíl rsyncu, tedy `uživatel@server:cesta/` |
| `BETA_DEPLOY_SSH_KEY`, `OSTRA_DEPLOY_SSH_KEY` | soukromý klíč účtu pro nasazení |
| `DEPLOY_SSH_KNOWN_HOSTS` | řádky `known_hosts` serveru; bez nich se nasazení nespustí |

Volitelné proměnné repozitáře (Settings → Secrets and variables → Variables): `DEPLOY_SSH_PORT` a dva limity mazání, nejvíc položek (souborů i složek), které jedno nasazení smí smazat: `DEPLOY_RSYNC_MAX_DELETE_PROJECT` pro vše mimo `vendor/` (výchozí 500) a `DEPLOY_RSYNC_MAX_DELETE_VENDOR` pro `vendor/` (výchozí 5000). `vendor/` je v gitu, takže větší aktualizace závislostí, přeinstalování nebo úklid starých balíčků smaže stovky až tisíce souborů (první plán pro betu: 1793 starých vývojových balíčků), kdežto zbytek se mění po desítkách. V historii od roku 2013, počítáno jako rsync (soubory i složky), smazala nejvíc skutečná aktualizace ve `vendor/` 495 položek a jednou přeinstalování celého `vendor/` 8269; mimo `vendor/` smazaly přes sto položek čtyři commity, nejvíc 526. Takový výjimečný commit limit překročí a nasazení se zastaví, dokud se proměnná nezvýší. Nižší limit mimo `vendor/` tak ještě pozná rozbitou masku nebo chráněnou cestu. Při `0` nasazení selže, jakmile by se v dané části mělo cokoli mazat. Každý běh, i zkušební, nejdřív nanečisto spočítá, co by smazal, a při překročení kteréhokoli limitu skončí dřív, než cokoli změní; hláška říká, který limit a kolik položek by se smazalo.

Co je dobré znát:

- **Mazání.** rsync `--delete` smaže všechno, co v gitu není. Co na serveru vzniká za běhu nebo se tam zkopírovalo ručně (nahrané fotky, dumpy databáze, cache), chrání `getRsyncProtectedPaths()` v [`udrzba/rsync-nasazeni.php`](../udrzba/rsync-nasazeni.php). Když aplikace začne zapisovat do nové cesty uvnitř projektu, patří sem také. Je tam i `maintenance.html`, přepínač údržbového režimu (viz níže). Soubory podle výchozích masek (`.git*`, `*.bak`, `.idea`, `Thumbs.db`, `.DS_Store`, `.svn`, `.claude`) se nenahrávají a rsync je na serveru smaže, pokud je nechrání pravidlo výše nebo ignorovaný obsah (například `.gitkeep` v nahraných složkách). Ostatní ignorované cesty (logy, cache, nahraný obsah) rsync na serveru nechává.
- **Změněný soubor** se pozná podle obsahu (`--checksum`), ne podle času, protože po checkoutu a `composer install` mají všechny soubory nový.
- **Souběh a atomicita.** Soubory se nahrají pod dočasnými názvy, na místo se přejmenují až po nahrání všech (`--delay-updates`) a mazání jde teprve po přejmenování (`--delete-delay`; prosté `--delete` by mazalo dřív, než nové soubory dorazí). Souběh dál hlídá `concurrency` ve workflow.
- **Chyba uprostřed.** Když rsync skončí chybou (spojení, soubor, který nejde smazat), nasazení skončí červeně před smazáním cache a migracemi a server je částečně aktualizovaný. Po odstranění příčiny stačí workflow spustit znovu: rsync dokončí rozdíl a pak proběhnou migrace. Automatický opakovaný pokus tu není.
- **Ruční úpravy na serveru** (hotfix) rsync při příštím nasazení přepíše.

Workflow „Deploy BETA on direct push or merged pull request“ (`deploy-beta.yml`) se spouští ručně na větvi `beta`, „Deploy OSTRA (main) on direct push or merged pull request“ (`deploy-ostra.yml`) na větvi `main` (Actions → Run workflow → Use workflow from). Na jiné větvi skončí úloha `check-branch` chybou a nic se nenasadí, protože `nasad.php` skládá nastavení podle názvu větve a do složky jednoho prostředí by nahrál nastavení druhého. Ochrana je ve workflow z vybrané větve: dokud ji `beta` nemá (dostane ji sloučením `main`, což je skutečné nasazení bety), **nespouštějte Deploy OSTRA s volbou „Use workflow from: beta“**, protože by na ostrou nahrál kód bety a smazal z ní `nastaveni-produkce.php` s přihlašovacími údaji k databázi. Opakované spuštění staršího běhu (Re-run) nasadí znovu jeho původní commit.

Co by nasazení udělalo, ukáže ruční spuštění workflow se zaškrtnutým `dry_run`: proběhnou testy a pak `rsync --dry-run`, který vypíše nahrávané a mazané soubory, a nic se nezmění ani na serveru, ani v databázi. Zkušební běh má vlastní skupinu `concurrency`, takže nezruší čekající skutečné nasazení. Volba `dry_run` je také ve workflow z vybrané větve, takže na `beta` je až po sloučení `main`. Potřebuje cíl, klíč i `known_hosts` v secrets výše; bez cíle `nasad.php` skončí chybou dřív, než cokoli nahraje nebo změní.

### Co musí být na serveru

Řeší se v ansible, tady jen požadavky z pohledu nasazení:

- účet s přihlášením klíčem, který smí spustit jen `rsync` (ne shell) a jen do složky ostré, respektive bety; role `rsync_deployer` v ansible mu dává stejné uid (33) jako má `www-data`;
- právo zápisu do té složky a do podsložek, ve kterých se něco mění (rsync nahrává pod dočasným názvem a přejmenovává, takže vlastník samotného souboru nerozhoduje) a nově vytvořené soubory musí být čitelné pro `www-data`;
- `rsync` ve stejné nebo novější major verzi než na straně GitHub Actions;
- složky, do kterých účet nasazení nesmí zapisovat (typicky založené rootem), nesmí obsahovat nic, co se má smazat nebo přepsat, jinak rsync skončí chybou. Soubor vlastněný rootem ve složce, kam účet nasazení zapisuje, smazat jde (kromě složky se sticky bitem, kde smí mazat jen vlastník souboru nebo složky).

## Údržbový režim

Dokud je ve složce webu na serveru soubor `maintenance.html`, odpovídá každý požadavek na ten web `503` se stránkou [`maintenance.off.html`](../maintenance.off.html) a hlavičkou `Retry-After`. Platí pro stránky webu (včetně e-shopu), administraci (`admin.gamecon.cz` i `/admin`), soubory z `cache/public` a pro `POST`. Soubor se kontroluje při každém požadavku, takže zapnutí ani vypnutí nepotřebuje reload. Zapíná se pro každý web zvlášť (ostrá a beta jsou dvě složky), PHP běží dál a nic dalšího na serveru tím dotčeno není. Hodí se třeba před upgradem serveru, aby se do ostré nezapisovalo.

**Ostrá, z GitHubu:** Actions → „Maintenance mode OSTRA (turn on or off)“ ([`maintenance-ostra.yml`](../.github/workflows/maintenance-ostra.yml)) → Run workflow na větvi `main` → `on` nebo `off`. Před zapnutím ověří, že ostrá už má nasazenou stránku údržby se značkou (`https://admin.gamecon.cz/maintenance.off.html`), a když tam není (`404`) nebo je starší bez značky, údržbu nezapne, protože server servíruje nasazenou stránku, ne tu, kterou workflow nahrává; po sloučení změny stránky proto nejdřív dokonči nasazení. Admin, který neodpovídá nebo hlásí chybu, zapnutí nebrání. Pak nahraje nebo smaže soubor přes rsync stejným klíčem jako nasazení ostré (`udrzba/udrzbovy-rezim.php`) a pak požadavky na pět adres ostré (`/`, `/web/`, soubor v `cache/public`, `https://admin.gamecon.cz` a `/admin` na jménu webu) ověří, že odpovídají podle režimu: při zapnutí `503` s hlavičkou `Retry-After` a se značkou stránky údržby v těle (`<meta name="gamecon-maintenance">`, kterou musí mít i stránka upravená pro konkrétní případ; `503` z jiné vrstvy neprojde), při vypnutí cokoli jiného než `503` (třikrát po pěti sekundách). Když ne, skončí červeně, takže zelený běh znamená, že údržba opravdu je zapnutá, respektive vypnutá. Úplnost pokrývá `UdrzbovyRezimTest`, ne tahle kontrola. Zapnutí i vypnutí lze opakovat. Pro betu workflow není.

**Ručně na serveru** (třeba beta), ve složce webu:

```bash
ln -s maintenance.off.html maintenance.html   # zapne údržbu
rm maintenance.html                           # vypne ji
```

Záleží jen na tom, že soubor `maintenance.html` existuje; odkaz na `maintenance.off.html` i obyčejný soubor fungují stejně a nezáleží na tom, kdo ho vytvořil. Obsah odpovědi je vždy `maintenance.off.html` z nasazeného kódu, takže stránku pro konkrétní případ (třeba do kdy údržba potrvá) upravíš pull requestem a nasazením. Stránka je samostatná (styly v ní, žádné vnější soubory), protože se za údržby nic dalšího neposkytuje, a má `noindex`; `503` s `Retry-After` je pro vyhledávače správná odpověď na dočasnou nedostupnost.

**Co za údržby projde:**

- Existující soubory v `admin/deploy/` (po nahrání souborů smazání cache, migrace databáze a cache programu) projdou, protože jinak by nasazení za údržby skončilo červeně. Chrání ho heslo migrace jako vždy. Dá se tedy nasadit nový kód a migrovat databázi, zatímco je web zavřený; chceš-li, aby se do databáze nezapisovalo vůbec, za údržby nenasazuj.
- `admin/cron.php` (cron-job.org, jednou za hodinu) dostane `503` a nic neudělá; cron-job.org to může hlásit jako selhání, a po delší údržbě proto zkontroluj, že úloha tam není vypnutá (nevím, jestli ji po sérii selhání cron-job.org sám vypíná). Zmeškaný běh se nedoplňuje a připomínky vypravěčům mají okno jedné hodiny, takže ty, které na zmeškaný běh vyšly, se neodešlou.
- Brána previews a archivů se serverově ptá adminu přes loopback (`/api/preview-vstup`, viz `docs/generated/preview-vstup-pres-adminu.md`) a dostane `503`, což brána vyhodnotí jako „admin nedostupný“ a ukáže basic auth (`roles/gate_validator/files/enter.go` v ansible), takže se do previews a archivů za údržby dostane i organizátor jen přes basic auth. `(rozhodnutí)` Výjimka pro tuhle adresu by udržela vstup bez hesla, ale pustila by za údržby kód adminu a databázi; nezavedeno.
- Všechno ostatní dostane `503`, kromě adresy `/maintenance.html` samé: tu obsluhuje `Alias` serveru a ukáže obecnou stránku serveru, ne tu naši. Z volání zvenku počítá kód s cron-job.org, s nasazením a s bránou, ostatní (program z `cache/public`) jde přes prohlížeč.

**Na co si dát pozor:**

- Soubor `maintenance.html` není v gitu a nasazení ho nemaže ani nenahrává (`getRsyncProtectedPaths()` a `buildIgnoreMasks()`), takže údržba přežije nasazení. Je v `.gitignore` a `.dockerignore`, aby ho zapomenutý lokální odkaz neposlal do ostré ani do image.
- Stránka se podává pod adresou `/maintenance.off.html`, ne `/maintenance.html`: ansible má na `/maintenance.html` v celém vhostu `Alias` na obecnou stránku, který by tu naši přebil. Ta se ukáže, když PHP-FPM neběží a Apache sám vyrobí `503`; `ErrorDocument` na naši stránku proto platí jen za údržby (`<If>` v `.htaccess`), jinak by při pádu FPM přebil tu obecnou a návštěvník by dostal holou chybu.
- Pravidla přepisování se do podsložky s vlastním `.htaccess` nedědí. Blok údržby proto začíná každý `.htaccess` s `RewriteEngine` (kořen, `web`, `admin`, `cache/public`) a hlídá to `UdrzbovyRezimTest`; nový takový soubor ho musí mít taky, jinak jeho složka údržbu obejde. Blok se opakuje, protože `RewriteOptions Inherit*` podle dokumentace mod_rewrite dědí všechna pravidla rodiče, tedy i směrovací `.* web/$0` kořene, které by v podsložkách nemělo co dělat. Hlavičku `Retry-After` nastavuje `setifempty` i vhost v ansible; v `.htaccess` je proto, že ho nemají preview, archivy ani lokální vývoj, a test ji z vhostu vynechává. Admin na vlastním jménu má za kořen složku `admin/`, a proto hledá `maintenance.html` i o úroveň výš a má tam vlastní kopii stránky (odkaz `admin/maintenance.off.html`, rsync ho nahraje jako soubor).
- Workflow nahrává obyčejný soubor, ne odkaz: klíč nasazení drží `rrsync -munge`, který odkazy, které mu klient pošle, zneškodní.

### Test

`tests/web/UdrzbovyRezimTest.php` spouští `.htaccess` projektu v samostatném Apache (`ApacheJakoNaOstre`), který má kořeny rozložené jako ostrá (`gamecon.cz` a `admin.gamecon.cz` s kořenem `admin/`, `Alias` na obecnou stránku jako v ansible). Hlavní Apache, který používají ostatní webové testy, se tím nepřepíná. Přepínání samotné (příkazy rsync, ověření odpovědí) testuje `tests/Dev/RsyncNasazeniTest.php` proti falešnému `rsync`.

## Hotfix

Výjimku pro editaci přímo na serveru představuje hotfix. Pokud je nějaká kritická chyba, je možné kód změnit přímo na serveru po SSH a na githubu vytvořit pull request s odpovídajícími změnami. V pull requestu se uvede, že jde o hotfix. Další nasazení ruční úpravu přepíše, takže změna musí být do té doby sloučená.
