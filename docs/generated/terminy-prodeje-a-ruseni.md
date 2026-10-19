# Termíny prodeje: co po nich ještě jde změnit

Proč jídlo po termínu nejde ani zrušit, zatímco merch ano. Z kódu se to odvodit nedá —
rozhoduje to, jestli je zboží objednané na hlavu u dodavatele, nebo leží na skladě.

## Pravidlo

| Sekce | Po termínu koupit | Po termínu zrušit | Proč |
|---|---|---|---|
| Jídlo | ne (účastník) | **ne** | Do cateringu jde konkrétní počet porcí; zrušená porce se stejně uvaří a zaplatí |
| Ubytování | ne (účastník) | ano | Vlastní cesta (`AccommodationWriter`), na místě podle volné kapacity |
| Merch (trička, mikiny, předměty) | ne | **ano** | Bere se zásoba navíc a doprodává se na infopultu |

Pult objednává dál po termínu u všech sekcí — proto admin obrazovky existují.

## Jídlo: počet porcí, ne konkrétní jídla

Catering dostane **počet porcí**, ne seznam jmen. Org to takhle vysvětluje účastníkům
(dotaz na rezervaci bezlepkového jídla):

> „Možnost rezervace konkrétního jídla bohužel také nedokážeme zajistit, protože
> objednáváme konkrétní počet porcí, nikoliv jednotlivých jídel."

Důsledek pro zrušení: porce je po termínu nevratná. Když účastník onemocní, orgy řeší
**převodem na jinou osobu na infopultu**, ne zrušením.

Hromadná rozesílka účastníkům (30. 6. 2026) termín formuluje jako konec *předprodeje*, ne
jako úplný konec:

> „19. července se ve 24:00 spouští druhá vlna odhlašování a zároveň končí možnost
> zajištění ubytování a jídla předem. Ubytování už pak bude možné jen na místě podle
> dostupných kapacit. Jídlo bude možné zajistit si nejpozději dopoledne den předem."

Tedy: online si účastník po termínu nesáhne, pult zařídí ještě ráno předem.

## Merch: dřív zamčený obousměrně, dnes podle zásob

Historicky se merch zastavil úplně — objednat ani zrušit. To je význam, který nese jméno
nastavení `*_LZE_OBJEDNAT_A_MENIT_DO_DNE` („objednat **a měnit**"). Od roku 2026 se u
účastnických triček kupuje zásoba navíc, takže se dají měnit až do GC:

> „Vždycky se trička úplně zastavily v tom červnu (nemohl jsi objednat, nemohl jsi zrušit).
> Letos se vzaly účastnické trička navíc, takže jsou normálně editovatelné až do GC a řídí
> se stavem zásob."

Orgovská a vypravěčská trička zamčená zůstávají — pro ně se zásoba navíc nebere. Trička se
vyrábějí na počet objednávek (`Množství: dle objednávek`), kdežto kostky a pytlíky se
kupují v pevném počtu kusů, takže jsou vratné z principu.

## Jak se to nastavuje

Není to v kódu napevno a ani nemá být. Orgy mají tři páky:

- **datum na kategorii** — `TRICKA_…`, `MIKINY_…`, `PREDMETY_BEZ_TRICEK_…`, `JIDLO_…`,
  `UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE`,
- **`nabizet_do` na konkrétním produktu**,
- **zásobu** u produktu.

Hodnoty v `SystemoveNastaveni::vychoziHodnoty` jsou jen výchozí; kategorie se dají
nastavit zvlášť (`vlastni = 1`). **Které kategorie sdílejí datum, se mezi ročníky mění** —
2025 mělo jídlo společně s ubytováním, 2026 společně s merchem. Nespoléhej na to, že
skupina platí i příští rok.

## Kde to drží kód

`CartService::prodejSekceUkoncen()` řeší nákup pro všechny sekce, `overRuseni()` zamyká
rušení **jen u jídla**. Zámek v `MealProductOutputDto::locked` je pouze nápověda pro
matici — vynucuje ho `CartService`, kudy zápis účastníka doopravdy vede.
