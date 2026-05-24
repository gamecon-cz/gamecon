# prepnuti-na-uzivatele (dříve „superadmin“)

TL;DR: Schopnost přihlásit se v adminu jako libovolný uživatel ("přepnout se na"). Dřív hardcoded pole `SUPERADMINI`, nově právo `PREPNUTI_NA_UZIVATELE` (114) visící na **ročníkové** roli, kterou každý rok znovu uděluje rada.

## Vstupní body v kódu
- Právo: `Gamecon\Pravo::PREPNUTI_NA_UZIVATELE` (= 114), `model/Pravo.php`.
- Role (ročníková): `Gamecon\Role\Role::LETOSNI_PREPINANI_UZIVATELE()`, base id `ROLE_PREPINANI_UZIVATELE_ID_ZAKLAD = 30`, význam `VYZNAM_PREPINANI_UZIVATELE`.
- Konstanta `ROLE_PREPINANI_UZIVATELE`: `nastaveni/nastaveni-role.php`.
- Vynucení práva: `admin/scripts/prihlaseni.php` (větev `prihlasitSeJakoUzivatel`) a `admin/index.php` (zobrazení omniboxu „přepnutí uživatele“).
- Auto-zakládání role pro nový ročník: `migrace/9999_01-letosni-role-krome-ucasti-endless.php` (iteruje `Role::vsechnyRocnikoveRole`).
- První zavedení: `migrace/2026-05-24-111857_prepnuti-na-uzivatele-pravo.php`.

## Pravidla / invarianty
- **(záměr)** Právo je **dočasné, resetuje se každý ročník.** Visí na ročníkové roli — při překlopení ročníku vznikne nová role-row s novým `id_role` a nikým přiřazeným; loňská přiřazení přestávají být „platná“ (view `platne_role` filtruje podle ROCNIK). Kdo má přepínat letos, musí dostat roli znovu.
- **(záměr)** **Uděluje rada** (`CLEN_RADY`), ne kdokoli: role má `kategorie_role = KATEGORIE_OMEZENA (0)`, takže `Uzivatel::maPravoNaPrirazeniRole()` ji pustí přidělit jen členu rady. Viz `Role::kategoriePodleVyznamu(VYZNAM_PREPINANI_UZIVATELE) => KATEGORIE_OMEZENA`.
- **(záměr)** Držení `CLEN_RADY` samo o sobě **nedává** přepínání — člen rady ji musí dostat přiřazenou jako každý jiný. Rada tedy právo *spravuje*, ale automaticky *nemá*.
- **(záměr)** Pro rok 2026 není přiřazen **nikdo** — rada přiřadí ručně přes `/admin/prava`. Do té doby nemůže přepínat nikdo.
- Infopulťák (`jeInfopultak`) má i nadále vlastní *omezené* přepínání (jen na vypravěče/partnery) nezávisle na tomto právu — viz druhá větev v `admin/index.php` / `prihlaseni.php`.

## Gotchas
- **Význam role se odvozuje z NÁZVU role**, ne z konstanty. Endless migrace dělá `kod_role = toConstantLikeName(prefix . ' ' . nazev_role)` a `vyznam = vyznamPodleKodu(kod_role)`. Proto `VYZNAM_PREPINANI_UZIVATELE` musí přesně odpovídat tomu, co vznikne z názvu „Přepínání na uživatele“ → `PREPINANI_NA_UZIVATELE` (vč. `NA_`). Nesoulad → `NeznamyVyznamRole` při migraci.
- Endless migrace kopíruje práva na novou roli z **loňské role-row se stejným `nazev_role`** — proto se právo 114 propisuje do dalších ročníků automaticky, jakmile existuje předchozí ročník s tou rolí.
- **Předseda rady** (chairperson) jako samostatný koncept v aplikaci **neexistuje** — jen `CLEN_RADY` (role 23). Všichni členové rady mají stejnou pravomoc přidělovat omezené role. Zúžení „jen předseda uděluje“ by byla samostatná feature.

## Otevřené otázky
- 4 z 5 původních hardcoded superadminů (mimo radu) ztrácí přepínání; přístup jim musí rada explicitně přiřadit (záměr ticketu 1445 — „ať se to nerozlézá bez vědomí rady“).
