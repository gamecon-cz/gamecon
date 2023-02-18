import { ProgramStateCreator, useProgramStore } from "..";
import { GAMECON_KONSTANTY } from "../../../env";
import { generujUrl, parsujUrl, ProgramTabulkaVýběr, ProgramURLState, tabulkaMožnostíUrlStateProgram, URL_STATE_VÝCHOZÍ_MOŽNOST } from "../logic/url";


export type ProgramUrlSlice = {
  urlState: ProgramURLState
  urlStateMožnosti: ProgramTabulkaVýběr[],
}

export const createProgramUrlSlice: ProgramStateCreator<ProgramUrlSlice> = () => ({
  urlState: {
    výběr: URL_STATE_VÝCHOZÍ_MOŽNOST,
    aktivitaNáhledId: undefined,
    rok: GAMECON_KONSTANTY.ROCNIK,
  },
  urlStateMožnosti: tabulkaMožnostíUrlStateProgram(),
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

export const nastavUrlVýběr = (možnost: ProgramTabulkaVýběr) =>{
  useProgramStore.setState((s) => {
    s.urlState.výběr = možnost;
  }, undefined, "nastav program den");
};
