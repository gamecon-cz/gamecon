TODO(tym): dodělat dokument jak funguji týmovky a navázané aktivity
  - Zamykání týmů
    - zakázání odhlašování / přidávání změn atd.
    - otázka jak se chová při odhlašování neplatičů
      - Odemknout a vyhodit neplatiče
    - v adminu možnost odemknout tým
    - každý zamčený tým je automaticky neveřejný
    - odemknout může jen org nebo automaticky odhlašováním neplatičů
      - při odemčení poslat mail
    - při dobýhání limitu na tamčení (asi den před) poslat upozornění mailem kapitánovi
    - při odemčení resetovat limit
  - anonymizace pro přihlášené lidi (důležitá hlavně pro veřejné týmy) - zobrazit pouze přezdívku nebo jak to je

  - při klikfestu:
    - kapitán otevře nastavení týmů a tam klikne založit tým
    - (současně není potřeba pro Lkd turnaj lineární) vybere pro svůj tým termíny (může být přeskočeno pokud není nikde víc termínů na výběr)
    - sám se přihlásí na aktivitu
    - tým je připraven
    - (přidat varování!) pokud není tým alespoň v tomto kroku do 30min. od založení pak je smazán.
    - členové týmu vidí kód který pošlou kamarádům aby se mohli přihlásit.
    - když se někdo chce přihlásit do týmu tak vloží kód co dostal a automaticky ho to přihlásí na všechny aktivity a do týmu
    - kapitán může kdykoliv snížit limit týmu až do min kapacity (popř zvýšit limit až zpět do max kapacity)
    - pokud není tým zamčený do limitu 72h po založení podle nastavení konkrétní aktivity buď:
      - je tým veřejný a můžou se přihlašovat další lidé (stejně tak když se zvedne limit nebo někdo odejde)
      - je tým rozpustěn
  - sledování chceme na volné místo na tým ale ne na zveřejnění týmu.
  - program v adminu může dělat změny i mimo pravidla ... jako asi editovat zamknutý tým nebo ho alespoň odemknout

  - Každá aktivita má kapacitu na určitý počet týmů a minmax kapacitu každého týmu
  - Tým si může snížit svou max kapacitu (limit) až na min kapacitu aktivity
  - Tým který nenaplní svůj limit (nebo max kapacitu aktivity) bude zveřejněn (otevřen pro přihlašování komukoliv)

  - více kol můžou mít pouze týmové aktivity ? (asi ano)
  - ?? ideální způsob zakládání vícekolové aktivity je přes instance ?
  - návod na zprávu týmových aktivit do Nová aktivita/upravy
    - tady vysvětlit instance
    - které aktivity mají být připravené
  - různé termíny můžou mít různou kapacitu (TODO)

  - je potřeba nějaká featura na zamknutí týmu ? Neboli zakázaní všech editací přihlašování a odhlašovaní ? Pokud ano, jaké má parametry ? je to povinné ? děje se automaticky ?

