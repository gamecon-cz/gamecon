import { ProgramStateCreator, useProgramStore } from "..";
import { AktivitaStav, AktivitaStavyVšechny } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import { generujUrl, parsujUrl, ProgramTabulkaVýběr, ProgramURLState, urlStateProgramTabulkaMožnostíDnyMůj, URL_STATE_VÝCHOZÍ_STAV } from "../logic/url";

export type ProgramUrlSlice = {
  urlState: ProgramURLState
  urlStateMožnosti: {
    dny: ProgramTabulkaVýběr[],
    linie: string[],
    tagy: string[],
    stavy: readonly AktivitaStav[],
  },
}

export const createProgramUrlSlice: ProgramStateCreator<ProgramUrlSlice> = () => ({
  urlState: URL_STATE_VÝCHOZÍ_STAV,
  urlStateMožnosti: {
    dny: urlStateProgramTabulkaMožnostíDnyMůj(),
    linie: [],
    tagy: [],
    stavy: AktivitaStavyVšechny,
  }
});



/** nastaví url a url-stav na hodnotu */
const nastavUrlState = (url: string) => {
  useProgramStore.setState(s => {
    s.urlState = parsujUrl(url);
  }, undefined, "nastavUrlState");
};


export const nastavStateZUrl = () => {
  nastavUrlState(location.href);
};

export const nastavUrlZState = (replace = false) => {
  const současnéUrl = location.href;
  const novéUrl = generujUrl(useProgramStore.getState().urlState);

  /** stavy jsou ekvivalentní, netřeba cokoliv měnit */
  if (současnéUrl === novéUrl || !novéUrl) return;

  history[!replace ? "pushState" : "replaceState"](null, "", novéUrl);
};


export const nastavUrlAktivitaNáhledId = (aktivitaNáhledId: number) => {
  useProgramStore.setState(
    (s) => {
      s.urlState.aktivitaNáhledId = aktivitaNáhledId;
    },
    undefined,
    "nastav url nahled id"
  );
};

export const skryjAktivitaNáhledId = () => {
  useProgramStore.setState((s) => {
    s.urlState.aktivitaNáhledId = undefined;
  });
};

export const nastavUrlVýběr = (možnost: ProgramTabulkaVýběr) => {
  useProgramStore.setState((s) => {
    s.urlState.výběr = možnost;
  }, undefined, "nastav program den");
};

// export const nastavFiltrLinie = (linie: string, hodnota: boolean) => {
//   useProgramStore.setState((s) => {
//     if (hodnota) {
//       const filtrLinie = s.urlState.filtrLinie ?? [];
//       if (!s.urlState.filtrLinie)
//         s.urlState.filtrLinie = filtrLinie;

//       filtrLinie.push(linie);
//       if (!s.urlStateMožnosti.linie.some(x => !filtrLinie.some(y => x === y))) {
//         s.urlState.filtrLinie = undefined;
//       }
//     } else {
//       s.urlState.filtrLinie = (s.urlState.filtrLinie ?? s.urlStateMožnosti.linie).filter(x => x !== linie);
//     }
//   }, undefined, "nastav program linie");
// };

// TODO: lepší název
/**
 * Vybrané všechny nebo žádné => undefined
 */
const filtrZMožností = <T extends string>(vybrané: T[], všechny: T[]): T[] | undefined => {
  return !(!vybrané.length || (všechny.length && !všechny.some(x => !vybrané?.some(y => x === y)))) ? vybrané : undefined;
};

export const nastavFiltrRočník = (ročník?: number) => {
  useProgramStore.setState((s) => {
    s.urlState.ročník = ročník ?? GAMECON_KONSTANTY.ROCNIK;
  }, undefined, "nastav filtr linie");
};

export const nastavFiltrLinií = (vybranéLinie: string[]) => {
  useProgramStore.setState((s) => {
    s.urlState.filtrLinie = filtrZMožností(vybranéLinie,s.urlStateMožnosti.linie);
  }, undefined, "nastav filtr linie");
};

export const nastavFiltrTagů = (vybranéTagy: string[]) => {
  useProgramStore.setState((s) => {
    s.urlState.filtrTagy = filtrZMožností(vybranéTagy,s.urlStateMožnosti.linie);
  }, undefined, "nastav filtr tagy");
};

export const nastavFiltrStavů = (vybranéStavy: AktivitaStav[]) => {
  useProgramStore.setState((s) => {
    s.urlState.filtrStavAktivit = filtrZMožností(vybranéStavy, s.urlStateMožnosti.stavy);
  }, undefined, "nastav filtr stavy");
};

export const nastavFiltrTextu = (text: string | undefined | null) => {
  useProgramStore.setState((s) => {
    s.urlState.filtrText = !text /* žádná hodnota nebo prázdná */ ? undefined : text;
  }, undefined, "nastav filtr text");
};

export const nastavFiltrPřihlašovatelné = (přihlašovatelné:boolean) =>{
  useProgramStore.setState((s) => {
    s.urlState.filtrPřihlašovatelné = přihlašovatelné;
  }, undefined, "nastav filtr přihlašovatelné");
};
