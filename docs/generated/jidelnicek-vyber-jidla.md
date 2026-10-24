# Jídelníček a výběr konkrétního jídla

TL;DR: porce jídla (Oběd pátek) se kupuje v e-shopu; 14 dní před GameConem se zveřejní jídelníček a účastník si k porci vybere konkrétní jídlo. Kdo si nevybere, dostane výchozí jídlo slotu. Pokrývá datový model, termíny, kudy se vybírá (přihláška, stránka Jídelníček, pult), admin a exporty.

## Pravidla

- **Jídelní lístek přichází 14 dní před akcí** a teprve pak se vybírá konkrétní jídlo; do té doby se objednává jen porce *(záměr)*. Termín je nastavení `JIDELNICEK_ZVEREJNIT_OD` (výchozí = začátek GC − 14 dní).
- **Výběr končí s prodejem jídla** (`JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE`): jídelna dostane počty konkrétních jídel ve stejnou chvíli jako počty porcí, žádný druhý termín *(záměr)*.
- **Kdo nevybere, dostane výchozí jídlo** *(záměr)*. Výchozí je jídlo s `is_default`, bez označení první v pořadí. Nikam se neukládá — dosazuje se při čtení (`MealSlot::getEffectiveDish()`, v SQL `JidlaZJidelnicku::efektivniJidloSql()`). Změna výchozího jídla v adminu tedy platí i pro ty, kdo už nevybrali, i po termínu.
- **Vybírá se jen k obědům a večeřím.** Snídaně (štítek `snidane`) jsou švédský stůl.
- **Stránka Jídelníček jen vybírá k už objednaným porcím**, porci tam koupit nejde; bez porce odkazuje do přihlášky *(záměr)*.
- **Pult vybírá i před zveřejněním a po termínu**, stejně jako po termínu doobjednává porce. Účastník po termínu nezmění nic.
- **Počty se berou jen z porcí přihlášených na letošní GameCon** — v adminu, v exportech i na stravenkách stejně, ať admin a jídelna nevidí jiná čísla.
- Všechna jídla slotu stojí stejně — cena visí na porci, výběr na ni nesahá. Jednotlivá jídla nemají kapacitu.

## Datový model

- `meal_dish` (`App\Entity\MealDish`): `variant_id` (porce = varianta produktu jídla), `year`, `name`, `description`, `vegetarian`, `is_default`, `position`. **Ročník je nutný**: produkty jídel přežívají ročníky, jídla ne.
- `shop_nakupy.meal_dish_id` (`OrderItem::$mealDish`): vybrané jídlo; NULL = nevybráno. Cizí klíč `RESTRICT`, ne `SET NULL` — vynulování databází by obešlo audit nákupů ([audit-zmen-nakupu](audit-zmen-nakupu.md)). Smazané jídlo proto nejdřív odebere výběry přes Doctrine (`MealMenuEditor::saveSlot()`).
- Slot = oběd nebo večeře letošního katalogu: produkt se štítkem `jidlo` bez `snidane`, nearchivovaný, jeho první varianta. Pořadí: den, oběd před večeří — druh se pozná jen z názvu produktu („Oběd …", „Večeře …").

## Vstupní body v kódu

- `symfony/src/Service/MealMenuReader.php` — sloty, jídelníček zákazníka (`forCustomer`), admin přehled s počty (`forAdmin`)
- `symfony/src/Service/MealDishChooser.php` — výběr jídla k porci, kontrola vlastníka porce, slotu a termínů
- `symfony/src/Service/MealMenuEditor.php` — uložení jídel jednoho slotu najednou
- `symfony/src/Service/MealMenuSchedule.php` — zveřejnění a konec výběru (čte `SystemoveNastaveni`)
- API: `/cart/meal-menu` (účastník), `/admin/customer-meal-menu` (pult, právo 100/101), `/admin/meal-menu` (editor, právo 108)
- UI: `ui/src/pages/jidlo/VýběrJídel.tsx` (v `JídloMatice` i samostatně jako `preact-vyber-jidel`), `ui/src/pages/jidelnicekAdmin/`
- Web: `web/widgety/jidelnicek.php` — widget `(widget:jidelnicek)` ve stránce Jídelníček (`stranky.url_stranky = 'jidelnicek'`); jídla vykreslí na serveru pro všechny, výběr je Preact
- Admin: Finance → Jídelníček (`admin/scripts/modules/finance/jidelnicek.php`)
- Exporty: `model/Report/JidlaZJidelnicku.php` → reporty `jidlo-pocty-jidel`, `jidlo-vyber-ucastniku`; stravenky tisknou jídlo porce (`stravenky.php` + `admin/files/stravenky.js`)

## Gotchas

- **Stránka Jídelníček je obsah z redakčního systému.** Widget se objeví jen tam, kde je v textu `(widget:jidelnicek)`. Migrace `2026-10-25-190200_jidelnicek-widget-na-strance.php` ho vložila jen do nezměněného textu; upravený text je potřeba doplnit ručně.
- **Testy a stará Symfony cache.** Legacy strana (`SystemoveNastaveni::getService()`, `dbSdileneSpojeni()`) bootuje vlastní kernel v prostředí `test` s `debug=false`, takže se jeho cache po přepnutí větve ani po změně `services.yaml` sama neobnoví. Projeví se to tak, že legacy a Doctrine nesdílí spojení (testy práv přes `PermissionVoter` padají na 403) nebo chybí veřejná služba. Lék: `./bin/console cache:clear --env=test --no-debug`.
- **Testovací databáze má termín prodeje jídla z ročníku 2025** (`000.php`, `vlastni = 1`), takže v testech je výběr vždycky „po termínu". Testy proto podstrkují vlastní `MealMenuSchedule` (v API testu přes `static::getContainer()->set()`).
- `MealWriter::save()` po uložení volá `EntityManager::clear()` — entity načtené před nákupem jsou pak odpojené.

## Otevřené

- E-mail „jídelníček je venku, vyber si" se neposílá.
- Výběr jídla se nezobrazuje ve finančním přehledu účastníka.
