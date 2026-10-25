# Nasazení ostré a bety přes rsync

TL;DR: `udrzba/nasad.php` nahrává kód na ostrou a betu buď přes rsync po SSH (je-li nastavený `DEPLOY_RSYNC_TARGET`), nebo přes FTP (`dg/ftp-deployment`), které zmizí, až rsync poběží na obou ([Issue #1385](https://github.com/gamecon-cz/gamecon/issues/1385)). Dokument říká, v čem se způsoby liší a podle čeho se ověřilo, že nahrávají totéž. Postup pro lidi je v `dokumentace/ftp-a-deployment.md`.

## Vstupní body v kódu

- `udrzba/nasad.php` — podle větve (`main` = ostrá, `beta`) skládá nastavení, čte `DEPLOY_RSYNC_TARGET` a přepínač `--dry-run`
- `udrzba/_pomocne.php::nasad`, `::buildIgnoreMasks`, `::uploadFilesViaFtp`, `::call_check_capture`
- `udrzba/rsync-nasazeni.php::uploadFilesViaRsync`, `::buildRsyncFilters`, `::buildRsyncCommand`, `::getRsyncProtectedPaths`
- `tests/Dev/RsyncNasazeniTest.php` — pravidla a příkaz proti falešnému `rsync`, který se chová jako ten skutečný (dev image `rsync` nemá a test nemá potřebovat server)
- `.github/workflows/deploy-ostra.yml`, `deploy-beta.yml`

## Jak to funguje

- Jedna sada masek ve formátu `dg/ftp-deployment` (`buildIgnoreMasks`) slouží oběma způsobům. Pro rsync se přeloží tak, aby vybíraly stejné soubory jako knihovna: pořadí se otočí (knihovna nechá vyhrát poslední shodu, rsync první), `!` se stane `+` a přidá nadřazené složky (knihovna je u výjimky odkrývá), masky s lomítkem se kotví v kořeni a písmena se mění na třídy `[xX]` (knihovna nerozlišuje velikost). Výchozí masky knihovny (`.git*`, `*.bak`, `.idea`…) jdou na začátek, protože u knihovny stojí na konci a přebíjejí výjimky. Co se takhle přeložit přesně nedá (`[`, `\`, `**`, výjimka uvnitř složky, kterou dřívější maska ignoruje), `assertMasksTranslatable()` odmítne hlasitou chybou, ne tichým rozdílem. Je to přibližná kontrola: maska s lomítkem na konci se bere jako složka a vzory se porovnávají navzájem, takže zachytí výjimku pod složkou ignorovanou doslova nebo hvězdičkou na jedné straně, ale ne dvě hvězdičky, které se překrývají.
- `--checksum --copy-links --delete-delay --delay-updates`: `migrace/symfony` je symbolický odkaz, který FTP rozbalovalo na složku, čas souboru nic neříká (po checkoutu a `composer install` je všude nový) a prosté `--delete` maže před přejmenováním nových souborů na místo.
- Server se ověřuje (`StrictHostKeyChecking=yes`, jen `DEPLOY_SSH_KNOWN_HOSTS`); klíč a `known_hosts` přijdou z GitHub secrets a `uploadFilesViaRsync` je na chvíli zapíše do souborů s právy `600`.
- Každý běh nejdřív naplánuje (`--dry-run` bez `--max-delete`) a skončí, kdyby se mělo smazat víc než `DEPLOY_RSYNC_MAX_DELETE`; `--max-delete` nese jen samotné nahrání a zastaví mazání až po tolika smazaných položkách. Musí chybět v plánu, protože skutečný rsync s ním zastaví i `--dry-run` kódem 25 a seznam smazaného se nevypíše. Plán je i kontrola, že server je dosažitelný a ověřený, ještě než se cokoli změní. Do počtu se nezapočítají složky, které `--dry-run` vypíše jako smazané, ale skutečný běh je kvůli chráněnému obsahu nechá (`cannot delete non-empty directory`); `--max-delete` skutečného běhu o jejich počet roste, protože rsync každý takový pokus počítá jako přeskočené mazání, jakmile je limit dosažen.

## Gotchas

- **FTP nemazalo nic, co samo nenahrálo; rsync maže vše mimo git.** Na serveru je proto víc, než je v gitu: nahrané fotky a avatary (`web/soubory/systemove/*/*`), dumpy `admin/*.sql.gz`, `cache/*` (včetně složky `cache/private<název databáze>`, kterou založila aplikace), `symfony/config/reference.php`. Tyhle cesty chrání pravidla `P` v `getRsyncProtectedPaths()`, která brání smazání a nahrávání neovlivní. Nová cesta, kam aplikace zapisuje uvnitř projektu, se musí přidat sem.
- **Pravidla `P` musí být před překládanými maskami.** Za nimi by je přebila `+` pravidla výjimek (`.htaccess`, `default.png` v `systemove/*/`) a server-only soubor toho jména by se smazal.
- **Ručně nahrané soubory, na které odkazuje kód:** `web/soubory/styl-rocnikovy/` (pozadí stránky v `web/soubory/styl/styl.less`) a `web/soubory/blackarrow/qrka/*.png` (loga pro `web/moduly/qrka.php`, zatím se nepoužívají). V gitu nejsou, takže jsou mezi chráněnými cestami. Správné řešení je dostat je do gitu a z ochrany je vyřadit.
- Pravidlo `P` na složku, která v gitu existuje, nechrání její obsah; pro takovou složku je potřeba tvar `/cesta/***`.
- Masky z `nastaveni/.gitignore` buildIgnoreMasks() kotví v kořeni repozitáře, ne v `nastaveni/`, takže nic nechrání (FTP to nevadilo, nic nemazalo). Opravit kotvení nejde, protože by se přestal nahrávat `nastaveni/nastaveni-server.php`; ručně nakopírované přihlašovací údaje ke Google jsou proto mezi chráněnými cestami.
- **Soubory nepatřící účtu, který nasazuje, rsync nesmaže** a skončí kódem 23 až po nahrání (`beta/backups.log` patří rootu). Plán je nezachytí, protože nic nemaže. Před zapnutím rsync je z cílové složky odstranit.
- **Chyba rsync po nahrání** (kód 23, 25, ztracené spojení) shodí nasazení před smazáním cache a migracemi, takže nový kód už může běžet bez migrací. Znovu spuštěné workflow to dokončí; automatický opakovaný pokus jako u FTP tu není. Visící symbolický odkaz ve zdrojovém stromu dává kód 23 a vypne i mazání, FTP ho potichu přeskakovalo.
- Skryté nastavení (`nastaveni/nastaveni-<prostředí>.php`) generuje `nasad()` přímo ve zdrojové složce před nahráním, takže je v nahrávaném stromu; kdo spouští suchý běh mimo `nasad()`, uvidí je jako mazané.
- Zapnout rsync znamená nastavit `*_DEPLOY_RSYNC_TARGET`; vypnout (zpět na FTP) smazat ho. Dokud FTP existuje, nasazení bez toho secretu skončí na FTP.
- Zkušební běh (`dry_run`) má vlastní skupinu `concurrency`; ve sdílené by nahradil jediné čekající skutečné nasazení, které GitHub zruší.
- **Ruční spuštění workflow patří na větev jeho prostředí** (`beta`, respektive `main`), jinak úloha `check-branch` skončí chybou dřív, než se cokoli sestaví. `nasad.php` vybírá nastavení podle větve, ale cíl (secrets, rsync, FTP) určuje workflow. Deploy BETA spuštěný na `main` by sestavil `nastaveni-produkce.php`, rsync by z bety smazal `nastaveni/nastaveni-beta.php` (zkušební běh ho tak hlásí) a volání cache a migrací po nahrání by šla na adresu ostré (`URL_ADMIN` z nastavení `main`). Klíč bety smí psát jen do bety, takže rsync ostrou nezasáhne; FTP takové omezení nemá.
- **Ochrana je součástí workflow na vybrané větvi.** Větev, která nemá poslední `main` (`beta` před prvním sloučením), ji nemá, a opakované spuštění staršího běhu používá workflow z jeho commitu. Úplné řešení by byly GitHub Environments s omezením větví a secrets uvnitř nich.

## Jak se ověřilo, že rsync nahrává totéž co FTP

`dg/ftp-deployment` umí cíl `file://`, takže se z jednoho zdrojového stromu nahrály obě varianty do místních složek a porovnaly přes `diff -r` (stejné soubory i obsah, přes sedmnáct tisíc souborů). Že porovnání umí selhat, se ověřilo vyřazením výchozích masek (`.git*`), kdy se rozdíl objevil okamžitě. Suchý běh `rsync -n --delete` proti ostré a betě ukázal, co by mazání navíc smazalo. Zbytek byly zbytky starších nasazení (`*.deploytmp`, staré `nastaveni-*.php`, soubory starších verzí vendoru).
