# Nový E-shop GameCon - Rozhodnuté funkce

Tento dokument obsahuje rozhodnutí o funkcích pro nový e-shop GameCon.

**Legenda:**
- ✅ = Chceme implementovat
- ❌ = Nechceme implementovat
- 🤔 = Možná později

---

## 1. Správa produktů

### Katalog produktů
- ✅ **Jednoduché produkty (jedna varianta)** - základní produkty bez variant, např. kostka, placka
- ✅ **Varianty produktů** - např. kombinace velikostí, barev (trička v různých velikostech a barvách)
- ✅ **Produktové možnosti s více hodnotami** - konfigurovatelné vlastnosti produktů (barva, velikost atd.)
- 🤔 **Asociace produktů** - související produkty, cross-sell, up-sell (např. 'Zákazníci, kteří koupili kostku, kupovali také placku')
- ✅ **Obrázky produktů** - možnost více obrázků na produkt, fotogalerie
- ✅ **Zapnutí/vypnutí produktu (řízení viditelnosti)** - Na toto použijeme logiku podobnou `\Gamecon\Shop\StavPredmetu`, část převezmou customer groups, ale `\Gamecon\Shop\StavPredmetu::MIMO` a `\Gamecon\Shop\StavPredmetu::POZASTAVENY` musíme mít jako viditelnost produktu
- 🤔 **Řazení a pozicování produktů** - ruční nastavení pořadí zobrazení

### Atributy produktů
- ❌ **Konfigurovatelné atributy produktů** - vlastní pole pro produkty (text, číslo, datum, výběr, checkbox)
- ✅ **Přeložitelné atributy** - podpora více jazyků (atributy s českými/anglickými názvy)

### Organizace produktů
- 🤔 **Hierarchické kategorie** - stromová struktura kategorií (Merchandise > Trička > Modrá trička)
- 🤔 **Přiřazení produktu do více kategorií** - jeden produkt může být ve více kategoriích najednou
- 🤔 **Obrázky a popisy kategorií** - fotka a text pro každou kategorii

---

## 2. Správa skladu

### Řízení zásob
- ✅ **Sledování zásob na produkt/variantu** - kontrola, kolik kusů máme na skladě
- ❌ **Úrovně zásob (na skladě, rezervováno)** - oddělení fyzicky dostupných a rezervovaných kusů
- ❌ **Upozornění na nízké zásoby** - automatické notifikace při poklesu pod limit
- ❌ **Obsluha vyprodaného zboží** - speciální zobrazení vyprodaných produktů
- ✅ **Prevence přeprodání** - blokace objednávky, když není dost na skladě (KRITICKÉ!)
- ✅ **Validace zásob v košíku a při objednávce** - kontrola při přidání do košíku a dokončení objednávky

---

## 3. Cenotvorba

### Správa cen
- ❌ **Základní cena + původní cena** - zobrazení přeškrtnuté původní ceny při slevě
- ❌ **Podpora více měn** - např. CZK, EUR (zůstáváme pouze u CZK)
- ❌ **Směnné kurzy** - převody mezi měnami
- ❌ **Sledování historie cen** - uchování záznamů o změnách cen
- ❌ **Nejnižší cena před slevou (EU compliance)** - zobrazení nejnižší ceny za posledních 30 dní

---

## 4. Akce a slevy

### Systém slev
- ✅ **Procentuální slevy** - Chceme, ale pouze na základě zákaznických skupin (organizátor, běžný účastník...)
- ❌ **Fixní slevy** - např. sleva 50 Kč
- ❌ **Slevové kupóny s kódy** - např. 'LETO2025' = 10% sleva
- ❌ **Limity použití kupónů** - limity na kupón/zákazníka
- ❌ **Časově omezené akce** - datum začátku/konce akce
- ❌ **Priorita a pravidla kombinování akcí** - jak se kombinují různé slevy

### Pravidla akcí (podmínky)
- ❌ **Prahová hodnota celkového košíku** - např. sleva při nákupu nad 1000 Kč
- ❌ **Prahová hodnota množství v košíku** - např. při 5+ kusech
- ❌ **Konkrétní produkt v košíku** - pokud má zákazník v košíku konkrétní produkt
- ❌ **Produkt z konkrétní kategorie** - pokud má zákazník produkt z dané kategorie
- ✅ **Příslušnost k zákaznické skupině** - slevy pro organizátory, běžné účastníky atd.
- ❌ **Sleva na N-tou objednávku** - např. každá 10. objednávka se slevou

### Akce slev
- ❌ **Procentuální sleva na objednávku** - sleva na celkovou částku
- ❌ **Fixní sleva na objednávku** - fixní částka z objednávky
- ❌ **Procentuální sleva na položku** - sleva na konkrétní položky
- ❌ **Fixní sleva na položku** - fixní částka z položek
- ❌ **Doprava zdarma** - akční doprava
- ❌ **Kup X dostaneš Y zdarma** - bundle akce

### Katalogové akce
- ❌ **Automatické snížení cen** - přednastavené snížení cen produktů
- ❌ **Akce založené na produktech** - akce pro konkrétní produkty
- ❌ **Akce založené na kategoriích** - akce pro celé kategorie
- ❌ **Naplánované akce** - automatické spuštění/ukončení akcí

### Pokročilé funkce cen a slev
- ✅ **Rekalkulace při změně role** (MUST) - při změně rolí org/vypravěč se automaticky přepočítají slevy a aktualizuje reporting
- ✅ **Zamrazení ceny prodeje** (SHOULD) - při změně ceny produktu zůstane starým zákazníkům původní cena (i po rekalkulaci)
- 🤔 **Násilná rekalkulace** (COULD) - možnost vynutit přepočet cen i zákazníkům, kteří už koupili (minimální use-case, ale teoreticky potřeba)
- 🤔 **Nastavení slevy podle role v e-shopu** (COULD) - přenesení definice slev z práv do e-shopu (pružnější správa)

**Poznámka:**
- Rekalkulace při změně role je KRITICKÁ - organizátoři získávají/ztrácejí slevy během roku
- Zamrazení ceny chrání zákazníky před navýšením cen po nákupu
- Násilná rekalkulace slouží jen pro extrémní případy (chyba v ceně, změna dodavatele)

---

## 5. Objednávky a pokladna

### Proces objednávky
- ❌ **Vícekrokový checkout proces** - kroky: košík → adresa → doprava → platba → přehled → potvrzení (použijeme jednodušší flow)
- ❌ **Možnost objednání bez registrace (host checkout)** - nákup jako host bez účtu (vyžadujeme registraci)
- ❌ **Zadání/výběr adresy** - fakturační a dodávací adresa (nepotřebujeme)
- ❌ **Výběr způsobu dopravy** - např. osobní vyzvednutí, pošta, zasička (vše vyzvednutí na místě)
- ❌ **Výběr platební metody během checkoutu** - výběr způsobu platby
- ✅ **Kontrola a potvrzení objednávky** - finální kontrola před dokončením
- ✅ **Vyprázdnění košíku po úspěšné objednávce** - automatické vyčištění košíku

### Správa objednávek
- ✅ **Vytváření a sledování objednávek** - kompletní správa objednávek
- ✅ **Unikátní čísla objednávek** - každá objednávka má své číslo (např. GC2025-001234)
- ✅ **Stavy objednávek** - košík, nová, zpracovává se, dokončená, zrušená (lifecycle objednávky)
- ✅ **Historie a časová osa objednávky** - záznam všech změn (vytvořeno, zaplaceno, vyexpedováno...)
- ✅ **Zobrazení detailu objednávky** - kompletní detail pro zákazníka i admina
- ✅ **Filtrování a vyhledávání objednávek** - podle zákazníka, data, stavu, platby... (admin rozhraní)
- ✅ **Poznámky a komentáře k objednávkám** - interní poznámky adminů

### Zpracování objednávek
- ❌ **Obsluha plateb objednávek** - zpracování přes platební bránu (používáme stávající systém)
- ❌ **Sledování zásilek** - tracking čísla zásilek
- ✅ **Zrušení objednávky** - možnost zrušit objednávku
- ✅ **Dokončení objednávky** - označení objednávky jako dokončené
- ✅ **Úprava objednávky (omezené scénáře)** - admin může upravit objednávku

### Položky objednávky
- ✅ **Položky objednávky s množstvím** - seznam položek a jejich počty
- ✅ **Úpravy objednávky** - daně, poplatky za dopravu, slevy
- ✅ **Zachování detailů na úrovni položek** - historická cena, název atd.

---

## 6. Platby

### Správa plateb
- ❌ **Více platebních metod** - např. bankovní převod, QR platba, hotovost (používáme stávající systém)
- ❌ **Konfigurace platebních metod** - nastavení platebních metod
- ❌ **Integrace platební brány** - např. GoPay, Česká spořitelna (používáme stávající systém)
- ❌ **Stavy plateb** - nová, zpracovává se, dokončená, neúspěšná, zrušená, refundovaná
- ❌ **Zapnutí/vypnutí platební metody** - aktivace/deaktivace metod

### Funkce plateb
- ❌ **Bezpečné zpracování plateb** - PCI compliance (používáme stávající systém)
- ❌ **Potvrzení platby** - automatické potvrzení po platbě
- ❌ **Obsluha neúspěšné platby** - zpracování failed plateb
- ❌ **Podpora refundací** - vrácení peněz zákazníkovi
- ❌ **Překlady platebních metod** - vícejaz yčné názvy metod

**Poznámka:** Platby zůstanou v současném systému GameCon Finance, nový e-shop jen vytvoří objednávku.

---

## 7. Doprava

### Způsoby dopravy
- ❌ **Více způsobů dopravy** - různé možnosti dopravy (vše osobní vyzvednutí na akci)
- ❌ **Konfigurace způsobů dopravy** - nastavení dopravců
- ❌ **Výpočet nákladů na dopravu** - podle částky, váhy, zóny
- ❌ **Zapnutí/vypnutí způsobu dopravy** - aktivace/deaktivace
- ❌ **Překlady způsobů dopravy** - vícejazční  názvy

### Správa zásilek
- ❌ **Sledování zásilek** - tracking
- ❌ **Stavy zásilek** - připravena, odeslaná, doručená, zrušená
- ❌ **Správa dodací adresy** - adresy pro doručení
- ❌ **Doručovací zóny a pravidla** - geografické zóny

**Poznámka:** Doprava není potřeba, vše se vyzvedává osobně na akci GameCon.

---

## 8. Daně

### Správa daní
- ❌ **Daňové kategorie** - různé druhy daní
- ❌ **Daňové sazby na kategorii** - různé sazby DPH
- ❌ **Daně založené na zónách** - geografické daně
- ❌ **Procentuální sazby DPH** - různé úrovně DPH
- ❌ **Ceny s DPH / bez DPH** - zobrazení s/bez daně
- ❌ **Výpočet daní na objednávkách** - automatický výpočet
- ❌ **Podpora více daňových sazeb** - kombinace sazeb

**Poznámka:** Ceny jsou finální včetně DPH, nepotřebujeme složitý daňový systém.

---

## 9. Správa zákazníků

### Zákaznické účty
- ✅ **Registrace zákazníků** - nový zákazník si vytvoří účet
- ✅ **Profily zákazníků** - informace o zákazníkovi
- ✅ **Skupiny zákazníků** - organizátoři, běžní účastníci atd. (pro slevy)
- ✅ **Zobrazení historie objednávek** - zákazník vidí své objednávky
- ✅ **Ověření e-mailu** - potvrzení e-mailové adresy
- ✅ **Reset/změna hesla** - self-service správa hesla
- ✅ **Úprava účtu** - změna profilu
- ✅ **Zapnutí/vypnutí zákazníka** - admin může deaktivovat účet

**Poznámka:** Používáme stávající systém uživatelů GameCon.

### Správa adres
- ❌ **Adresář** - seznam uložených adres
- ❌ **Více adres na zákazníka** - výběr z adres
- ❌ **Fakturační adresy** - adresa pro fakturu
- ❌ **Dodací adresy** - adresa pro doručení
- ❌ **Výběr výchozí adresy** - preferovaná adresa
- ❌ **Vytvoření, úprava, smazání adresy** - CRUD adres

**Poznámka:** Adresy nepotřebujeme, vše osobní vyzvednutí.

### Autentizace uživatelů
- ✅ **Přihlášení/odhlášení zákazníka** - login/logout
- ✅ **Správa administrátorských uživatelů** - admin účty
- ✅ **Role a oprávnění uživatelů** - přístupová práva
- ✅ **Správa sessions** - session management

**Poznámka:** Používáme stávající autentizační systém GameCon.

---

## 10. Nákupní košík

### Funkce košíku
- ✅ **Přidat do košíku** - základní operace přidání produktu
- ✅ **Aktualizovat množství** - změna počtu kusů
- ✅ **Odebrat položky** - smazání z košíku
- ✅ **Shrnutí košíku s celkovými částkami** - přehled ceny
- ✅ **Zobrazení aplikovaných akcí** - viditelné slevy
- ❌ **Aplikace kódu kupónu** - zadání slevového kódu
- ✅ **Perzistence košíku** - uložení košíku do DB pro přihlášené uživatele
- ❌ **Widget mini-košíku** - ikona košíku v hlavičce
- ❌ **Sledování opuštěných košíků** - tracking neúspěšných nákupů

---

## 11. Funkce e-shopu (frontend)

### Zobrazení produktů
- ❌ **Úvodní stránka** - dedikovaná homepage e-shopu
- ❌ **Stránky se seznamem produktů** - listing pages
- ❌ **Detailní stránky produktů** - product detail page
- ❌ **Vyhledávání produktů** - fulltext search
- ❌ **Filtrování produktů podle atributů** - filtry
- ❌ **Řazení produktů** - název, cena, datum, pozice
- ❌ **Procházení kategorií** - category navigation
- ❌ **Zobrazení recenzí a hodnocení produktů** - reviews & ratings
- ❌ **Zobrazení souvisejících produktů** - related products

**Poznámka:** Frontend bude minimalistický, integrovaný do stávajícího GameCon webu.

### Nákupní zážitek
- ❌ **Responzivní design** - mobile-first (použijeme stávající design GameCon)
- ❌ **Galerie obrázků produktů** - slideshow obrázků
- ❌ **Zobrazení dostupnosti zásob** - stock indicator
- ❌ **Zobrazení ceny s/bez DPH** - price breakdown
- ❌ **Odznaky slev** - discount badges
- ❌ **Odznaky nových produktů** - new product badges

---

## 12. Administrační rozhraní

### Administrace
- ❌ **Dashboard s přehledovými statistikami** - úvod adminu s grafy
- ✅ **Plné CRUD operace** - Budeme muset použít co nejvíce současného rozhraní adminu, kde část CRUD operací je, ale podle práv admina
- ✅ **Tabulkové/seznamové zobrazení** - moderní gridy s filtrováním, řazením, stránkováním
- ✅ **Hromadné akce** - bulk operations (označit více položek a provést akci) - NUTNÉ pro hromadné zrušení objednávek neplatících
- ✅ **Nahrávání a správa obrázků** - upload produktových fotek
- ✅ **Řízení přístupu a oprávnění** - podle rolí adminů
- ✅ **KFC mřížkové prodejní rozhraní** - speciální UI pro rychlý prodej na místě u pokladny (zachovat)
- ✅ **Admin prodej za jiného uživatele** - admin může nakoupit položky pro jiného uživatele (pole id_objednatele)

**Poznámka:** Maximálně využijeme stávající admin rozhraní GameCon.

---

## 13. Internacionalizace

### Podpora více jazyků
- ✅ **Více jazyků/locales** - čeština a angličtina
- ✅ **Přeložitelný obsah** - produkty, kategorie, atributy, akce, způsoby dopravy, platební metody
- ✅ **Přepínání jazyků** - language switcher
- ✅ **Výchozí jazyk na kanál** - defaultní locale

### Geografické funkce
- ❌ **Správa zemí** - seznam zemí
- ❌ **Kraje/regiony** - regions/provinces
- ❌ **Geografické zóny** - geographic zones
- ❌ **Pravidla založená na zónách** - zone-based rules pro doprav u/daně

**Poznámka:** Nepotřebujeme geografické funkce, zaměřujeme se na ČR.

---

## 14. Podpora více kanálů

### Správa kanálů
- ❌ **Více prodejních kanálů** - multi-channel e-commerce
- ❌ **Specifické pro kanál** - produkty, ceny, měny, jazyky, daně, doprava, platby

**Poznámka:** Máme jen jeden kanál - GameCon.

---

## 15. Komunikace

### E-mailový systém
- ✅ **E-maily s potvrzením objednávky** - order confirmation
- ✅ **E-maily s potvrzením platby** - payment confirmation (pokud integrace s Finance to umožní)
- ❌ **E-maily se sledováním zásilky** - shipping tracking (nepotřebujeme)
- ✅ **Potvrzení registrace** - registration email (stávající systém)
- ✅ **E-maily pro reset hesla** - password reset (stávající systém)
- ❌ **Odběr newsletteru** - newsletter subscription
- ❌ **Kontaktní formulář** - contact form (stávající má GameCon)

---

## 16. Bezpečnost

### Bezpečnostní funkce
- ✅ **Autentizace uživatelů** - login system
- ✅ **Šifrování hesel** - password hashing
- ✅ **Ochrana proti CSRF** - CSRF tokens
- ✅ **Ochrana proti XSS** - input sanitization
- ✅ **Řízení přístupu pro adminy** - admin access control
- ✅ **Bezpečný checkout** - secure order process
- ❌ **Soulad s PCI** - PCI compliance pro platby (řeší stávající Finance systém)

**Poznámka:** Používáme bezpečnostní mechanismy podle současné aplikace GameCon.

---

## 17. Reporty a analytika

### Reporty
- ✅ **Nákupní reporting** - kolik čeho objednat/nakoupit (MUST) - počet triček podle barvy a velikosti, počet jídel podle dnů a typů, celkové počty merchandise
- ✅ **Finanční reporting** - kolik čeho se prodalo za kolik (MUST) - počet triček zdarma vs placených, počet jídel se slevou vs zdarma, rozdělení podle zákaznických skupin
- 🤔 **Log prodejů v čase** - pro projekce rozpočtu (COULD) - suma/počet vstupného k datu, počty prodaných merchů v čase, % registrovaných
- ❌ **Prodejní reporty** - komplexní sales reports (stávající BFSR/BFGR)
- ❌ **Statistiky objednávek** - order statistics
- ❌ **Příjmy podle období** - revenue by period
- ❌ **Nejprodávanější produkty** - best sellers
- ❌ **Statistiky zákazníků** - customer stats
- ❌ **Efektivita akcí** - promotion effectiveness

**Poznámka:**
- E-shop MUSÍ poskytovat **nákupní** a **finanční** reporting pro operativní rozhodování
- Stávající BFSR/BFGR reporty zůstanou, e-shop jim poskytne data
- Nákupní reporting je kritický pro objednávání triček, jídla, merchandise před akcí

---

## 18. API a integrace

### REST API
- ✅ **API pro produkty** - product endpoints
- ✅ **API pro objednávky** - order endpoints
- ✅ **API pro zákazníky** - customer endpoints
- ✅ **API pro sklad** - inventory endpoints
- ✅ **Autentizace (JWT/OAuth)** - API authentication
- ✅ **Dokumentace API** - OpenAPI/Swagger docs

---

## 19. Vývojářské funkce

### Technická infrastruktura
- ❌ **Stavový automat pro workflow** - Symfony Workflow (nebudeme používat)
- ✅ **Systém událostí pro rozšiřitelnost** - event dispatcher
- ✅ **Databázové migrace** - migration system
- ✅ **Podpora automatizovaného testování** - unit & integration tests
- ✅ **Logování a sledování chyb** - logging & error tracking
- ✅ **Optimalizace výkonu** - performance optimization
- ✅ **Strategie cachování** - caching strategies

**Poznámka:** Bez Symfony Workflow, ale s eventy, logy, migracemi a testy.

---

## 20. Další funkce

### Nice-to-Have funkce
- ❌ **Recenze a hodnocení produktů** - product reviews
- ❌ **Funkcionalita wishlistu** - wishlist feature
- ❌ **Porovnání produktů** - product comparison
- ❌ **Nedávno zobrazené produkty** - recently viewed
- ❌ **Notifikace o dostupnosti zásob** - back in stock notifications
- ❌ **Dárkové poukazy/vouchery** - gift cards
- ❌ **Systém věrnostních bodů** - loyalty program
- ❌ **Obnovení opuštěných košíků** - cart recovery
- ❌ **Předobjednávky** - pre-orders
- ❌ **Backordery** - backorder management

---

## 21. GameCon-specifické funkce

### Ubytování
- ✅ **Snižování kapacity všech dnů ubytování** (MUST) - při prodeji ubytování snížit kapacitu pro VŠECHNY noci (ne jen koupené), protože nerecyklujeme postele
- ✅ **Oddělené interní kapacity ubytování** (SHOULD) - kapacity pro vypravěče/orgy oddělené od běžných účastníků (řeší rezervy)
- ✅ **Forced bundling ubytování** (MUST) - možnost vynutit prodej nocí společně (čt+pá+so jako balíček), omezené jen na účastníky (ne org pool)
- ✅ **Sdílení pokoje** - funkcionalita "s kým chceš být na pokoji" (z OLD_ESHOP)

**Poznámka:**
- **Snižování kapacity všech dnů** je KRITICKÉ - zabránit over-bookingu
- **Oddělené kapacity** řeší problém s odhadem a rozpouštěním rezerv
- **Forced bundling** např. vynutit prodej Čt+Pá+So společně pro běžné účastníky (orgy/vypravěči mohou kupovat jednotlivě)

### Balíčky produktů (Bundles)
- ✅ **Forced bundles** - možnost vynutit prodej produktů společně (např. ubytování čt+pá+so)
- ✅ **Podmínky podle zákaznické skupiny** - forced bundle může být omezený jen na určité skupiny zákazníků
- ❌ **Balíčky se slevou** - klasické bundle s výhodnou cenou (nekupóny, není potřeba)

**Příklad forced bundle:**
- Produkt "Ubytování" s variantami: Středa, Čtvrtek, Pátek, Sobota, Neděle
- Bundle "Víkendový balíček" = Čtvrtek + Pátek + Sobota (forced pro skupinu "účastník")
- Organizátoři a vypravěči mohou kupovat dny jednotlivě (nejsou v forced bundle)

### Merchandise a jídlo
- ✅ **Matrixový výběr jídel** - UI pro výběr jídel (dny × typy jídel)
- ✅ **Ukončení prodeje individuálně** (COULD) - možnost nastavit stop stav pro každý item v admin UI (ne přes /nastavení)
- ✅ **Kompletní CRUD v admin** (SHOULD) - přidání/editace produktů bez reimportu

---

## Shrnutí - Co budeme implementovat

### ✅ CORE FUNKCE (Priorita 1)

#### Produkty
- Jednoduché produkty + varianty + možnosti
- **Všechny objednatelné položky jako varianty produktů** (ubytování, jídlo, vstupné, aktivity)
- **Tagy produktů** (pro detekci slev místo kódu produktu)
- **Přidružené entity** (Activity, Food) pro komplexní položky
- **Časově omezená dostupnost** (pole available_until)
- Obrázky produktů (galerie)
- Řízení viditelnosti (podle StavPredmetu logiky)
- Přeložitelné názvy/popisy (CS/EN)
- **Bez multi-year modelů** (každý rok nové produkty)

#### Sklad
- Sledování zásob na variantu
- **Prevence přeprodání** (KRITICKÉ!)
- Validace při přidání do košíku a checkout

#### Slevy
- Procentuální slevy podle zákaznických skupin (organizátor vs běžný účastník)
- Aplikace slev automaticky podle přihlášeného uživatele
- **Rekalkulace při změně role** (MUST) - automatický přepočet slev
- **Zamrazení ceny prodeje** (SHOULD) - ochrana zákazníků před navýšením cen

#### Košík
- Plnohodnotný košík (add/update/remove)
- Perzistence v DB pro přihlášené
- Zobrazení aplikovaných slev

#### Objednávky
- Entita objednávky s unikátním číslem
- Stavy objednávek (košík, nová, zpracovává se, dokončená, zrušená)
- **Rozlišení zákazníka a objednatele** (customer_id vs ordered_by_id)
- Historie změn objednávky
- **Archiv zrušených objednávek** s důvodem zrušení
- Admin: filtrování, vyhledávání, zobrazení detailu

#### Zákazníci
- Používáme stávající systém uživatelů GameCon
- Skupiny zákazníků pro slevy

#### Admin
- Gridy s filtrováním a stránkováním
- **Kompletní CRUD produktů** (SHOULD) - přidání/editace bez reimportu
- Upload obrázků
- **Hromadné akce** (bulk cancel objednávek)
- **KFC prodejní rozhraní** (rychlý prodej na místě)
- **Admin prodej za jiného uživatele** (id_objednatele)
- **Individuální ukončení prodeje** (COULD) - stop stav pro každý item v admin UI

#### API
- REST API pro produkty, objednávky, zákazníky, sklad
- JWT autentizace
- Dokumentace (Swagger)

#### I18N
- Čeština + Angličtina
- Překlad produktů, kategorií atd.

#### Technické
- Event system
- Migrace
- Testy (unit, integration)
- Logování
- Caching

#### Reporting
- **Nákupní reporting** (MUST) - počty produktů pro objednání
- **Finanční reporting** (MUST) - co se prodalo za kolik, rozdělení zdarma/se slevou

#### GameCon-specifické
- **Snižování kapacity všech dnů ubytování** (MUST) - prevence over-bookingu
- **Oddělené kapacity ubytování** (SHOULD) - org/vypravěč vs účastníci
- **Forced bundling** (MUST) - vynutit prodej nocí společně
- **Sdílení pokoje** - "s kým chceš být"
- **Matrixový výběr jídel** - UI pro výběr jídel

### 🤔 MOŽNÁ POZDĚJI (Priorita 2)

- Asociace produktů (related, cross-sell)
- Řazení/pozicování produktů
- Hierarchické kategorie
- Více kategorií na produkt
- Obrázky kategorií
- **Log prodejů v čase** (COULD) - pro projekce rozpočtu
- **Násilná rekalkulace** (COULD) - vynutit přepočet cen starým zákazníkům
- **Nastavení slevy podle role v e-shopu** (COULD) - přenesení slev z práv do e-shopu

### ❌ NEBUDE (Out of scope)

- Složité atributy (checkbox, date...)
- Více měn
- Historie cen
- EU compliance (nejnižší cena)
- Kupóny
- Časové akce
- Složitá pravidla slev
- Vícekrokový checkout
- Host checkout
- Adresy
- Doprava
- Složité daně
- Platební brány (používá se Finance)
- Refundace
- Mini-košík widget
- Opuštěné košíky
- Dedikovaný frontend (integrujeme do stávajícího)
- Dashboard se statistikami
- Reporty (máme BFSR/BFGR)
- Více kanálů
- Nice-to-have (wishlist, porovnání, věrnostní body...)

---

## Integrace se stávajícím systémem GameCon

Nový e-shop **MUSÍ** využívat:
1. **Uživatelský systém** - stávající účty, autentizace, oprávnění
2. **Finance systém** - platby zůstanou v současném Finance modulu
3. **Admin rozhraní** - maximálně využít stávající admin UI a navigaci
4. **E-mailový systém** - stávající Symfony Mailer
5. **Bezpečnost** - CSRF, XSS ochrana podle současné aplikace

Nový e-shop **PŘIDÁ**:
1. Entitu **Objednávka** (Order) s číslem a stavy
2. Entitu **Košík** (Cart) perzistentní v DB
3. **Řízení zásob** s prevencí přeprodání
4. **REST API** pro integraci
5. **Vícejaz yčnost** (CS/EN) pro produkty

---

## 🆕 Rozhodnuté funkce ze současného e-shopu

Tato sekce obsahuje rozhodnutí o funkcích, které existují v současném e-shopu a byly analyzovány pro nový e-shop.

### ✅ Speciální typy produktů a vazby na aktivity

**Rozhodnutí:** Vše co je k objednání (v přihlášce, nebo v aktivitách) musí být **varianta produktu**.

**Architektura:**
- **Základní produkty s variantami** - cena, dostupnost, a další vlastnosti pro prodej pocházejí z produktu a varianty
- **Přidružené entity** - komplexní položky jako ubytování a jídlo budou mít navázané entity k produktu/variantě (Activity, Food)
- **Speciální vlastnosti a informace** pocházejí z přidružených entit

**Příklady:**
- **UBYTOVÁNÍ** - Produkt "Ubytování" s variantami (Středa, Čtvrtek, Pátek, Sobota, Neděle)
  - Entity `Activity` nebo `Accommodation` navázaná na variantu s dodatečnými informacemi (kapacita, sdílení pokoje)
- **JÍDLO** - Produkty "Snídaně", "Oběd", "Večeře" s variantami podle dnů
  - Entita `Food` navázaná na variantu s dodatečnými informacemi (typ jídla, den)
- **VSTUPNÉ** - Produkt "Dobrovolné vstupné" s možností pay-what-you-want
  - Vlastní cena nastavitelná zákazníkem (custom field na variantě)
- **TRIČKA** - Produkt "Tričko" s variantami (velikost × barva)
  - Standardní produktová logika
- **MERCHANDISE** - Produkty "Kostka", "Placka", "Blok" atd.
  - Standardní produktová logika s tagy pro slevy

### ✅ Tagy produktů pro detekci slev

**Rozhodnutí:** Používat **tagy/štítky produktů** místo detekce podle kódu produktu.

**Implementace:**
- Produkt může mít tagy: `kostka`, `placka`, `org-merch`, `tricko-org-modre`, `tricko-org-cervene`
- Slevy se aplikují na základě tagů, ne podle `jeToKostka()`, `jeToPlacka()` metod
- Flexibilnější, nez nezávislé na pojmenování/kódu produktu
- Tagy lze kombinovat (produkt může mít více tagů)

**Příklady:**
- Produkt "Kostka GameCon" má tag `kostka` → sleva "Kostka zdarma pro organizátory"
- Produkt "Modrá organizátorská trička" má tagy `tricko`, `org-merch`, `modre` → slevy podle skupiny zákazníka

### ❌ Multi-year produktové modely

**Rozhodnutí:** Každý rok nové produkty **bez vazby** na minulé roky.

**Zdůvodnění:**
- Jednodušší databázové schéma (bez pole `model_rok`)
- Každý rok "čistý štart" s novými cenami
- Historická data zůstanou v objednávkách (uložená historická cena)
- Nebudeme vytvářet nové verze stejných produktů

**Důsledky:**
- Produkty z minulých let se archivují (soft-delete nebo změna stavu na MIMO)
- Reporty napříč roky budou dotazovat archivované produkty
- Migrace dat z minulých let proběhne jednorázově při spuštění nového e-shopu

### ✅ Automatické pozastavení prodeje podle data

**Rozhodnutí:** Ano, implementovat **automatické pozastavení podle data/času** (pole `nabizet_do`).

**Implementace:**
- Pole `available_until` (datetime, nullable) na produktu/variantě
- Po vypršení se stav automaticky změní na POZASTAVENY
- Použití pro časově omezené nabídky (např. "Jídlo objednatelné do 15.7. 23:59")
- Snižuje ruční práci adminů

**Příklady:**
- Jídlo: `available_until = '2025-07-15 23:59:00'` → po termínu se automaticky pozastaví
- Early bird merchandise: `available_until = '2025-05-01 00:00:00'` → po 1.5. nedostupné

### ✅ Admin prodej za jiného uživatele

**Rozhodnutí:** Ano, zachovat rozlišení **zákazníka a objednatele** (pole `id_objednatele`).

**Implementace:**
- Pole `customer_id` - komu patří objednávka (zákazník)
- Pole `ordered_by_id` - kdo provedl objednávku (může být admin)
- Využití v KFC prodejním rozhraní na akci
- Trasovatelnost, kdo provedl objednávku

**Příklady:**
- Admin na pokladně prodá tričko pro účastníka:
  - `customer_id = 123` (účastník)
  - `ordered_by_id = 456` (admin u pokladny)

### ✅ KFC mřížkové prodejní rozhraní

**Rozhodnutí:** Ano, **zachovat KFC rozhraní** pro prodej na místě.

**Zdůvodnění:**
- Aktivně používané každý rok u pokladny na akci
- Rychlý výběr zákazníka, okamžitý prodej položky
- Generování QR platby pro zákazníka
- Kritické pro provoz během akce

**Implementace:**
- Speciální admin endpoint/stránka pro KFC rozhraní
- Rychlé vyhledání zákazníka (ID, jméno, nickname)
- Mřížka s produkty pro rychlý výběr
- Okamžité vytvoření objednávky a zobrazení QR platby

### ✅ Hromadné zrušení objednávek

**Rozhodnutí:** Ano, implementovat **bulk cancel operations**.

**Zdůvodnění:**
- Používá se v automatických skriptech pro zrušení objednávek neplatících účastníků
- Zrušení ubytování/jídla/merchandise pro všechny neplatící najednou
- Kritické pro finanční管理 před a po akci

**Implementace:**
- Admin může označit více objednávek a provést hromadné zrušení
- API endpoint pro bulk operace: `POST /api/orders/bulk-cancel`
- Možnost zrušit objednávky podle kritérií (typ produktu, stav platby, datum)

**Příklady operací:**
- Zrušit všechny objednávky ubytování pro neplatící
- Zrušit všechny objednávky jídla vytvořené před datem X a nezaplacené
- Zrušit všechny objednávky daného uživatele

### ✅ Archiv zrušených objednávek

**Rozhodnutí:** Ano, zachovat **samostatnou tabulku pro zrušené objednávky** s důvodem zrušení.

**Implementace:**
- Tabulka `shop_orders_cancelled` (nebo `cancelled_order_items`)
- Pole `cancelled_at`, `cancelled_by_id`, `cancellation_reason`
- Auditní stopa kdo a proč zrušil
- Možnost reportování zrušených objednávek

**Struktura:**
```sql
cancelled_order_items:
  - id (původní ID položky objednávky)
  - order_id
  - customer_id
  - product_variant_id
  - quantity
  - purchase_price (historická cena)
  - ordered_at (původní datum objednávky)
  - cancelled_at (datum zrušení)
  - cancelled_by_id (kdo zrušil)
  - cancellation_reason (důvod: 'non-payment', 'customer-request', 'admin-bulk-cancel', 'out-of-stock')
```

### ✅ Časově omezená dostupnost produktů

**Rozhodnutí:** Implementovat pole `available_until` pro automatické pozastavení.

**Použití:**
- Časově omezené nabídky (early bird merchandise)
- Deadline pro objednání jídla/ubytování
- Automatické vypnutí prodeje po datu

**Poznámka:** Již zmíněno výše v "Automatické pozastavení prodeje podle data".

### 📋 Další poznámky k architektuře

#### Integrace s Finance systémem
- E-shop vytváří objednávky, Finance systém řeší platby
- API mezi e-shopem a Finance pro výpočet slev (organizátorské bonusy)
- E-shop poskytuje data pro BFSR/BFGR reporty
- Generování QR plateb zůstane v Finance modulu

#### Speciální UI komponenty
Rozhodnutí o UI ponecháno na implementaci:
- **Matrixový výběr jídel** - zachovat nebo upravit podle nového designu
- **Dynamické přidávání triček** - standardní variantový výběr (velikost × barva)
- **Posuvník vstupného** - zachovat gama korekci a smajlíky (nice-to-have UX feature)

---

## 📊 Dodatečné požadavky z CSV (WIP Sheet)

Tato sekce obsahuje požadavky z CSV souboru "Nový e-shop - zadání WIP", které byly zapracovány do dokumentu.

### ✅ MUST požadavky (implementováno)

1. **Nákupní reporting** - Sekce 17
   - Kolik čeho objednat/nakoupit (počet triček podle barvy a velikosti, počet jídel)
   - KRITICKÉ pro operativní rozhodování před akcí

2. **Finanční reporting** - Sekce 17
   - Kolik čeho se prodalo za kolik (triček zdarma vs placených, jídel se slevou vs zdarma)
   - Rozdělení podle zákaznických skupin

3. **Rekalkulace při změně role** - Sekce 4 (Pokročilé funkce cen a slev)
   - Při změně org/vypravěč se automaticky přepočítají slevy
   - Aktualizuje se reporting

4. **Blokace prodejů přes počet** - Sekce 2 (Prevence přeprodání)
   - Již bylo v původním plánu ✅

5. **Snižování kapacity všech dnů ubytování** - Sekce 21
   - Při prodeji ubytování snížit kapacitu pro VŠECHNY noci (ne jen koupené)
   - Prevence over-bookingu, protože nerecyklujeme postele

6. **Forced bundling ubytování** - Sekce 21
   - Možnost vynutit prodej nocí společně (např. čt+pá+so jako balíček)
   - Omezitelné jen na účastníky (org pool může kupovat jednotlivě)

### ✅ SHOULD požadavky (implementováno)

7. **Zamrazení ceny prodeje** - Sekce 4 (Pokročilé funkce cen a slev)
   - Při změně ceny produktu zůstane starým zákazníkům původní cena
   - Ochrana zákazníků před navýšením cen (i po rekalkulaci)

8. **Kompletní editace v admin** - Sekce 12, Sekce 21
   - CRUD pro produkty v admin UI bez reimportu
   - Řeší přidání 1 položky nebo změnu tří položek

9. **Oddělené interní kapacity ubytování** - Sekce 21
   - Kapacity pro vypravěče/orgy oddělené od běžných účastníků
   - Řeší problém s odhadem a rozpouštěním rezerv

### 🤔 COULD požadavky (možná později)

10. **Log prodejů v čase** - Sekce 17
    - Pro projekce rozpočtu (suma/počet vstupného k datu, počty merchů v čase)

11. **Násilná rekalkulace** - Sekce 4 (Pokročilé funkce cen a slev)
    - Možnost vynutit přepočet cen i starým zákazníkům
    - Minimální use-case (chyba v ceně, změna dodavatele)

12. **Nastavení slevy podle role** - Sekce 4 (Pokročilé funkce cen a slev)
    - Přenesení slev z práv na e-shop (pružnější správa)

13. **Ukončení prodeje** - Sekce 21
    - Individuálně pro každý item v admin UI (ne přes /nastavení)

### ❓ NEROZHODNUTO (k diskusi)

14. **Meziroční kontinualita předmětu** - ??? priorita
    - "Aby si e-shop pamatoval kolik čeho zbývá do dalšího roku"
    - V praxi často shazujeme do pytle "staré X"
    - **Rozhodnutí:** Zatím NEBUDE - konflikt s rozhodnutím "každý rok nové produkty bez vazby"

15. **Zamrazení slev z rolí ke konci GC** - ??? priorita
    - "V praxi by se spíš měly zamrazit ty role"
    - **K diskusi:** Řešit na úrovni rolí nebo e-shopu?

### 📝 Implementační poznámky

#### Forced bundling - technické řešení
- Entita `ProductBundle` s polem `forced` (boolean)
- Pole `applicable_to_customer_groups` - array skupin, pro které je bundle povinný
- Validace při přidání do košíku: pokud zákazník je ve skupině s forced bundle, nemůže koupit varianty jednotlivě
- Příklad: Bundle "Víkendový balíček" (Čt+Pá+So) je forced pro skupinu "účastník", ale ne pro "organizátor"

#### Snižování kapacity všech dnů ubytování
- Při prodeji varianty "Ubytování - Pátek" se sníží kapacita pro:
  - Středa, Čtvrtek, Pátek, Sobota, Neděle (všechny dny)
- Logika: jedna postel = obsazená pro celý víkend, nerecyklujeme
- Implementace: hook/event listener na `OrderItem.created` pro ubytování

#### Oddělené kapacity ubytování
- Pole na variantě produktu:
  - `capacity_total` - celková kapacita
  - `capacity_org` - rezervovaná kapacita pro org/vypravěče
  - `capacity_participant` - dostupná kapacita pro běžné účastníky
- Validace podle `customer_group`: org může čerpat z `capacity_org`, účastník z `capacity_participant`

#### Rekalkulace při změně role
- Event listener na `User.role_changed`
- Načte všechny aktivní objednávky uživatele (stav != completed/cancelled)
- Přepočítá slevy podle nové role
- Zachová `original_price` (zamrazení), ale aktualizuje `final_price`
- Aktualizuje reporting

---

**Poznámka:** Tento dokument je živý - můžeme funkce přesouvat mezi prioritami podle potřeby během vývoje.
