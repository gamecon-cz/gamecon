# prepnuti-na-uzivatele (dříve „superadmin“)

TL;DR: Schopnost přihlásit se v adminu jako libovolný uživatel ("přepnout se na"). Dřív hardcoded pole `SUPERADMINI`, nově právo `PREPNUTI_NA_UZIVATELE` (114) visící na **ročníkové** roli, kterou každý rok znovu uděluje rada.

## Vstupní body v kódu
- Právo: `Gamecon\Pravo::PREPNUTI_NA_UZIVATELE` (= 114), `model/Pravo.php`.
- Role (ročníková): `Gamecon\Role\Role::LETOSNI_PREPINANI_UZIVATELE()`, base id `ROLE_PREPINANI_UZIVATELE_ID_ZAKLAD = 30`, význam `VYZNAM_PREPINANI_UZIVATELE`.
- Konstanta `ROLE_PREPINANI_UZIVATELE`: `nastaveni/nastaveni-role.php`.
- Vynucení práva: `admin/scripts/prihlaseni.php` (větev `prihlasitSeJakoUzivatel`) a `admin/index.php` (zobrazení omniboxu „přepnutí uživatele“). Kdo přepínač vůbec dostane, říká `PrepnutiNaUzivatele::smiPrepinat()`: kdo má `Uzivatel::muzePrepnoutNaJinehoUzivatele()` (= drží právo **nebo** `jsmeNaLocale()`), ten na kohokoli, infopulťák jen na koho pustí `PrepnutiNaUzivatele::getDuvodOdmitnutiInfopultaka()`.
- Samotné přepnutí a co si o něm pamatuje session: `Gamecon\Uzivatel\PrepnutiNaUzivatele` (`model/Uzivatel/PrepnutiNaUzivatele.php`).
- Auto-zakládání role pro nový ročník: `migrace/9999_01-letosni-role-krome-ucasti-endless.php` (iteruje `Role::vsechnyRocnikoveRole`).
- První zavedení: `migrace/2026-05-24-111857_prepnuti-na-uzivatele-pravo.php`.

## Pravidla / invarianty
- **(záměr)** Právo je **dočasné, resetuje se každý ročník.** Visí na ročníkové roli — při překlopení ročníku vznikne nová role-row s novým `id_role` a nikým přiřazeným; loňská přiřazení přestávají být „platná“ (view `platne_role` filtruje podle ROCNIK). Kdo má přepínat letos, musí dostat roli znovu.
- **(záměr)** **Uděluje rada** (`CLEN_RADY`), ne kdokoli: role má `kategorie_role = KATEGORIE_OMEZENA (0)`, takže `Uzivatel::maPravoNaPrirazeniRole()` ji pustí přidělit jen členu rady. Viz `Role::kategoriePodleVyznamu(VYZNAM_PREPINANI_UZIVATELE) => KATEGORIE_OMEZENA`.
- **(záměr)** Držení `CLEN_RADY` samo o sobě **nedává** přepínání — člen rady ji musí dostat přiřazenou jako každý jiný. Rada tedy právo *spravuje*, ale automaticky *nemá*.
- **(záměr)** Pro rok 2026 není přiřazen **nikdo** — rada přiřadí ručně přes `/admin/prava`. Do té doby nemůže přepínat nikdo.
- **(záměr)** **Na locale (`jsmeNaLocale()`) smí přepínat každý admin i bez přiřazené role.** Lokální vývoj jede na čerstvě naseedované DB, kde nikomu není přiřazená letošní ročníková role pro přepínání — bez tohoto pravidla by se vývojář na svém stroji nemohl přepnout vůbec. Na preview řeší tentýž problém deploy příkazem `app:grant-switch-user-role-to-devs` (uděluje roli Dev uživatelům po restore ostré DB); na locale to řeší přímo `muzePrepnoutNaJinehoUzivatele()`. Není to bezpečnostní díra — locale je vývojářův vlastní stroj.
- Infopulťák (`jeInfopultak`) má i nadále vlastní *omezené* přepínání nezávisle na tomto právu — viz `admin/index.php` / `prihlaseni.php`. Slouží k tomu, aby za vypravěče vyplnil prezenci: po přepnutí na vypravěče nebo partnera vede `prihlaseni.php` rovnou na jeho Moje aktivity.
- **(záměr)** Přepnutím dostane infopulťák **všechna práva cíle**, proto se smí přepnout jen na vypravěče nebo partnera, který mu nepřidá nic kromě Moje aktivity a výhod vlastního účtu (`PrepnutiNaUzivatele::PRAVA_KTERA_INFOPULTAK_SMI_PREVZIT`: slevy, věci a noci zdarma, značky ve výpisech). Organizátor, který vede aktivitu, je i vypravěč; bez tohoto pravidla by se přes něj infopulťák dostal k jeho panelům adminu a třeba k přepnutí na kohokoli. Právo, které cíl má a infopulťák ne a které v seznamu chybí, přepnutí odmítne s hláškou, jaká práva navíc to jsou; nové právo tedy přepnutí blokuje, dokud ho někdo vědomě nepřidá. Omnibox infopulťáka (`jenSRolemi`) takové vypravěče pořád nabízí, odmítne je až odeslání.
- Session si pamatuje, kdo se přepnul (`PrepnutiNaUzivatele`, klíč `prepnuto_z_uzivatele`; při dalším přepnutí pořád ten první). Na tom stojí:
  - **Odkaz „zpět na …“** v rámu adminu (operátor, oba vzhledy) i v online prezenci místo „na Program“ (vypravěč do adminu skoro nesmí). Je to GET `?zpetZPrepnuti=1`, protože online prezence má jen odkazy; návrat k sobě nic nepovoluje, padělaný odkaz neuškodí.
  - **Odhlášení odhlásí toho, kdo sedí u zařízení** (`Uzivatel::odhlas()`): session, trvalé přihlášení a cookie s JWT tohoto prohlížeče jsou jeho. Uživateli, na kterého se přepnul, se na jeho zařízeních nezruší nic, odhlášení platí jen pro toto zařízení, viz [prihlaseni-na-vice-zarizenich](prihlaseni-na-vice-zarizenich.md).
  - **JWT zůstává tomu, kdo sedí u zařízení**: přepnutí volá `Uzivatel::prihlasId(..., vytvoritJwt: false)`, jinak by přepsalo jediný soubor s JWT druhého uživatele. Totéž `Uzivatel::otoc()`, které celou session zničí a založí znovu; záznam o přepnutí přenese. Odhlášení ostatních zařízení a změna hesla za druhého (`Uzivatel::zmenRazitkoPrihlaseni()`) mu uložený JWT smažou a nový nevydají.
  - **Vzhled adminu se za druhého nevolí ani nepočítá** (bez dialogu, bez přepínače v patičce, bez statistik), viz [vzhled-adminu](vzhled-adminu.md).
  - Nové přihlášení (heslem, cookie trvalého přihlášení) začíná bez přepnutí.

## Gotchas
- **Na locale se větev infopulťáka neprojeví.** `muzePrepnoutNaJinehoUzivatele()` vrací na locale `true` každému a Docker obraz má `ENV=local`, takže v lokálním adminu i ve všech testech (PHPUnit i webových přes Apache) se každý admin přepne na kohokoli. Omnibox infopulťáka bez `jenSRolemi` a odmítnutí přes admin tak lokálně nikdo nevidí. Pravidlo samo ověřuje `tests/Uzivatel/PrepnutiNaUzivateleTest.php` přímým voláním `getDuvodOdmitnutiInfopultaka()`, `tests/web/PrepnutiInfopultuNaVypraveceTest.php` cestu od přepínače po uloženou prezenci a zpět. Přes admin to ručně vyzkoušíš jen tak, že `|| jsmeNaLocale()` dočasně odebereš.
- **Význam role se odvozuje z NÁZVU role**, ne z konstanty. Endless migrace dělá `kod_role = toConstantLikeName(prefix . ' ' . nazev_role)` a `vyznam = vyznamPodleKodu(kod_role)`. Proto `VYZNAM_PREPINANI_UZIVATELE` musí přesně odpovídat tomu, co vznikne z názvu „Přepínání na uživatele“ → `PREPINANI_NA_UZIVATELE` (vč. `NA_`). Nesoulad → `NeznamyVyznamRole` při migraci.
- Endless migrace kopíruje práva na novou roli z **loňské role-row se stejným `nazev_role`** — proto se právo 114 propisuje do dalších ročníků automaticky, jakmile existuje předchozí ročník s tou rolí.
- **Předseda rady** (chairperson) jako samostatný koncept v aplikaci **neexistuje** — jen `CLEN_RADY` (role 23). Všichni členové rady mají stejnou pravomoc přidělovat omezené role. Zúžení „jen předseda uděluje“ by byla samostatná feature.

## Otevřené otázky
- 4 z 5 původních hardcoded superadminů (mimo radu) ztrácí přepínání; přístup jim musí rada explicitně přiřadit (záměr ticketu 1445 — „ať se to nerozlézá bez vědomí rady“).
