# Práva a role

## Jak to funguje: uživatel → role → práva

Přístupy a výhody se v GameConu nikdy nepřidělují člověku napřímo. Funguje to ve třech krocích:

1. **Právo** je jedna konkrétní schopnost nebo výhoda — třeba „vidí kartičku Finance v adminu“ nebo „má zdarma páteční noc ubytování“.
2. **Role** je pojmenovaný balíček práv — třeba Vypravěč, Organizátor (zdarma), Infopult.
3. **Uživatel** dostane roli (v aplikaci se říká „posadit na roli“) a tím získá všechna její práva. Odebráním role („sesazením“) o ně zase přijde.

Jeden uživatel může mít rolí víc a jeho práva se sčítají.

> **Pozor: každá změna platí okamžitě.** Jakmile někoho posadíš na roli, má její práva hned při dalším načtení stránky — žádné potvrzování, žádné čekání. Role jako Organizátor nebo práva na kartičky Infopult, Finance či Reporty dávají **přístup k osobním údajům a financím účastníků**. Než roli přidělíš, ujisti se, že ji dotyčný opravdu má mít — a že sedíš u správného uživatele.

## Obrazovka správy práv

Obrazovku **Práva** najdeš v adminu (vidí ji jen ten, kdo má právo na administraci práv). Pracuje se na ní vždy nad **aktuálně otevřeným (pracovním) uživatelem** — tím, kterého sis předtím vyhledal(a) v adminu.

Na hlavním výpisu vidíš:

- **Role s právy** — rozdělené na **Trvalé role** a **Ročníkové role** (pro aktuální ročník). U každé role je zelená tečka, pokud na ní pracovní uživatel právě sedí.
- Odkazy **posaď** / **sesaď** — přidají nebo odeberou roli pracovnímu uživateli. U „sesaď“ se po najetí myší zobrazí, kdo a kdy uživatele na roli posadil.
- Odkaz **detail** — otevře stránku jedné role.
- **Role bez práv** — role účasti (přihlášen / přítomen / odjel na letošní GC). Ty slouží jen k evidenci a nenesou žádná práva.

Na **detailu role** pak vidíš:

- seznam práv, která role dává, s popisem každého práva — a možnost **přidat roli právo** nebo **vzít roli právo**,
- seznam všech uživatelů, kteří na roli sedí, s možností je **sesadit** nebo si je rovnou otevřít,
- tlačítko pro posazení/sesazení aktuálního pracovního uživatele.

Všechny změny (posazení, sesazení, přidání i odebrání práva) se **logují** — vždy je dohledatelné, kdo změnu udělal.

### Kdo smí co měnit

- **Posazovat a sesazovat** na běžné role smí každý, kdo má přístup na obrazovku Práva.
- **Omezené role** (typicky role s velkým dopadem: Organizátor, CFO, Člen rady, Brigádník, Zázemí, Přepínání na uživatele…) smí přidělovat a odebírat **jen člen rady**. Ostatní tyto role na obrazovce ani nevidí.
- **Měnit práva role** (přidat/vzít roli právo) smí jen ten, kdo má navíc speciální právo **změna práv** — a i tak jen u rolí, které smí přidělovat. Tohle je nejsilnější operace na obrazovce: změna práv role se okamžitě dotkne **všech** lidí, kteří na roli sedí.

## Druhy práv

Práva se dají rozdělit do tří skupin:

| Skupina | Co dává |
|---|---|
| **Přístup do adminu** | Každé právo zpřístupní jednu kartičku (sekci) administrace: Infopult, Ubytování, Akce, Prezence, Reporty, Web, Práva, Statistiky, Finance, Moje aktivity, Nastavení, Peníze, Loga na webu, Dev. Kdo právo nemá, kartičku vůbec nevidí. |
| **Výhody a slevy** | Hmotné benefity: jídlo se slevou nebo zdarma; jednotlivé noci ubytování zdarma (středa až neděle) nebo ubytování zdarma po celou dobu; trička zdarma (jakékoli, dvě jakákoli, tričko zdarma při dosažení bonusu) a možnost objednávat modrá či červená trička; placka a kostka zdarma; slevy na aktivity (částečná sleva, aktivity úplně zdarma, jedna nejdražší aktivita zdarma); plný servis (uživatele kompletně platí a zajišťuje GC). |
| **Speciální schopnosti a chování** | Pořádání aktivit (dotyčný je v nabídce vypravěčů a má v adminu „Moje aktivity“); registrace více aktivit ve stejný čas; přihlašování a odhlašování lidí z aktivit, které už proběhly, nebo které ještě nejsou aktivované; **přepnutí na uživatele** (viz níže); nebezpečné tlačítko hromadné aktivace aktivit; rušení nákupů uživatelů; nerušit uživateli objednávky při pozdní platbě; možnost objednat jen jednu noc ubytování; provádění korektur textů aktivit; „bez bonusu za vedení aktivit“ (vypravěčská sleva se nepočítá); označování jako organizátor ve výpisech; zobrazování role ve statistikách a reportech; **unikátní role** a **změna práv** (viz jinde v této kapitole). |

Dvě práva si zaslouží zvláštní pozornost:

- **Přepnutí na uživatele** — držitel se v adminu může přihlásit jako **libovolný** uživatel a vidět i měnit vše, co on. Je to nejsilnější právo v systému. Visí na ročníkové roli **Přepínání na uživatele**, kterou každý rok znovu přiděluje rada — s novým ročníkem loňská přiřazení přestávají platit a nikdo přepínat nemůže, dokud rada roli znovu neudělí. (Infopulťák má nezávisle na tom vlastní omezené přepínání jen na vypravěče a partnery.)
- **Unikátní role** — uživatel může sedět **jen na jedné** roli, která má toto právo. Používá se u rolí, které se navzájem vylučují (typy orgovství apod.). Pokus posadit člověka na druhou takovou roli skončí chybou „Uživatel už má jinou unikátní roli.“

## Letošní vs. trvalé role

- **Trvalé role** platí napříč ročníky — jednou posazený Organizátor jím zůstává, dokud ho někdo nesesadí.
- **Ročníkové role** (Vypravěč, Zázemí, Infopult, Partner, Brigádník, noci zdarma, Přepínání na uživatele…) platí jen pro jeden ročník. Při překlopení na nový ročník se **automaticky vytvoří znovu**: loňská verze se přejmenuje s prefixem ročníku (např. „GC2025 Vypravěč“) a vznikne čerstvá letošní role **se stejnými právy, ale bez lidí**. Kdo má roli mít i letos, musí na ni být posazen znovu.
- **Role účasti** (přihlášen / přítomen / odjel) vznikají pro každý ročník také a nenesou žádná práva — slouží k evidenci, kdo na GC byl.

Praktický důsledek: na začátku každé sezóny je potřeba **znovu posadit vypravěče, zázemí, infopult a další ročníkové role**. Není to chyba, je to záměr — ročníkové výhody a přístupy se nemají „vozit“ z minulého roku automaticky.

## Typické postupy

**Nový organizátor:**

1. Vyhledej uživatele v adminu, ať je otevřený jako pracovní uživatel.
2. Otevři obrazovku Práva a u role **Organizátor (zdarma)** klikni **posaď**. Roli smí přidělit jen člen rady (je omezená).
3. Zkontroluj zelenou tečku u role — od té chvíle má dotyčný všechna práva organizátora, včetně přístupů do adminu.

**Nový vypravěč (pro letošní ročník):**

1. Vyhledej uživatele a otevři obrazovku Práva.
2. V sekci **Ročníkové role** posaď uživatele na roli **Vypravěč**.
3. Tím se dotyčný objeví v nabídce vypravěčů u aktivit a získá výhody navázané na roli (podle toho, jaká práva role letos má). Příští rok bude potřeba ho posadit znovu.

## Na co si dát pozor

- **Změny platí okamžitě.** Neexistuje „uložit“ ani „vrátit zpět“ — jediná cesta zpátky je roli zase odebrat, ale mezitím měl dotyčný plný přístup.
- **Špatně přidělená role = přístup k osobním údajům a penězům.** Kartičky Infopult, Finance, Reporty či Statistiky odhalují osobní a finanční data účastníků. Roli s takovými právy dávej jen lidem, kteří ji opravdu potřebují.
- **Změna práv role zasáhne všechny na roli.** Než roli přidáš nebo vezmeš právo, podívej se na detail role, kdo všechno na ní sedí.
- **Role Prezenční admin** je určená jen pro změny účastníků v uzavřených aktivitách a je označena jako nebezpečná — bez jasného důvodu ji nepoužívej.
- **Hromadná aktivace aktivit** je nebezpečné tlačítko — právo na ni dávej jen lidem, kteří vědí, co dělají.
- **Role a výhody ovlivňují peníze.** Posazení či sesazení může okamžitě změnit uživateli cenu aktivit, ubytování nebo jídla — přepočet proběhne hned.
- Ročníkové role s ročníkem v názvu (např. „GC2025 Vypravěč“) jsou už jen historie — nová přiřazení dělej vždy na letošní verzi role.
