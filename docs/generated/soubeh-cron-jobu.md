TL;DR: Joby cronu, které rozesílají maily nebo hromadně mění data, běží pod pojmenovaným zámkem MariaDB (`Gamecon\Cron\ZamekJobu`), aby dva překrývající se běhy neudělaly totéž dvakrát. Druhý běh nečeká, zapíše do logu, že job přeskakuje, a nic neudělá.

## Proč

- 17. 7. 2026 pustil cron-job.org `admin/cron.php?job=aktivity_hromadne` v 00:00:49 a znovu v 00:01:09. Oba běhy poslaly „1. varování“ 37 neplatičům a v `hromadne_akce_log` jsou dva řádky `email-varovani-neplaticum-brzke-odhlaseni-1-…` (log na ostré `logy/cron/cron-aktivity_hromadne-2026-07.log`).
- Vzor všech dotčených jobů: zeptej se `hromadne_akce_log`, jestli už je hotovo → desítky sekund rozesílej → teprve na konci zapiš. Druhý běh, který se zeptá mezitím, udělá všechno znovu. `UpominaniDluzniku` sice loguje po každém mailu, ale seznam už upomenutých načte jen na začátku, takže souběh ho neochrání.

## Vstupní body

- `model/Cron/ZamekJobu.php` — zámek (`zamkni()` vrací hned `false`, když ho drží jiný běh; `odemkni()`).
- `admin/cron/_cron_job.php` — zámek `neplatici` kolem všech jobů neplatičů (odhlášení, aktivace, maily CFO a neplatičům, nespárované platby), zámek `promlceni-automaticke` kolem `jobs/promlceni_automaticke.php`.
- `model/Uzivatel/UpominaniDluzniku.php` `odesliUpominkyDluznikum()` — zámek `upominani-dluzniku`.
- `model/Uzivatel/PromlceniZustatku.php` `odesliVarovneEmaily()` — zámek `promlceni-zustatku-varovani`.

## Pravidla

- Pod zámkem musí být **kontrola i zápis do logu** v jednom běhu. Zámek jen kolem rozesílky nepomůže.
- Joby neplatičů mají **jeden společný zámek**, ne každý svůj: aktivace aktivit musí pracovat s dokončeným odhlášením, nesmí běžet vedle odhlašování v jiném běhu. Když hromadný job (`aktivity_hromadne`) zámek nedostane, přeskočí jen joby neplatičů a pokračuje dalšími.
- **Nečeká se** (`GET_LOCK(…, 0)`): joby mají na svou práci okno hodin až dní (varování od 3 dnů před odhlášením, odhlášení do dne po platnosti) a další běh cronu ji dodělá. Čekání by v jednom PHP procesu (test, kde druhý běh startuje uvnitř prvního) zablokovalo samo sebe.
- **Vlastní neperzistentní spojení** (`dbConnectTemporary(selectDb: false)`), ne hlavní: hlavní je perzistentní (`p:`) a přežije request, takže zámek běhu, který spadl na časový limit, by na něm visel, dokud spojení nepoužije další request. Neperzistentní spojení PHP zavře s koncem requestu i po fatální chybě. `selectDb: false` navíc obejde `UPDATE systemove_nastaveni` v `_nastavRocnikDoSpojeni()`, který by v testech čekal na zámky řádků z testovací transakce.
- **Název zámku obsahuje `DB_NAME`** (zahashovaný, MariaDB bere nejvýš 64 znaků): pojmenované zámky platí pro celý server a ostrá, beta, archivy i preview sdílejí jednu MariaDB. Bez toho by job na betě zablokoval ostrou.
- `odemkni()` volá `RELEASE_LOCK` výslovně: samotné zavření spojení server zpracuje až po odpojení klienta a běh hned po nás by zámek mohl najít obsazený.

## Testy

- `tests/Cron/ZamekJobuTest.php` — vzájemné vyloučení dvou instancí (dvě spojení), uvolnění.
- `UpominaniDluznikuTest::druhyBehSpustenyBehemRozesilkyPrvnihoNicNeodesle`, `PromlceniZustatkuTest::druhyBehSpustenyPredKoncemPrvnihoNicNeodesle` — druhý běh se spustí uvnitř prvního (podtřída `UpominkaDluznikaLog` po první upomínce, resp. podtřída `PromlceniZustatku` v `najdiUzivateleKPromlceni()`). Bez zámku padají (druhý běh rozešle).
- `tests/web/SoubehCronuTest.php` — skutečný `admin/cron.php` přes Apache, zatímco test drží zámek. Cron se volá **bez parametru `cas`**: každý spuštěný job hned zahlásí „Chybí čas předaný GET parametrem 'cas'“ a skončí, takže se pozná, co běželo, bez stahování plateb z Fia a rozesílání. S `cas=now` job opravdu běží (v testovací DB se pustil do Fia a trval 50 s).

## Co zámek nekryje

- Hodinový cron bez `?job=` (`admin/cron.php`): `Aktivita::upozorniNaNeuzavreneKonciciOdDo()` posílá vypravěčům maily podle časového okna, dva souběžné běhy by je poslaly dvakrát.
- `materializace_roli_podle_rocniku`, `synchronizace_historickych_plateb`, `archiv_prednasek_po_gc`, `mazani_nepripravenych_tymu` — nerozesílají maily, na souběh nebyly prověřeny `(nejisté)`.
