
  - zakládání a přihlašování týmu:
    - kapitán otevře nastavení týmů a tam klikne založit tým
    - vybere pro svůj tým termíny
      - může být přeskočeno pokud v žádném kole není víc termínů na výběr
      - současně není potřeba pro Lkd turnaj, ten je lineární (je to v podstatě jako dva samostatné turnaje)
    - sám se přihlásí na aktivitu
      - je potřeba pouze v moment kdy by nešel přihlásit protože má v čas turnaje jinou aktivitu
    - tým je připraven
      - pokud od založení není tým připraven do 30min. pak je smazán
        - důrazné varování v UI
    - všichni členové týmu vidí kód který pošlou kamarádům aby se mohli přihlásit
    - pro přihlášení do tohoto týmu vloží hrač kód co dostal od člověka který v týmu už je
    - kapitán může upravit limit týmu
      - _??? je tahle featura opravdu potřeba když stejně musí být tým zamčený aby byl hotový taky může být naintuitivní pro používání_
      - limit týmu je kapitánem nastavená hodnota kolik může být celkem v týmu lidí
      - lze nastavit v rozmezí týmová kapacita min-max
    - uzamčení týmu
      - tým lze uzamknout pokud má alespoň min kapacitu
      - musí být manuálně uzamčen do 72h
        - výrazný vizuální indikátor zbývajícího času
        - po 72h je tým zveřejněn/smazán (podle nastavení aktivity)


  - posílání mailu při:
    - v moment odemčení týmu
    - zbývá 24h ze 72h do doby kdy musí kapitán zamknout tým

  - kapitán může
    - předat kapitána
    - vyhodit (a odhlásit) člověka z týmu
    - odhlásit sám sebe (kapitán se předá někomu jinému nebo pokud je poslední tak se tým rozpustí)
    - změnit limit týmu

  - zamčený tým
    - nelze nijak dál editovat (vypne odhlašování/přihlašování předávání kapitána etc.)
    - odemčení
      - pouze šéf infa nebo automaticky odhlášením neplatiče
      - při odhlášení neplatiče odemkne a vyhodí neplatiče
      - při odemčení běží limit 72h znova
    - každý zamčený tým je automaticky nastavený jako neveřejný

  - týmová aktivita:
    - týmová kapacita - koluk může být na aktivitě přihlášeno týmů
    - min a max kapacita týmu kolik musí mít každý tým lidí

  - vícekolové aktivita/turnaj:
    - aktivita může být součástí turnaje, pak musí mít určené ve kterém kole se nachází
    - aby mohl být hráč přihlašený na vícekolovou aktivitu tak musí mít v každém kole přihlášenou právě jednu aktivitu
    - přihlašování na všechny kola se provádí jako jedna akce
      - v případě týmovek je výběr termínů před přihlášením kapitána do týmu
      - pro netýmové se nepočítá s výběrem z více možností
    - různé kola aktivity můžou mít různou kapacitu

  - veřejný tým
    - tým který je zobrazený v seznamu týmu a dá se do něj přihlásit bez kódu

  - anonymizace pro přihlášené lidi - zobrazit pouze přezdívku nebo jak to je

  - sledování týmové aktivity
    - ano, pošle email když se uvolní místo pro přidání týmu
    - sledování týmové aktivity s více koly
      - hráč není odhlášen od sledování vícekolové aktivity pokud ve všech kolech může sledovat alespoň jednu aktivitu. (pokud by se udělalo místo tak by se mohl přihlásit bez odhlášení jiné aktivity)

  - program v adminu může dělat změny i mimo pravidla
    - jako asi editovat zamknutý tým nebo ho alespoň odemknout
    - přidávat lidi nad max týmu ?

  - prezenčky
    - todo

  - otázky:
    - je potřeba výběr kola pro netýmové aktivity ?
      - výběr kola se provádí v ui pro tým, pokud by netýmová aktivita potřebovala taky výběr kola tak by bylo potřeba dovymyslet
      - jinak řečeno může nějaká netýmová vícekolová aktivita mít v jednom kole více aktivit ?
    - je potřeba upravování limitu aktivity ?
      - stejně aktivitu zamknu když mám lidi co chci takže limity jsou jen kroky navíc
      - limit dává asi možná trochu smysl pro veřejné týmy co chcou hrát v menším počtu
    - co vše by mělo jít dělat přes admin ?
      - alespoň vše co by mohl normálně dělat kapitán
      - šef infa může odemknout tým

  - todo
    - importy
    - reporty
