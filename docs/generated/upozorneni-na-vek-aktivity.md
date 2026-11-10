TL;DR: Kdo se hlásí na aktivitu se štítkem „Věk: 15+“ a v den aktivity mu tolik ještě nebude, uvidí před přihlášením upozornění, že se má předem domluvit se šéfem programu. Přihlášení to nebrání. Dokument popisuje, odkud se věk bere, kde se upozornění ukazuje a co schválně nedělá.

## Proč (záměr)

- Na larpy se v roce 2026 třikrát přihlásili lidé mladší, než je doporučený věk hry. Pravidlo pořadatelů: kdo chce na hru mimo svůj věk, má se předem zeptat a domluvit (Trello 1230).
- **Upozornit, ne zablokovat** (záměr): výjimku už může mít domluvenou. Kdyby se přihlášení blokovalo, museli by ho za něj přihlašovat organizátoři v adminu.
- Kontakt v textu: `Aktivita::KONTAKT_NA_SEFA_PROGRAMU` (`info@gamecon.cz`), stejná adresa je v `UpozorneniNaVekModal.tsx`. Žádný kontakt na šéfa linie v databázi není, je jen celoroční role „Šéf programu“.

## Odkud je minimální věk

- Štítky kategorie „omezení Věk“ (`KategorieTagu::OMEZENI_VEK` = 50, založená v `migrace/000.php`) ve tvaru `Věk: N+`: `Tag::getMinimalniVek()`.
- „Věk: do 15“ je horní hranice dětských aktivit, minimální věk z něj není a nehlídá se (záměr).
- `Aktivita::getMinimalniVek()` vezme nejvyšší z věkových štítků aktivity (v datech 2026 má každá nejvýš jeden). Štítky čte z cache `Tag::zIds(..., true)`, protože se program ptá na stovky aktivit naráz.
- `Aktivita::isMladsiNezMinimalniVek()` počítá věk k **začátku aktivity**, ne k začátku GameConu: kdo má během festivalu narozeniny, ten se zbytečně neupozorňuje. Bez data narození vrací `false`.

## Kde se upozornění ukazuje

- **Program (Preact)**: `aktivityUzivatel.php` posílá u každé aktivity `nesplnenyMinimalniVek` (minimální věk, nebo `null`). `Přihlašovátko.tsx` pak místo přihlášení otevře `UpozorneniNaVekModal` (`otevřiUpozorněníNaVěk()` ve `všeobecnéSlice.ts`).
  - Do statických souborů programu (`ProgramStaticFileGenerator::aktivitaDoPole`) se věk schválně nedává: ty se přegenerují až po změně aktivity, takže by po nasazení chyběl.
- **Stránky linií** (`/larpy`, PHP): `Aktivita::prihlasovatkoSUpozornenimNaVek()` vykreslí odkaz, který zavolá Preact most `window.preactMost.upozorneniNaVek.otevri` (registruje ho `AktivityApp`) a formulář odešle až po potvrzení. Než se Preact načte, poslouží nativní `confirm()` s textem z `data-upozorneni`.
- **Bez upozornění**: přihlašování z adminu za někoho jiného (`$operator` ≠ účastník v `aktivityUzivatel.php`; kdo přihlašuje, má výjimku domluvenou, záměr), týmové aktivity (zatím), sledování (to jen pošle mail, přihlásí se pak běžným odkazem).

## Gotchas

- Ručně poskládaný POST se na aktivitu přihlásí i bez upozornění. To je v pořádku, nic se neblokuje.
- Lokálně po skončení GameConu žádný odkaz „přihlásit“ není (`probihaRegistraceAktivit()` končí `GC_BEZI_DO`). Pro ruční zkoušku je potřeba dočasně posunout `GC_BEZI_DO`, dát aktivitě stav `AKTIVOVANA` a účastníkovi roli přihlášen na GC a mladší datum narození.
