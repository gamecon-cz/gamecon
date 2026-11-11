TL;DR: Kdo se hlásí na aktivitu se štítkem „Věk: 15+“ a v den aktivity mu tolik ještě nebude, uvidí před přihlášením upozornění, že se má předem domluvit se šéfem programu. Přihlášení to nebrání. Dokument popisuje, odkud se věk bere, kde se upozornění ukazuje a co schválně nedělá.

## Proč (záměr)

- Na larpy se v roce 2026 třikrát přihlásili lidé mladší, než je doporučený věk hry. Pravidlo pořadatelů: kdo chce na hru mimo svůj věk, má se předem zeptat a domluvit (Trello 1230).
- **Upozornit, ne zablokovat** (záměr): výjimku už může mít domluvenou. Kdyby se přihlášení blokovalo, museli by ho za něj přihlašovat organizátoři v adminu.
- Kontakt v textu: `Aktivita::KONTAKT_NA_SEFA_PROGRAMU` (`info@gamecon.cz`). Do Preactu jde i jako `GAMECON_KONSTANTY.KONTAKT_NA_SEFA_PROGRAMU` (`Program::gameconKonstanty()`, stránky programu i linií), jen aby z něj `UpozorneniNaVekModal` v textu udělal odkaz.
  - Kontakt na šéfa linie v databázi **je**: `akce_typy_hlavicky` (sekce, jméno, e-mail pro každý typ aktivity, admin Web → Hlavičky linií), stránka linie ho ukazuje v hlavičce (`ikonaLinieEmail`, bez záznamu `info@gamecon.cz`). Upozornění ho nepoužívá, posílá na šéfa programu. Kdyby se mělo přepnout na šéfa linie, kontakt je tam.
- Text upozornění je jen v PHP: `Aktivita::getUpozorneniNaVek()` vrátí `{nadpis, text}` (nebo `null`). Dialog v Preactu ho dostane z API programu nebo z HTML stránky linie a jen ho zobrazí, záložní `confirm()` ukáže totéž.

## Odkud je minimální věk

- Štítky kategorie „omezení Věk“ (`KategorieTagu::OMEZENI_VEK` = 50, založená v `migrace/000.php`) ve tvaru `Věk: N+`: `Tag::getMinimalniVek()`.
- „Věk: do 15“ je horní hranice dětských aktivit, minimální věk z něj není a nehlídá se (záměr).
- `Aktivita::getMinimalniVek()` vezme nejvyšší z věkových štítků aktivity (v datech 2026 má každá nejvýš jeden).
- Štítky bere ze stejného `$this->tagy` jako `tagy()` (`getObjektyTagu()`). První dotaz na štítky načte štítky celé kolekce (aktivity z jednoho `zFiltru()` / `zWhere()`) jedním dotazem (`prednactiTagy()`), na API programu i na stránkách linií.
  - Schválně ne přes `prednacitat`: ten přednačítá i přihlášky celého ročníku, jeden dotaz za ~100 ms. Stránku linie s pár aktivitami by to zpomalilo (lokálně deskoherna 14 → 116 ms v DB), štítky celé kolekce stojí pár milisekund.
  - Pořadí štítků drží `tagyId()` (`GROUP_CONCAT` seřazený podle kategorie a názvu). Proto se nečtou z cache `Tag::zIds(..., true)`: ta vrací napřed objekty z cache, pak nově načtené, a pořadí na kartě by se rozházelo.
- `Aktivita::getNesplnenyMinimalniVek($ucastnik, $prihlasujici)` vrátí minimální věk, když ho účastníkovi **v den začátku aktivity** ještě nebude, jinak `null`. Kdo má narozeniny během festivalu, ten se zbytečně neupozorňuje. Bez skutečného data narození nebo bez začátku aktivity (na takovou se přihlásit nedá) vrací `null`. Štítky čte až po levných kontrolách.
- **Skutečné datum narození** (`Uzivatel::isDatumNarozeniSkutecne()`): ne `0000-00-00` a dřív než den registrace. `Uzivatel::rychloregistrace()` (na místě, infopult) vyplní místo narození dnešek a admin anonymizované databáze (`AnonymizovanaDatabaze::pridejAdminUzivatele()`) `NOW()`, obojí v den registrace. Bez toho by dospělý rychloregistrovaný loni měl letos 0 let a upozornění dostal na každé aktivitě se štítkem věku. Staré účty mají `registrovan` `0000-00-00 00:00:00`, u nich se datum bere jako skutečné.
  - Report mladších 15 let (`zazemi-a-program-potvrzeni-pro-navstevniky-mladsi-patnacti-let.php`) vylučuje totéž v SQL (`z_rychloregistrace = 0 OR datum_narozeni != DATE(registrovan)`). `Uzivatel::vekKDatu()` to nehlídá, takže kontrola souhlasu rodičů v přihlášce (`prihlaska.php`) a označení mladších 18 v online prezenci rychloregistrovaného dál počítají jako novorozence.

## Kde se upozornění ukazuje

- **Program (Preact)**: `aktivityUzivatel.php` posílá u každé aktivity `upozorneniNaVek` (`{nadpis, text}`, nebo `null`). Počítá ho jen tam, kde `Přihlašovátko.tsx` nabídne přihlášení (přihlašovatelná, účastník na ní není nebo ji jen sleduje, nevede ji), takže minulé ročníky štítky vůbec nenačtou. Kliknutí řeší `klikniNaPřihlašovátko()` (`kliknutíNaPřihlašovátko.ts`): místo přihlášení otevře `UpozorneniNaVekModal` (`otevřiUpozorněníNaVěk()` ve `všeobecnéSlice.ts`), u týmové aktivity otevře nastavení týmu (`NastaveniTymuModal`) až po potvrzení.
  - Do statických souborů programu (`ProgramStaticFileGenerator::aktivitaDoPole`) se věk schválně nedává: ty se přegenerují až po změně aktivity, takže by po nasazení chyběl.
  - Počítá se na serveru, ne v prohlížeči, i když klient štítky i začátek aktivity má: pravidlo (tvar štítku, věk k začátku aktivity, výjimka pro admina) by jinak bylo dvakrát, v PHP pro stránky linií a v TS, a do prohlížeče by muselo datum narození. Na serveru to stojí jeden dotaz na štítky a průchod aktivitami.
- **Stránky linií** (`/larpy`, PHP): `Aktivita::getPrihlasovatkoSUpozornenimNaVek()` vykreslí odkaz, který zavolá Preact most `window.preactMost.upozorneniNaVek.otevri` (registruje ho `AktivityApp`) a formulář odešle až po potvrzení. Než se Preact načte, poslouží nativní `confirm()` s textem z `data-nadpis` a `data-upozorneni`.
  - Týmová aktivita má místo odkazu tlačítko `Aktivita::getPrihlasovatkoTymu()`: kdo na ní ještě není, dostane napřed stejné upozornění a nastavení týmu (`preactMost.prihlaseniTymu.otevri`) se otevře až po potvrzení. Bez Preactu tlačítko nefunguje vůbec, `confirm()` tu proto není.
  - Scroll stránky: `zachovejScroll()` v `aktivity.xtpl` ukládá pozici kliknutím na `form > a`, formuláře s `data-upozorneni` ale vynechá. Ty ji uloží až s potvrzením (`zachovejScrollUloz()`), zrušené upozornění by jinak nechalo pozici v `localStorage` a obnovila by se až na jiné stránce.
- **Bez upozornění**: přihlašování z adminu za někoho jiného (`$prihlasujici` ≠ účastník, v API `$operator`; kdo přihlašuje, má výjimku domluvenou, záměr), nastavení týmu, ve kterém už je, sledování (to jen pošle mail, přihlásí se pak běžným odkazem).

## Testy

- PHP: `tests/Aktivity/UpozorneniNaVekTest.php`, včetně rychloregistrace, tlačítka týmu a pole `upozorneniNaVek` z API (skript `aktivityUzivatel.php` se v testu načte přes `require` ve statické closure, která mu dá jen `$u`, `$uPracovni` a `$systemoveNastaveni`; jinak by přepsal proměnné testu).
- Vitest: `ui/src/store/program/slices/upozorněníNaVěk.test.ts` (dialog: potvrzení přihlásí jednou, zavření nic) a `ui/src/pages/program/components/tabulka/kliknutíNaPřihlašovátko.test.ts` (přihlášení i tým až po potvrzení). Skutečný store programu (zustand) v testech nejde načíst, chce React, proto testy podstrčí atrapy (`vi.mock`). Vitest nemá preset pro JSX, proto je logika kliknutí v `.ts` mimo komponentu.

## Gotchas

- Ručně poskládaný POST se na aktivitu přihlásí i bez upozornění. To je v pořádku, nic se neblokuje.
- Lokálně po skončení GameConu žádný odkaz „přihlásit“ není (`probihaRegistraceAktivit()` končí `GC_BEZI_DO`). Pro ruční zkoušku je potřeba dočasně posunout `GC_BEZI_DO`, dát aktivitě stav `AKTIVOVANA` a účastníkovi roli přihlášen na GC a mladší datum narození.
