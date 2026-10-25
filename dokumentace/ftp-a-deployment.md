
## FTP

__Editování kódu přes FTP je zakázáno.__ Kód se na server dostává automaticky (viz dál) a ruční nahrání by způsobilo skrytý rozdíl mezi obsahem gitu a serveru, což způsobuje hodně těžko odhalitelné bugy, které se mohou objevit s velkým spožděním a úplně nečekaně.

Ručně nahrávat přes FTP je možné fotky do `web/soubory/systemove/fotky` případně různý obsah do podsložek `web/soubory/obsah`.

__Editování struktury databáze přes Adminer je zakázáno.__ K změnám struktury databáze se používají skripty v složce `migrace`. Strukturou se rozumí přidávání sloupců, tabulek a podobně.

## Deployment

Nasazení na ostrou i betu (vč. upgrade databáze) provádí skript `udrzba/nasad.php`. Spouští ho GitHub Actions: [`deploy-ostra.yml`](../.github/workflows/deploy-ostra.yml) po pushi do `main`, [`deploy-beta.yml`](../.github/workflows/deploy-beta.yml) po pushi do větve `beta`. Nasazení jedou postupně (`concurrency`), testy běží před nimi.

Soubory se nahrávají jedním ze dvou způsobů a vybírá se secretem:

- **rsync po SSH**, když je nastavený `DEPLOY_RSYNC_TARGET`. Server se ověřuje připnutým `known_hosts`.
- **FTP** (knihovna `dg/ftp-deployment`), dokud ten secret chybí. Zmizí spolu s FTP účtem nasazení.

Které soubory se nahrávají, určují masky v `buildIgnoreMasks()` v [`udrzba/_pomocne.php`](../udrzba/_pomocne.php), společné pro oba způsoby. Po nahrání nasazení vždy zavolá na serveru `admin/deploy/*`: smaže cache, spustí migrace databáze a přegeneruje cache programu.

### rsync

Ostrá a beta mají každá vlastní klíč, který smí zapisovat jen do složky svého prostředí. Secrets v GitHubu:

| Secret | Obsah |
| --- | --- |
| `BETA_DEPLOY_RSYNC_TARGET`, `OSTRA_DEPLOY_RSYNC_TARGET` | cíl rsyncu, tedy `uživatel@server:cesta/` |
| `BETA_DEPLOY_SSH_KEY`, `OSTRA_DEPLOY_SSH_KEY` | soukromý klíč účtu pro nasazení |
| `DEPLOY_SSH_KNOWN_HOSTS` | řádky `known_hosts` serveru; bez nich se nasazení nespustí |

Volitelné proměnné repozitáře (Settings → Secrets and variables → Variables): `DEPLOY_SSH_PORT` a dva limity mazání, nejvíc položek (souborů i složek), které jedno nasazení smí smazat: `DEPLOY_RSYNC_MAX_DELETE_PROJECT` pro vše mimo `vendor/` (výchozí 500) a `DEPLOY_RSYNC_MAX_DELETE_VENDOR` pro `vendor/` (výchozí 5000). `vendor/` je v gitu, takže větší aktualizace závislostí, přeinstalování nebo úklid starých balíčků smaže stovky až tisíce souborů (první plán pro betu: 1793 starých vývojových balíčků), kdežto zbytek se mění po desítkách. V historii od roku 2013, počítáno jako rsync (soubory i složky), smazala nejvíc skutečná aktualizace ve `vendor/` 495 položek a jednou přeinstalování celého `vendor/` 8269; mimo `vendor/` smazaly přes sto položek čtyři commity, nejvíc 526. Takový výjimečný commit limit překročí a nasazení se zastaví, dokud se proměnná nezvýší. Nižší limit mimo `vendor/` tak ještě pozná rozbitou masku nebo chráněnou cestu. Při `0` nasazení selže, jakmile by se v dané části mělo cokoli mazat. Každý běh, i zkušební, nejdřív nanečisto spočítá, co by smazal, a při překročení kteréhokoli limitu skončí dřív, než cokoli změní; hláška říká, který limit a kolik položek by se smazalo.

Rozdíly proti FTP, které je dobré znát:

- **Mazání.** FTP mazalo jen soubory, které samo nahrálo (seznam `.htdeployment` na serveru). rsync `--delete` smaže všechno, co v gitu není. Co na serveru vzniká za běhu nebo se tam nahrálo ručně (nahrané fotky, dumpy databáze, cache), chrání `getRsyncProtectedPaths()` v [`udrzba/rsync-nasazeni.php`](../udrzba/rsync-nasazeni.php). Když aplikace začne zapisovat do nové cesty uvnitř projektu, patří sem také.
- **Změněný soubor** se pozná podle obsahu (`--checksum`), ne podle času, protože po checkoutu a `composer install` mají všechny soubory nový.
- **Souběh a atomicita.** Soubory se nahrají pod dočasnými názvy, na místo se přejmenují až po nahrání všech (`--delay-updates`) a mazání jde teprve po přejmenování (`--delete-delay`; prosté `--delete` by mazalo dřív, než nové soubory dorazí). Souběh dál hlídá `concurrency` ve workflow.
- **Chyba uprostřed.** Když rsync skončí chybou (spojení, soubor, který nejde smazat), nasazení skončí červeně před smazáním cache a migracemi a server je částečně aktualizovaný. Po odstranění příčiny stačí workflow spustit znovu: rsync dokončí rozdíl a pak proběhnou migrace. Automatický opakovaný pokus, jaký měl FTP, tu není.
- **Ruční úpravy na serveru** (hotfix) rsync při příštím nasazení přepíše.

Workflow „Deploy BETA on direct push or merged pull request“ (`deploy-beta.yml`) se spouští ručně na větvi `beta`, „Deploy OSTRA (main) on direct push or merged pull request“ (`deploy-ostra.yml`) na větvi `main` (Actions → Run workflow → Use workflow from). Na jiné větvi skončí úloha `check-branch` chybou a nic se nenasadí, protože `nasad.php` skládá nastavení podle názvu větve a do složky jednoho prostředí by nahrál nastavení druhého. Ochrana je ve workflow z vybrané větve: dokud ji `beta` nemá (dostane ji sloučením `main`, což je skutečné nasazení bety), **nespouštějte Deploy OSTRA s volbou „Use workflow from: beta“**, protože by na ostrou nahrál kód bety a smazal z ní `nastaveni-produkce.php` s přihlašovacími údaji k databázi. Opakované spuštění staršího běhu (Re-run) nasadí znovu jeho původní commit.

Co by nasazení udělalo, ukáže ruční spuštění workflow se zaškrtnutým `dry_run`: proběhnou testy a pak `rsync --dry-run`, který vypíše nahrávané a mazané soubory, a nic se nezmění ani na serveru, ani v databázi. Zkušební běh má vlastní skupinu `concurrency`, takže nezruší čekající skutečné nasazení. Volba `dry_run` je také ve workflow z vybrané větve, takže na `beta` je až po sloučení `main`. Funguje jen pro prostředí, které má rsync nastavený (cíl, klíč i `known_hosts` v secrets výše); jinak `nasad()` skončí chybou až po testech.

### Co musí být na serveru

Řeší se v ansible, tady jen požadavky z pohledu nasazení:

- účet s přihlášením klíčem, který smí spustit jen `rsync` (ne shell) a jen do složky ostré, respektive bety; role `rsync_deployer` v ansible mu dává stejné uid (33) jako má `www-data`;
- právo zápisu do té složky a do podsložek, ve kterých se něco mění (rsync nahrává pod dočasným názvem a přejmenovává, takže vlastník samotného souboru nerozhoduje) a nově vytvořené soubory musí být čitelné pro `www-data`;
- `rsync` ve stejné nebo novější major verzi než na straně GitHub Actions;
- složky, do kterých účet nasazení nesmí zapisovat (typicky založené rootem), nesmí obsahovat nic, co se má smazat nebo přepsat, jinak rsync skončí chybou. Soubor vlastněný rootem ve složce, kam účet nasazení zapisuje, smazat jde (kromě složky se sticky bitem, kde smí mazat jen vlastník souboru nebo složky).

## Hotfix

Výjimku pro editaci přes FTP představuje hotfix. Pokud je nějaká kritická chyba, je možné kód změnit přímo na ftp a na githubu vytvořit pull request s odpovídajícími změnami. V pull requestu se uvede, že jde o hotfix.
