import { ProgramStateCreator, useProgramStore } from "..";
import { AktivitaStav, AktivitaStavyVšechny } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import { generujUrl, parsujUrl, ProgramTabulkaVýběr, ProgramURLStav, urlStavProgramTabulkaMožnostíDnyMůj, URL_STATE_VÝCHOZÍ_STAV } from "../logic/url";

export type ProgramUrlSlice = {
  urlStav: ProgramURLStav
  urlStavMožnosti: {
    dny: ProgramTabulkaVýběr[],
    linie: string[],
    stavy: readonly AktivitaStav[],
  },
}

export const createProgramUrlSlice: ProgramStateCreator<ProgramUrlSlice> = () => ({
  urlStav: URL_STATE_VÝCHOZÍ_STAV,
  urlStavMožnosti: {
    dny: urlStavProgramTabulkaMožnostíDnyMůj(),
    linie: [],
    stavy: AktivitaStavyVšechny,
  }
});



/** nastaví url a url-stav na hodnotu */
const nastavUrlStav = (url: string) => {
  useProgramStore.setState(s => {
    s.urlStav = parsujUrl(url);
  }, undefined, "nastavUrlStav");
};


export const nastavStateZUrl = () => {
  nastavUrlStav(location.href);
};

export const nastavUrlZState = (replace = false) => {
  const současnéUrl = location.href;
  const novéUrl = generujUrl(useProgramStore.getState().urlStav);

  /** stavy jsou ekvivalentní, netřeba cokoliv měnit */
  if (současnéUrl === novéUrl || !novéUrl) return;

  history[!replace ? "pushState" : "replaceState"](null, "", novéUrl);
};


export const nastavUrlAktivitaNáhledId = (aktivitaNáhledId: number) => {
  useProgramStore.setState(
    (s) => {
      s.urlStav.aktivitaNáhledId = aktivitaNáhledId;
    },
    undefined,
    "nastav url nahled id"
  );
};

export const skryjAktivitaNáhledId = () => {
  useProgramStore.setState((s) => {
    s.urlStav.aktivitaNáhledId = undefined;
  });
};

export const nastavUrlVýběr = (možnost: ProgramTabulkaVýběr) => {
  useProgramStore.setState((s) => {
    s.urlStav.výběr = možnost;
  }, undefined, "nastav program den");
};

/**
 * Vybrané všechny nebo žádné => undefined
 */
const filtrZMožností = <T extends string | number>(vybrané: T[], všechny: T[]): T[] | undefined => {
  return !(!vybrané.length || (všechny.length && !všechny.some(x => !vybrané?.some(y => x === y)))) ? vybrané : undefined;
};

export const nastavFiltrRočník = (ročník?: number) => {
  useProgramStore.setState((s) => {
    s.urlStav.ročník = ročník ?? GAMECON_KONSTANTY.ROCNIK;
  }, undefined, "nastav filtr linie");
};

export const nastavFiltrLinií = (vybranéLinie: string[]) => {
  useProgramStore.setState((s) => {
    s.urlStav.filtrLinie = filtrZMožností(vybranéLinie, s.urlStavMožnosti.linie);
  }, undefined, "nastav filtr linie");
};

export const nastavFiltrŠtítků = (vybranéTagy: number[]) => {
  useProgramStore.setState((s) => {
    s.urlStav.filtrTagy = vybranéTagy;
  }, undefined, "nastav filtr štítků");
};

export const nastavFiltrStavů = (vybranéStavy: AktivitaStav[]) => {
  useProgramStore.setState((s) => {
    s.urlStav.filtrStavAktivit = filtrZMožností(vybranéStavy, s.urlStavMožnosti.stavy);
  }, undefined, "nastav filtr stavy");
};

export const nastavFiltrTextu = (text: string | undefined | null) => {
  useProgramStore.setState((s) => {
    s.urlStav.filtrText = !text /* žádná hodnota nebo prázdná */ ? undefined : text;
  }, undefined, "nastav filtr text");
};

export const nastavFiltrPřihlašovatelné = (přihlašovatelné: boolean) => {
  useProgramStore.setState((s) => {
    s.urlStav.filtrPřihlašovatelné = přihlašovatelné;
  }, undefined, "nastav filtr přihlašovatelné");
};
