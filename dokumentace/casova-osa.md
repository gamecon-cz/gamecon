
## Časová osa

Rámcový přehled činností, které se během roku běžně v souvislosti s webem provádí:

- (někdy září–listopad) Výchozí stav po překlopení ročníku (TODO: odkaz).
- (prosinec) V prosinci posílá marketing obvykle PF – následující je potřeba udělat, než PF vyjde:
  - Nastavení základních datumů do `nastaveni/nastaveni.php` (kdy začíná budoucí GC, kdy se otevře registrace, …).
  - Od grafiky vyžádáme a v případě úspěchu nasadíme aktualizovaný vzhled webu.
- (po jarním srazu – březen)
  - Kontrola nastavení, že si zadané datumy nikdo nerozmyslel.
  - Vložení aktualizované nabídky předmětů pro další rok do databáze.
  - Sekce pomalu začínají vkládat do adminu aktivity.
- (před startem registrací – konec dubna) Provázání spec. aktivit, aktuálně DrD a Legendy.
- (konec dubna) Automatický start přihlašování na GameCon (zpravidla o týden dřív oproti aktivitám, aby se družiny DrD stihly zaregistrovat).
- (začátek května) Automatický start přihlašování na aktivity (tento okamžik bychom měli marketovat). Je vhodné po programu zkontrolovat, že většina aktivit je v stavu „aktivní“, aby se skutečně v okamžiku určeném v nastavení otevřela registrace.
- (květen–červen) Spouštění „vln“ programu – provádí si postupně program.
- (konec června) Poloautomatické odhlašování – má na starosti zázemí, ale většinou to zapomíná a zpětně to nejde¹ opravit, tzn. je nutné je s tím urgovat. Obsahuje několik kroků:
  - Poslední večer (ideálně v 23:xx) kdy ještě platí včasná platba: pomocí panelu Finance v adminu všem účastníkům odebrat roli včasné platby, vyfiltrovat ty s zůstatkem větším jak např. -100Kč a potvrdit jejich posazení na roli včasné platby. Pokud se toto neudělá, po půlnoci všem naskočí nedoplatek (sleva se přestane automaticky dávat) a tím pádem už se nedá zjistit, kdo vlastně zaplatil a kdo ne.
  - O pár dní po té pomocí panelu Finance > Hromadné rušení objednávek v adminu odhlásit lidem s nedoplatky vybrané věci (co se bude odhlašovat a jaká výše nedoplatku se bere v úvahu si opět určuje zázemí).
  - Odhlásit neplatičům aktivity – provádí ručně zázemí.
- (před GC) Další vlna odhlašování (TODO: toto je nějaká nová věc zavedená zázemím, nevíme o tom mnoho)
- (začátek GC) Automatické přepnutí stránek do režimu „GC běží“ – znepřístupní se přihláška, klikat si věci v programu je stále možné. Nové lidi přihlašuje infopult přes admin, který stále plně funguje.
- (v průběhu GC) Vyplňování prezenček, …, TODO
- (konec GC) Automatické přepnutí stránek do režimu „GC skončil“ – nejde měnit přihlášky, aktivity, ani nic dalšího, ale zůstávají viditelné finance.
- (hned po GC) Infopult zpravidla vygeneruje nějaké nedodělky (člověk nemá platit storno, člověk má v systému napsánu účast na aktivitě, ale místo toho tam byl někdo jiný, …). Tyto věci infopult předá a zpracují se, aby se návštěvníkům zarovnaly zůstatky jak mají být.
- (srpen) Zázemí udělá „účetní uzávěrku“. Ideálně dotlačit k tomu, aby to provedli co nejrychleji. Po vzájemném ujištění, že je vše hotovo, je vhodné přejít co nejdřív k překlápění ročníku.
- (někdy září–listopad) překlopení ročníku (TODO: aktuálně máme dokumentaci tohoto procesu na gdrive, budeme nejspíš migrovat do repa)

---

¹ Samozřejmě že jde, stejně jako uvést na GameConu milion RPG naráz, jen je to komplikované.
