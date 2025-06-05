import produce from "immer";
import { GAMECON_KONSTANTY } from "../env";
import { useProgramStore } from "../store/program";
import { Aktivita } from "../store/program/slices/programDataSlice";

type TestovacíStav = {
  název: string,
  setter: () => void,
};

const resetStav = {
  název: "Reset",
  setter() {
    useProgramStore.setState(s => {
      s.data = {
        podleRočníku:{
          [GAMECON_KONSTANTY.ROCNIK]: {
            aktivityPodleId: {},
          }
        },
        štítky: [],
      };

      s.přihlášenýUživatel.data = {
        gcStav: "nepřihlášen",
      };

      s.urlStav.aktivitaNáhledId = undefined;
      s.urlStav.výběr = {
        typ: "den",
        datum: new Date(GAMECON_KONSTANTY.PROGRAM_OD),
      };
      s.urlStav.ročník = GAMECON_KONSTANTY.ROCNIK;
    });
  },
};

const časV = (hodina: number) => {
  const dateObj = new Date(GAMECON_KONSTANTY.PROGRAM_OD);

  dateObj.setHours(hodina);

  return +dateObj;
};

type AktivitaCreateParams = { id: number, hodina?: number, trvání?: number };

const createAktivita = (a: AktivitaCreateParams): Aktivita => {
  const {
    id,
    hodina,
    trvání
  } = a;

  const cas = {
    od: časV(hodina ?? 8),
    "do": časV((hodina ?? 8) + (trvání ?? 1))
  };

  return {
    id,
    nazev: "Dominion",
    kratkyPopis: "Stojíte na počátku budování své vlastní říše a nemáte nic než trochu peněz a malé pozemky.",
    obrazek: "",
    // stitky: [
    //   "Turnaj"
    // ],
    // TODO:
    stitkyId: [],
    cenaZaklad: 90,
    casText: "10:00&ndash;15:00",
    cas,
    linie: "turnaje v deskovkách",
    probehnuta: true,
    popis: "",
    vypraveci: [],
    obsazenost: {
      m: 0,
      f: 0,
      km: 0,
      kf: 0,
      ku: 1
    },
  };
};

const nastavAktivity = (aktivity: Aktivita[]) => {
  useProgramStore.setState(s => {
    aktivity.forEach(x => {
      const ročník =  s.data.podleRočníku[GAMECON_KONSTANTY.ROCNIK] ?? {aktivityPodleId: {}};
      s.data.podleRočníku[GAMECON_KONSTANTY.ROCNIK] = ročník;
      ročník.aktivityPodleId[x.id] = x;
    });
  });
};



export const TESTOVACÍ_STAVY: TestovacíStav[] = [
  resetStav,
  {
    název: "Různé druhy aktivit",
    setter() {
      resetStav.setter();

      nastavAktivity([
        produce(createAktivita({ id: 1, hodina: 8, trvání:3 }), x => {
          x.prihlasovatelna = true;
          // x.stitky.push("Kartičková");
          // x.stitky.push("Historie");
        }),
        produce(createAktivita({ id: 2, hodina: 9 }), x => {
          x.vdalsiVlne = true;
          // x.stitky.push("Rozhodovací");
        }),
        produce(createAktivita({ id: 3, hodina: 10 }), x => {
          x.vBudoucnu = true;
          // x.stitky.push("Věk: 6+");
        }),
        produce(createAktivita({ id: 4, hodina: 11 }), x => {
          x.prihlasovatelna = true;
          x.stavPrihlaseni = "prihlasen";
          // x.stitky.push("Kartičková");
        }),
        produce(createAktivita({ id: 5, hodina: 12, trvání: 3 }), x => {
          x.prihlasovatelna = true;
          x.stavPrihlaseni = "prihlasen";
          // x.stitky.push("Kartičková");
        }),
        produce(createAktivita({ id: 6, hodina: 13 }), x => {
          // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
          x.obsazenost.m = 1;
        }),
        produce(createAktivita({ id: 7, hodina: 14 }), x => {
          x.vedu = true;
        }),
        produce(createAktivita({ id: 8, hodina: 15 }), x => {
          x.prihlasovatelna = true;
          x.stavPrihlaseni = "prihlasenAleNedorazil";
        }),
        produce(createAktivita({ id: 9, hodina: 9+24*2 }), x => {
          x.prihlasovatelna = true;
          x.stavPrihlaseni = "prihlasen";
        }),
        produce(createAktivita({ id: 10, hodina: 8+24*3 }), x => {
          x.prihlasovatelna = true;
          x.stavPrihlaseni = "prihlasen";
          x.linie = "epické deskovky";
        }),
      ]);

      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.prihlasen = true;
        s.přihlášenýUživatel.data.gcStav = "přítomen";
      });
    },
  },

];


export const TESTOVACÍ_STAVY_UŽIVATEL: TestovacíStav[] = [
  {
    název: "přihlašen",
    setter() {
      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.prihlasen = !s.přihlášenýUživatel.data.prihlasen;
      });
    }
  },
  {
    název: "organizátor",
    setter() {
      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.organizator = !s.přihlášenýUživatel.data.organizator;
      });
    }
  },
  {
    název: "brigadnik",
    setter() {
      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.brigadnik = !s.přihlášenýUživatel.data.brigadnik;
      });
    }
  },
  {
    název: "koncovka a",
    setter() {
      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.koncovkaDlePohlavi = "a";
      });
    }
  },
  {
    název: "koncovka bez",
    setter() {
      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.koncovkaDlePohlavi = "";
      });
    }
  },
  {
    název: "stav nepřihlášen",
    setter() {
      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.gcStav = "nepřihlášen";
      });
    }
  },
  {
    název: "stav přihlášen",
    setter() {
      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.gcStav = "přihlášen";
      });
    }
  },
  {
    název: "stav přítomen",
    setter() {
      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.gcStav = "přítomen";
      });
    }
  },
  {
    název: "stav odjel",
    setter() {
      useProgramStore.setState(s => {
        s.přihlášenýUživatel.data.gcStav = "odjel";
      });
    }
  },
];
