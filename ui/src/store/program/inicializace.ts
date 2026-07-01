import { useProgramStore } from ".";
import { GAMECON_KONSTANTY } from "../../env";
import { registrujDotahováníNastaveníTýmu } from "../../pages/program/components/vstupy/NastaveniTymuModal";
import { distinct } from "../../utils";
import { nadpisProgramu, ProgramTabulkaVýběr, urlStavProgramTabulkaMožnostíDnyMůj } from "./logic/url";
import { načtiRok } from "./slices/programDataSlice";
import { nastavStateZUrl, nastavUrlZState } from "./slices/urlSlice";
import { nastavFiltryOtevřené } from "./slices/všeobecnéSlice";

const indexŘazeníLinie = (klíč: string) => {
  const index = GAMECON_KONSTANTY.PROGRAM_ŘAZENÍ_LINIE.findIndex(
    (x) => x === klíč
  );

  return index !== -1 ? index : 1000;
};

export const inicializujProgramStore = () => {
  // Načtu do stavu url
  nastavStateZUrl();
  // Normalizuju url podle stavu
  nastavUrlZState(true);

  useProgramStore.subscribe(s => s.urlStav, () => {
    nastavUrlZState();
  });

  // Titulek v záložce nastaví PHP serverově při načtení, ale přepínač dne mění
  // URL jen přes pushState (viz nastavUrlZState) – bez tohohle by se název v
  // záložce změnil až po F5. Prefix prostředí (β / 🧐 / …), který PHP přidává
  // před název, chceme zachovat. Server pro prázdný slug renderuje první den
  // programu, klient ale může mít jiný výchozí den (dle TED / dne v týdnu),
  // takže prefix odvodíme porovnáním titulku proti VŠEM možným názvům stránky,
  // ne jen proti aktuálnímu klientskému výběru – jinak by endsWith neseděl a
  // prefix by se ztratil.
  const prefixTitulku = (() => {
    const odpovídajícíNadpis = urlStavProgramTabulkaMožnostíDnyMůj({ přihlášen: true, jeAdmin: true })
      .map(nadpisProgramu)
      .filter((nadpis) => document.title.endsWith(nadpis))
      .sort((a, b) => b.length - a.length)[0];
    return odpovídajícíNadpis
      ? document.title.slice(0, document.title.length - odpovídajícíNadpis.length)
      : "";
  })();
  const aktualizujTitulekStranky = (výběr: ProgramTabulkaVýběr) => {
    document.title = prefixTitulku + nadpisProgramu(výběr);
  };
  // Srovnat titulek hned – klientský výchozí den se u prázdného slugu může lišit
  // od serverového prvního dne, takže nestačí čekat na první přepnutí.
  aktualizujTitulekStranky(useProgramStore.getState().urlStav.výběr);
  useProgramStore.subscribe(s => s.urlStav.výběr, aktualizujTitulekStranky);

  addEventListener("popstate", () => {
    nastavStateZUrl();
  });

  useProgramStore.subscribe(s => !!s.přihlášenýUživatel?.ucastnik, (přihlášen) => {
    useProgramStore.setState(s => {
      s.urlStavMožnosti.dny = urlStavProgramTabulkaMožnostíDnyMůj({ přihlášen });
    });
  });

  useProgramStore.subscribe(s => s.data, (data) => {
    useProgramStore.setState(s => {
      s.urlStavMožnosti.linie = distinct(Object.values(s.data.podleRočníku).flatMap(x=>Object.values(x.aktivityPodleId)).map(x => x.linie))
        .sort((a, b) => indexŘazeníLinie(a) - indexŘazeníLinie(b));
    });
  });

  const přihlášenýUživatelPřednačteno = window?.gameconPřednačtení?.přihlášenýUživatel;
  if (přihlášenýUživatelPřednačteno) {
    useProgramStore.setState(s => {
      s.přihlášenýUživatel.ucastnik = přihlášenýUživatelPřednačteno.ucastnik;
      s.přihlášenýUživatel.operator = přihlášenýUživatelPřednačteno.operator;
    });
  }

  // todo: hodně zakomentovaného kódu asi ke smazání

  //Tohle je cachování které bude vypnuté než se navrhne strategie jak to cachovat
  // const dataProgramString = localStorage.getItem(LOCAL_STORAGE_KLÍČE.DATA_PROGRAM);
  // if (dataProgramString) {
  //   try {
  //     useProgramStore.setState(s => {
  //       s.data = JSON.parse(dataProgramString);
  //     }, undefined, "načtení uložených dat");
  //   } catch (e) {
  //     console.warn("nepodařilo se načíst data z local storage");
  //   }
  // }

  // useProgramStore.subscribe(s => s.data, (data) => {
  //   localStorage.setItem(LOCAL_STORAGE_KLÍČE.DATA_PROGRAM, JSON.stringify(data));
  // });

  // tohle je prozatimní cachování štítků
  // const dataProgramString = localStorage.getItem(LOCAL_STORAGE_KLÍČE.DATA_PROGRAM);
  // if (dataProgramString) {
  //   try {
  //     useProgramStore.setState(s => {
  //       // s.data = JSON.parse(dataProgramString);
  //       s.data.štítky = (JSON.parse(dataProgramString) as typeof s.data).štítky;
  //     }, undefined, "načtení uložených dat POUZE ŠTÍTKY");
  //   } catch (e) {
  //     console.warn("nepodařilo se načíst ŠTÍTKY z local storage");
  //   }
  // }

  // useProgramStore.subscribe(s => s.data, (data): void => {
  //   localStorage.setItem(LOCAL_STORAGE_KLÍČE.DATA_PROGRAM,
  //     JSON.stringify(({ aktivityPodleId: {}, štítky: data.štítky } as typeof data)));
  // });

  const urlStav = useProgramStore.getState().urlStav;
  void načtiRok(urlStav.ročník);

  // ať máme vždy přednačtený aktuální ročník
  if (urlStav.ročník !== GAMECON_KONSTANTY.ROCNIK) {
    setTimeout(() => {
      void načtiRok(GAMECON_KONSTANTY.ROCNIK);
    }, 2000);
  }

  useProgramStore.subscribe(s => s.urlStav.ročník, (rok) => {
    void načtiRok(rok);
  });

  if (
    urlStav.ročník !== GAMECON_KONSTANTY.ROCNIK
    || urlStav.filtrLinie?.length
    || urlStav.filtrTagy?.length
    || urlStav.filtrPřihlašovatelné
  ) {
    nastavFiltryOtevřené(true);
  }

  registrujDotahováníNastaveníTýmu();
};
