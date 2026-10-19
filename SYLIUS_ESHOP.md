# Funkce E-shopu GameCon - Plán

Tento dokument obsahuje seznam základních funkcí e-commerce systému, které je potřeba implementovat v e-shopu GameCon, na základě analýzy platformy Sylius Standard.

## 1. Správa produktů

### Katalog produktů
- Jednoduché produkty (jedna varianta)
- Varianty produktů (např. kombinace velikostí, barev)
- Produktové možnosti s více hodnotami
- Asociace produktů (související produkty, cross-sell, up-sell)
- Obrázky produktů s možností více obrázků na produkt
- Zapnutí/vypnutí produktu (řízení viditelnosti)
- Řazení a pozicování produktů

### Atributy produktů
- Konfigurovatelné atributy produktů (text, číslo, datum, výběr, checkbox)
- Přeložitelné atributy (podpora více jazyků)
- Více atributů na produkt

### Organizace produktů
- Hierarchické kategorie (taxonomie/taxony)
- Přiřazení produktu do více kategorií
- Obrázky a popisy kategorií

## 2. Správa skladu

### Řízení zásob
- Sledování zásob na produkt/variantu
- Úrovně zásob (na skladě, rezervováno)
- Upozornění na nízké zásoby
- Obsluha vyprodaného zboží
- Prevence přeprodání
- Validace zásob v košíku a při objednávce

## 3. Cenotvorba

### Správa cen
- Základní cena na produkt/variantu
- Původní cena (pro zobrazení slev)
- Podpora více měn
- Směnné kurzy mezi měnami
- Sledování historie cen
- Nejnižší cena před slevou (soulad s EU)

## 4. Akce a slevy

### Systém slev
- Procentuální slevy
- Fixní slevy
- Slevové kupóny s kódy
- Limity použití kupónů (na kupón, na zákazníka)
- Časově omezené akce (datum začátku/konce)
- Priorita a pravidla kombinování akcí

### Pravidla akcí (podmínky)
- Prahová hodnota celkového košíku
- Prahová hodnota množství v košíku
- Konkrétní produkt v košíku
- Produkt z konkrétní kategorie
- Příslušnost k zákaznické skupině
- Sleva na N-tou objednávku

### Akce slev
- Procentuální sleva na objednávku
- Fixní sleva na objednávku
- Procentuální sleva na položku
- Fixní sleva na položku
- Doprava zdarma
- Kup X dostaneš Y zdarma

### Katalogové akce
- Automatické snížení cen
- Akce založené na produktech
- Akce založené na kategoriích
- Naplánované akce

## 5. Objednávky a pokladna

### Proces objednávky
- Vícekrokový checkout proces
- Možnost objednání bez registrace (host checkout)
- Objednání registrovaným uživatelem
- Zadání/výběr adresy
- Výběr způsobu dopravy
- Výběr platební metody
- Kontrola a potvrzení objednávky
- Vyprázdnění košíku po úspěšné objednávce

### Správa objednávek
- Vytváření a sledování objednávek
- Unikátní čísla objednávek
- Stavy objednávek (košík, nová, zpracovává se, dokončená, zrušená)
- Historie a časová osa objednávky
- Zobrazení detailu objednávky
- Filtrování a vyhledávání objednávek podle:
  - Zákazníka
  - Časového rozsahu
  - Stavu
  - Stavu platby
  - Stavu dopravy
- Poznámky a komentáře k objednávkám

### Zpracování objednávek
- Obsluha plateb objednávek
- Sledování zásilek
- Zrušení objednávky
- Dokončení objednávky
- Úprava objednávky (omezené scénáře)

### Položky objednávky
- Položky objednávky s množstvím
- Úpravy objednávky (daně, poplatky za dopravu, slevy)
- Zachování detailů na úrovni položek

## 6. Platby

### Správa plateb
- Více platebních metod
- Konfigurace platebních metod
- Integrace platební brány
- Stavy plateb (nová, zpracovává se, dokončená, neúspěšná, zrušená, refundovaná)
- Zapnutí/vypnutí platební metody

### Funkce plateb
- Bezpečné zpracování plateb
- Potvrzení platby
- Obsluha neúspěšné platby
- Podpora refundací
- Překlady platebních metod (více jazyků)

## 7. Doprava

### Způsoby dopravy
- Více způsobů dopravy
- Konfigurace způsobů dopravy
- Výpočet nákladů na dopravu podle:
  - Celkové částky objednávky
  - Váhy objednávky
  - Cílové zóny
- Zapnutí/vypnutí způsobu dopravy
- Překlady způsobů dopravy

### Správa zásilek
- Sledování zásilek
- Stavy zásilek (připravena, odeslaná, doručená, zrušená)
- Správa dodací adresy
- Doručovací zóny a pravidla

## 8. Daně

### Správa daní
- Daňové kategorie
- Daňové sazby na kategorii
- Daně založené na zónách
- Procentuální sazby DPH
- Ceny s DPH / bez DPH
- Výpočet daní na objednávkách
- Podpora více daňových sazeb

## 9. Správa zákazníků

### Zákaznické účty
- Registrace zákazníků
- Profily zákazníků
- Skupiny zákazníků
- Zobrazení historie objednávek
- Ověření e-mailu
- Reset/změna hesla
- Úprava účtu
- Zapnutí/vypnutí zákazníka

### Správa adres
- Adresář
- Více adres na zákazníka
- Fakturační adresy
- Dodací adresy
- Výběr výchozí adresy
- Vytvoření, úprava, smazání adresy

### Autentizace uživatelů
- Přihlášení/odhlášení zákazníka
- Správa administrátorských uživatelů
- Role a oprávnění uživatelů
- Správa sessions

## 10. Nákupní košík

### Funkce košíku
- Přidat do košíku
- Aktualizovat množství
- Odebrat položky
- Shrnutí košíku s celkovými částkami
- Zobrazení aplikovaných akcí
- Aplikace kódu kupónu
- Perzistence košíku (pro přihlášené uživatele)
- Widget mini-košíku
- Sledování opuštěných košíků

## 11. Funkce e-shopu (frontend)

### Zobrazení produktů
- Úvodní stránka
- Stránky se seznamem produktů
- Detailní stránky produktů
- Vyhledávání produktů
- Filtrování produktů podle atributů
- Řazení produktů (název, cena, datum, pozice)
- Procházení kategorií
- Zobrazení recenzí a hodnocení produktů
- Zobrazení souvisejících produktů

### Nákupní zážitek
- Responzivní design
- Galerie obrázků produktů
- Zobrazení dostupnosti zásob
- Zobrazení ceny s/bez DPH
- Odznaky slev
- Odznaky nových produktů

## 12. Administrační rozhraní

### Administrace
- Dashboard s přehledovými statistikami
- Plné CRUD operace pro:
  - Produkty
  - Kategorie
  - Objednávky
  - Zákazníky
  - Akce
  - Způsoby dopravy
  - Platební metody
  - Daně
- Tabulkové/seznamové zobrazení s filtrováním, řazením, stránkováním
- Hromadné akce
- Nahrávání a správa obrázků
- Řízení přístupu a oprávnění

## 13. Internacionalizace

### Podpora více jazyků
- Více jazyků/locales
- Přeložitelný obsah:
  - Produkty
  - Kategorie
  - Atributy
  - Akce
  - Způsoby dopravy
  - Platební metody
- Přepínání jazyků
- Výchozí jazyk na kanál

### Geografické funkce
- Správa zemí
- Kraje/regiony
- Geografické zóny
- Pravidla založená na zónách (doprava, daně)

## 14. Podpora více kanálů

### Správa kanálů
- Více prodejních kanálů (volitelné)
- Specifické pro kanál:
  - Produkty
  - Ceny
  - Měny
  - Jazyky
  - Daně
  - Způsoby dopravy
  - Platební metody

## 15. Komunikace

### E-mailový systém
- E-maily s potvrzením objednávky
- E-maily s potvrzením platby
- E-maily se sledováním zásilky
- Potvrzení registrace
- E-maily pro reset hesla
- Odběr newsletteru (volitelné)
- Kontaktní formulář

## 16. Bezpečnost

### Bezpečnostní funkce
- Autentizace uživatelů
- Šifrování hesel
- Ochrana proti CSRF
- Ochrana proti XSS
- Řízení přístupu pro adminy
- Bezpečný checkout
- Soulad s PCI (pro platby)

## 17. Reporty a analytika

### Reporty
- Prodejní reporty
- Statistiky objednávek
- Příjmy podle období
- Nejprodávanější produkty
- Statistiky zákazníků
- Efektivita akcí
- Skladové reporty

## 18. API a integrace

### REST API (volitelné)
- API pro produkty
- API pro objednávky
- API pro zákazníky
- API pro sklad
- Autentizace (JWT/OAuth)
- Dokumentace API

## 19. Vývojářské funkce

### Technická infrastruktura
- Stavový automat pro workflow objednávek/plateb
- Systém událostí pro rozšiřitelnost
- Databázové migrace
- Podpora automatizovaného testování
- Logování a sledování chyb
- Optimalizace výkonu
- Strategie cachování

## 20. Další funkce

### Nice-to-Have funkce
- Recenze a hodnocení produktů
- Funkcionalita wishlistu
- Porovnání produktů
- Nedávno zobrazené produkty
- Notifikace o dostupnosti zásob
- Dárkové poukazy/vouchery
- Systém věrnostních bodů
- Obnovení opuštěných košíků
- Balíčky produktů
- Předobjednávky
- Backordery

---

## Prioritizace implementace pro GameCon

Na základě specifických potřeb GameConu jako systému pro správu akcí s funkcionalitou e-shopu jsou doporučeny následující priority:

### Fáze 1: Základní e-shop (nezbytné)
1. Správa produktů (jednoduché produkty s variantami)
2. Správa skladu (sledování zásob, prevence přeprodání)
3. Nákupní košík
4. Základní proces objednávky
5. Správa objednávek
6. Integrace plateb (české platební metody)
7. Zákaznické účty
8. Základní administrační rozhraní

### Fáze 2: Ceny a akce
1. Systém slev (kupóny, procentuální, fixní)
2. Pravidla akcí (skupiny zákazníků, celková částka objednávky)
3. Správa cen
4. Výpočet daní (české DPH)

### Fáze 3: Rozšířené funkce
1. Kategorie produktů
2. Atributy produktů
3. Správa dopravy (pokud fyzické zboží)
4. E-mailové notifikace
5. Historie a sledování objednávek
6. Pokročilé administrační funkce

### Fáze 4: Pokročilé funkce (volitelné)
1. Podpora více jazyků (CS/EN)
2. Reporty a analytika
3. Recenze produktů
4. Pokročilé akce
5. API pro integrace

---

**Poznámka**: Toto je komplexní seznam funkcí. Skutečná implementace by měla být přizpůsobena konkrétním požadavkům GameConu, se zaměřením na funkce, které odpovídají správě akcí a prodeji vstupenek/merchandise.
