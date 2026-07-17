# Nastavení

Stránka **Nastavení** je řídicí pult celého ročníku. Měníš tu termíny (vlny přihlašování, konce prodejů, hromadná odhlašování neplatičů), částky a sazby (kurz eura, bonusy vypravěčů) i chování systému (notifikace, zamykání aktivit). Vidí ji jen organizátoři s právem na panel Nastavení.

**Pozor: všechno, co tady změníš, platí okamžitě pro celý web** — pro přihlášku účastníků, výpočty cen, automaty na pozadí i admin. Není tu žádné tlačítko „Uložit" ani „Zpět". Než něco změníš, měj jasno, co děláš; ideálně si změnu nejdřív vyzkoušej na betě.

## Jak se hodnoty mění a kdy se projeví

- Hodnota se **ukládá automaticky hned při změně** políčka. U políčka se objeví ✔️ („Změny jsou uloženy") nebo ❗ s popisem chyby, pokud systém hodnotu odmítl (třeba špatný formát data).
- U každé položky vidíš ve sloupci **Poslední změna**, kdy byla naposledy změněna a kdo to udělal (po najetí myší). Starší historie se na stránce nezobrazuje — o to opatrněji s přepisováním.
- Sloupec **Vlastní**: řada položek má **výchozí hodnotu**, kterou si systém spočítá sám (typicky termíny odvozené od začátku a konce GC). Odškrtnutím políčka Vlastní svou ruční hodnotu vyřadíš a necháš to na systému; zaškrtnutím ji zase převezmeš. Položky bez výchozí hodnoty přepnout nejdou.
- Po najetí na název položky se zobrazí podrobný popis včetně aktuální výchozí hodnoty. Odkaz **#** u názvu zkopíruje do adresy odkaz přímo na tu položku — hodí se, když chceš kolegovi poslat „tohle nastav".
- Některé položky jsou **jen pro čtení** (šedé) — třeba **Ročník** nebo **Průměrné loňské vstupné**. Ty tady změnit nejde.

## Časy

Nejdůležitější skupina — termíny, které řídí celý ročník.

| Nastavení | Co dělá |
|-----------|---------|
| Začátek / Konec Gameconu | Kdy akce běží. Od těchto dat se odvozuje většina výchozích termínů níže. |
| Začátek / Ukončení registrací účastníků | Od kdy a do kdy se lze registrovat na GC přes web. Konec registrací musí být nejpozději na konci GC — dřívější datum systém odmítne. |
| Začátek první / druhé / třetí vlny aktivit | Kdy se hromadně aktivují aktivity „Připravené k aktivaci" — tedy kdy se otevře přihlašování na další dávku aktivit. **Klíčové termíny ročníku**; posun vlny na špatné datum znamená, že se aktivity neotevřou, kdy mají. |
| První / Druhé / Třetí hromadné odhlašování | Kdy budou hromadně odhlášeni neplatiči. |
| Ukončení prodeje ubytování / jídla / triček / mikin / předmětů | Do kdy (včetně daného dne) lze v přihlášce danou věc objednat a měnit, než se zamkne. |
| Do kolika dní po GC lze přidat účastníka | Jak dlouho po akci mohou vypravěči doplňovat účastníky na neuzavřené aktivity. |
| Ročník | Který ročník GC je aktivní — jen pro čtení, tady ho nezměníš. |

## Finance

| Nastavení | Co dělá |
|-----------|---------|
| Kurz Eura | Kolik Kč je letos jedno €. |
| Bonus za vedení 3–5h aktivity | Základní odměna vypravěče. Bonusy za kratší i delší aktivity se z ní dopočítávají automaticky — spočítané částky vidíš v popisu položky. |
| Text pro rozpoznání odchozí GC platby | Přesné znění poznámky, podle kterého se páruje odchozí platba (vracení zůstatku) s účastníkem. Neměň, pokud přesně nevíš, co posíláte z banky. |
| Jakou slevu mají mít orgové na jídlo | Sleva pro všechny s rolí „Jídlo se slevou". |
| Podezřele vysoká platba účastníka | Částka, od které se na právě spárovanou platbu odešle upozornění CFO. |
| Průměrné loňské vstupné | Jen pro čtení; používá se pro kostku na posuvníku dobrovolného vstupného. |

## Aktivita

Minutové limity kolem začátku a konce aktivit: od kdy před začátkem může vypravěč editovat přihlášené, jak dlouho po konci lze potvrzovat účastníky, kolik minut před začátkem se pozdní přihlášení bere jako „na poslední chvíli" (Moje aktivity pak ukážou varování, ať na účastníka počkají), po kolika minutách běhu se aktivita sama zamkne a kdy přijde vypravěči mail, že aktivitu nezavřel. Patří sem i **Kolik minut je odhlášení aktivity bez pokuty** — omylem přihlášený účastník se může do pár minut odhlásit bez storna i těsně před začátkem.

## Neplatič

Kdo je při hromadném odhlašování považován za neplatiče: jak velký dluh je ještě „příliš velký", jakou částku musí účastník poslat, aby byl v bezpečí, a kolik dní od registrace je nový účastník chráněn. Samotné termíny odhlašování jsou ve skupině Časy.

## Notifikace

**Poslat nám e-mail o uvolněném ubytování** — přepínač, zda má na info@gamecon.cz přijít zpráva, když se odhlásí účastník s objednaným ubytováním.

## Zkopírování databáze z ostré (jen beta / preview / lokál)

Na betě, preview a lokálním prostředí je pod tabulkou nastavení sekce pro přepsání zdejších dat čerstvými daty. Slouží k tomu, aby testovací prostředí vypadalo jako ostrý web — na ostré se tato sekce vůbec nezobrazuje.

- **Zkopírovat databázi z ostré** — přepíše zdejší data aktuálním stavem ostré.
- **Zkopírovat archivní databázi** — přepíše je daty vybraného staršího ročníku (jen na betě).
- **Zkopírovat ze zálohy ostré** — přepíše je vybraným záložním souborem; u každého vidíš datum a stáří zálohy. **Na preview je k dispozici jen tato varianta.**

Každé tlačítko se ještě jednou zeptá, jestli opravdu chceš data přemazat. Kopírování běží na pozadí (může trvat řadu minut), na stránce sleduješ průběh a odhad zbývajícího času, o výsledku přijde e-mail. Naráz může běžet jen jedna kopie.

**Kopírování je nevratné** — všechno, co bylo v cílovém prostředí naklikáno (testovací účastníci, rozpracovaná nastavení), zmizí a nahradí se zdrojovými daty. Ostré databáze se nic z toho nedotkne, čte se z ní jen zdroj.

## Na co si dát pozor

- **Změny platí okamžitě a bez potvrzení.** Překlep v datu vlny nebo v částce se hned promítne účastníkům. Po každé změně zkontroluj uloženou hodnotu a ✔️.
- **Vrácení změny je jen ruční.** Vidíš pouze poslední změnu a jejího autora; starou hodnotu musíš znát a nastavit zpět sám.
- **Termíny vln a hromadných odhlašování** jsou nejcitlivější — řídí automaty, které se spouštějí samy. Neposouvej je na poslední chvíli bez domluvy s ostatními orgy.
- **Odškrtnutí „Vlastní"** znamená, že hodnota začne žít vlastním životem podle výpočtu systému (a změní se třeba při posunu termínu GC). Zaškrtnutá vlastní hodnota naopak zůstává, i když se ostatní termíny posunou — nezapomeň ji pak posunout taky.
- **Kopírování databáze nevratně maže data cílového prostředí.** Nikdy ho nepouštěj, pokud si na betě/preview někdo něco rozpracoval a neví o tom.
