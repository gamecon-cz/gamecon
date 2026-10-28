Medailonky vypravěčů: stránka jednoho vypravěče na `/<id>`, sdílený vzhled karty medailonku a veřejný přehled všech vypravěčů ročníku na `/vypravaci` s filtrem podle sekcí.

## Vstupní body

- **Medailonek jednoho vypravěče** = `/<id>` (i `/<id>-<slug>` nebo `/<slug>`): `web/index.php` přes `Uzivatel::zUrl()` pustí modul `web/moduly/aktivity.php` s parametrem `org`, ten vypíše hlavičku (fotka, jméno, „o sobě“) a aktivity, které vede. Funguje pro každé ID uživatele, ne jen pro vypravěče.
- **Přehled vypravěčů** = `web/moduly/vypravaci.php` + `web/sablony/blackarrow/vypravaci.xtpl`, data z `model/Uzivatel/PrehledVypravecu.php`. Filtr sekce je druhá část URL: `/vypravaci/rpg`.
- **Vzhled karty** `.medailonky > .medailonek` = `web/soubory/blackarrow/_spolecne/medailonek.less`. Sdílí ho stránka Kontakty (HTML v DB, upravuje se v adminu), widgety `web/widgety/dnd-pj.*` a `drd-pj.*` a přehled vypravěčů.
- **Odkaz z programu**: tlačítko „Vypravěči“ vedle „Filtry“ v `ui/src/pages/program/components/vstupy/Vstupy.tsx`; v adminu je skryté (`JE_ADMIN`), relativní `href` by tam vedl do adminu.

## Pravidla

- Vypravěč = má ročníkovou roli `Role::LETOSNI_VYPRAVEC($rocnik)`, i když ještě nevede žádnou aktivitu (záměr: seznam se mění během příprav a má se propsat sám, bez editace stránky), **nebo** v ročníku vede netechnickou aktivitu ve stavu z `StavAktivity::bezneViditelneStavy()`, i když roli nemá (záměr: aktivity vedou i organizátoři bez role vypravěče a do přehledu patří). Netechnická = typ mimo `TypAktivity::interniTypy()` (technická a brigádnická), stejně jako na stránkách aktivit a titulce; kdo vede jen technickou nebo brigádnickou aktivitu a roli nemá, ve výpisu není.
- Sekce vypravěče = linie z menu webu (`TypAktivity::zViditelnych()`, v pořadí menu), ve kterých v ročníku vede aktivitu ve stavu z `StavAktivity::bezneViditelneStavy()`. Aktivita v přípravě ani loňská aktivita sekci nepřidá.
- Filtr nabízí jen sekce, ve kterých vede aspoň jeden vypravěč. URL linie z menu bez vypravěčů ukáže prázdný výpis, ne 404 (sdílený odkaz nemá vést na chybu); neznámá linie je 404.
- Řazení podle jména na webu českou abecedou (`serazenePodle()` s `Collator`).
- Fotka: `fotkaAuto()`, kdo nenahrál vlastní, má náhradní podle pohlaví (záměr: náhradní obrázek, když uživatel fotku nedodá). Jméno: `jmenoNaWebu()`, tedy podle volby uživatele (jméno a příjmení / přezdívka).
- „O sobě píše“ se rozbaluje jen u vypravěče, který text vyplnil; prázdná lišta by se rozbalila do ničeho.

## Gotchas

- XTemplate neescapuje. Jméno a název sekce escapuje modul (`htmlspecialchars`), „o sobě“ je HTML z Markdownu a jde ven, jak je.
- Vedoucí netechnické aktivity, která není v linii z menu (např. mistrovství v DrD, workshopy, když je menu nezobrazuje), ve výpisu je, ale bez sekce, a filtr sekcí ho nenajde.
