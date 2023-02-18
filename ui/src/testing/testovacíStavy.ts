import produce from "immer";
import { APIAktivita, APIAktivitaPřihlášen } from "../api/program";
import { GAMECON_KONSTANTY } from "../env";
import { useProgramStore } from "../store/program";

type TestovacíStav = {
  název: string,
  setter: () => void,
};

const resetStav = {
  název: "Reset",
  setter() {
    useProgramStore.setState(s => {
      s.data = {
        aktivityPodleRoku: {},
        aktivityPřihlášenPodleRoku: {},
        aktivityPodleId: {},
        aktivityPřihlášenPodleId: {},
      };

      s.přihlášenýUživatel.data = {
        gcStav: "nepřihlášen",
      };

      s.urlState.aktivitaNáhledId = undefined;
      s.urlState.výběr = {
        typ: "den",
        datum: new Date(GAMECON_KONSTANTY.PROGRAM_OD),
      };
      s.urlState.rok = GAMECON_KONSTANTY.ROCNIK;
    });
  },
};

type AktivitaSPřihlášen = { aktivita: APIAktivita, přihlášen: APIAktivitaPřihlášen };

const časV = (hodina: number) => {
  const dateObj = new Date(GAMECON_KONSTANTY.PROGRAM_OD);

  dateObj.setHours(hodina);

  return +dateObj;
};

type AktivitaCreateParams = { id: number, hodina?: number, trvání?: number };

const createAktivita = (a: AktivitaCreateParams): AktivitaSPřihlášen => {
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
    aktivita: {
      id,
      nazev: "Dominion",
      kratkyPopis: "Stojíte na počátku budování své vlastní říše a nemáte nic než trochu peněz a malé pozemky.",
      obrazek: "",
      stitky: [
        "Turnaj",
        "Kartičková",
        "Historie",
        "Rozhodovací",
        "Věk: 6+"
      ],
      cenaZaklad: 90,
      casText: "10:00&ndash;15:00",
      cas,
      linie: "turnaje v deskovkách",
      probehnuta: true,
      popis: "",
      vypraveci: [],
    },
    přihlášen: {
      id,
      obsazenost: {
        m: 0,
        f: 0,
        km: 0,
        kf: 0,
        ku: 1
      },
    },
  };
};

const nastavAktivity = (aktivity: AktivitaSPřihlášen[]) => {
  useProgramStore.setState(s => {

    aktivity.forEach(x => {
      s.data.aktivityPodleId[x.aktivita.id] = x.aktivita;
      s.data.aktivityPřihlášenPodleId[x.přihlášen.id] = x.přihlášen;
    });

    s.data.aktivityPodleRoku[GAMECON_KONSTANTY.ROCNIK] = aktivity.map(x => x.aktivita);
    s.data.aktivityPřihlášenPodleRoku[GAMECON_KONSTANTY.ROCNIK] =
      aktivity.map(x => x.přihlášen);
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
          x.přihlášen.prihlasovatelna = true;
        }),
        produce(createAktivita({ id: 2, hodina: 9 }), x => {
          x.aktivita.vdalsiVlne = true;
        }),
        produce(createAktivita({ id: 3, hodina: 10 }), x => {
          x.aktivita.vBudoucnu = true;
        }),
        produce(createAktivita({ id: 4, hodina: 11 }), x => {
          x.přihlášen.prihlasovatelna = true;
          x.přihlášen.prihlasen = true;
          x.přihlášen.stavPrihlaseni = "prihlasen";
        }),
        produce(createAktivita({ id: 5, hodina: 12, trvání: 3 }), x => {
          x.přihlášen.prihlasovatelna = true;
          x.přihlášen.prihlasen = true;
          x.přihlášen.stavPrihlaseni = "prihlasen";
        }),
        produce(createAktivita({ id: 6, hodina: 13 }), x => {
          // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
          x.přihlášen.obsazenost!.m = 1;
        }),
        produce(createAktivita({ id: 7, hodina: 14 }), x => {
          x.přihlášen.vedu = true;
        }),
        produce(createAktivita({ id: 8, hodina: 15 }), x => {
          x.přihlášen.prihlasovatelna = true;
          x.přihlášen.prihlasen = true;
          x.přihlášen.stavPrihlaseni = "prihlasenAleNedorazil";
        }),
        produce(createAktivita({ id: 9, hodina: 9+24*2 }), x => {
          x.přihlášen.prihlasovatelna = true;
          x.přihlášen.prihlasen = true;
          x.přihlášen.stavPrihlaseni = "prihlasen";
        }),
        produce(createAktivita({ id: 10, hodina: 8+24*3 }), x => {
          x.přihlášen.prihlasovatelna = true;
          x.přihlášen.prihlasen = true;
          x.přihlášen.stavPrihlaseni = "prihlasen";
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