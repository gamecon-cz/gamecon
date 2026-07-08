# Přihlašování na skryté (nepublikované) technické a brigádnické aktivity

TL;DR: Posadit účastníka na **NOVA** (nepublikovanou, „v přípravě, neveřejnou") **technickou / brigádnickou** aktivitu jde jen s bitovým příznakem `Aktivita::INTERNI`. Ten se dnes odvozuje z práva **prezenčního admina** (`Pravo::ADMINISTRACE_PREZENCE`). Není to samostatná role — výjimka je vázaná na **typ aktivity**, ne na roli.

## Klíčový guard

`Aktivita::procNeniPrihlasovatelna()` (`model/Aktivita/Aktivita.php`), stavová podmínka. Na NOVA aktivitu se lze přihlásit jen když:

```php
$interni && $this->idStavu() == StavAktivity::NOVA && $this->typ()->jeInterni()
```

`typ()->jeInterni()` = `TypAktivity::interniTypy()` = `[TECHNICKA (10), BRIGADNICKA (102)]`. **Proto `INTERNI` z principu neumí přihlásit na jiný než technický/brigádnický typ** — pro běžnou aktivitu je `jeInterni()` false a příznak nedělá nic. Rozlišení „jen technické/brigádnické" je tím pádem vestavěné, nezávisí na tom, kdo příznak drží.

## Kde se `INTERNI` odvozuje

`Aktivita::zkontrolujZdaSeMuzePrihlasit()` a `Aktivita::prihlasovatelnaProPrihlasujiciho()` — obě metody přidají `self::INTERNI`, když `$prihlasujici->maPravoNaPristupDoPrezence()` (`Pravo::ADMINISTRACE_PREZENCE` = 103, drží role `Role::PREZENCNI_ADMIN` = 16). Sedí to hned vedle odvození `NEOTEVRENE|DOPREDNE` z `maPravoNaPrihlasovaniNaDosudNeotevrene()` (Pravo 9, drží Šéf programu).

Kontroluje se **operátor** (`$prihlasujici`), ne posazovaný uživatel. Přihlašování z programu (web i admin) jde přes jeden endpoint `web/moduly/api/aktivitaAkce.php` (admin `admin/scripts/api/aktivitaAkce.php` ho jen `require`-uje) → `Aktivita::prihlasovatkoZpracujBezBack($uPracovni, $u)`, kde `$u` (2. param `$prihlasujici`) je přihlášený operátor. Běžný účastník je sám sobě operátorem a právo 103 nemá → sám sebe na skrytou technickou nepřihlásí.

## Dvě brány, ne jedna (gotcha)

Povolit backendový guard (`INTERNI`) **nestačí**. Preact program bere aktivity z `web/moduly/api/aktivityUzivatel.php`, které skrytou aktivitu zahodí filtrem `!$aktivita->viditelnaPro($u)` **ještě před** výpočtem `prihlasovatelna`. `Aktivita::viditelnaPro()` proto pouští prezenčnímu adminovi (`maPravoNaPristupDoPrezence()`) právě **NOVA aktivity interního typu** (`jeInterniDleId`) — stejné zúžení jako enrollment guard. Záměrně **ne** ostatní neveřejné aktivity (jiný stav/typ), na ty přihlašovat nesmí. Obě místa (visibility + enrollment guard) musí zůstat v souladu, jinak buď admin aktivitu neuvidí, nebo naopak vidí víc, než na co smí sahat.

## Časová brána

`INTERNI` obchází jen **stavovou** podmínku. **První** podmínka v `procNeniPrihlasovatelna()` („Není spuštěna registrace aktivit") stále platí — projde jen v okně `probihaRegistraceAktivit()` (= od `prvniVlnaKdy` do `gcBeziDo`), nebo s `DOPREDNE`/`ZPETNE`. Prezenční admin `DOPREDNE` nedostává (to má jen právo 9), takže **mimo okno registrace posadit nepůjde**. Pokud by bylo potřeba i před první vlnou, přidat `DOPREDNE` k odvození INTERNI. (Druhá cesta — online prezence — používá `Aktivita::STAV`, který přeskočí stavovou podmínku, ale první podmínku taky ne.)

## Historie / záměr

- (záměr) Historicky žádná zvláštní role potřeba nebyla — org (např. Guff) si běžně posazoval své lidi na technické aktivity. Šlo o **výjimku na typ**, ne o roli.
- Výjimku dřív zapínala admin obrazovka „Program uživatele" (`Program::INTERNI => true`). Při migraci programu na **Preact** ten config vypadl — zůstal jen zakomentovaný `todo(tym)` blok v `admin/scripts/zvlastni/program-uzivatele.php` (konstanta `Program::INTERNI` už neexistuje). Nový Preact program posílá přihlášení s prázdnými parametry → `INTERNI` se nikde nenastavovalo → posazování na skryté technické přestalo fungovat.
- Rozhodnutí (uživatel, 2026-07): navázat `INTERNI` na právo prezenčního admina.

Test: `tests/Model/Aktivita/AktivitaTest.php::prezencniAdminMuzePosaditNaSkrytouTechnickouAktivitu`.
