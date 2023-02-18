import { useProgramStore } from ".";
import { APIAktivita, APIAktivitaPřihlášen } from "../../api/program";
import { Pohlavi, PřihlášenýUživatel } from "../../api/přihlášenýUživatel";
import { ProgramTabulkaVýběr, ProgramURLState } from "./logic/url";
import shallow from "zustand/shallow";

// TODO: přidat zbytek filtrů
export const useAktivityFiltrované = (): { aktivity: APIAktivita[]; aktivityPřihlášen: APIAktivitaPřihlášen[]; } => {
  const urlState = useProgramStore((s) => s.urlState);
  const aktivity = useProgramStore(
    (s) => Object.values(s.data.aktivityPodleId).filter(x => new Date(x.cas.od).getFullYear() === urlState.rok)
  );
  const aktivityPřihlášen = useProgramStore(
    (s) => aktivity.map(x => s.data.aktivityPřihlášenPodleId[x.id]).filter(x => x)
  );

  return { aktivity, aktivityPřihlášen };
};

export const useAktivita = (akitivitaId: number): { aktivita: APIAktivita | undefined; aktivitaPřihlášen: APIAktivitaPřihlášen | undefined; } => {
  const aktivita = useProgramStore(s => s.data.aktivityPodleId[akitivitaId]);
  const aktivitaPřihlášen = useProgramStore(s => s.data.aktivityPřihlášenPodleId[aktivita.id]);

  return { aktivita, aktivitaPřihlášen };
};

export const useAktivitaNáhled = (): { aktivita: APIAktivita | undefined; aktivitaPřihlášen: APIAktivitaPřihlášen | undefined; } =>
  useProgramStore(s => {
    const aktivita = s.data.aktivityPodleId[s.urlState.aktivitaNáhledId ?? -1];
    const aktivitaPřihlášen = s.data.aktivityPřihlášenPodleId[s.urlState.aktivitaNáhledId ?? -1];
    return ({ aktivita, aktivitaPřihlášen });
  }, shallow);

export const useUrlState = (): ProgramURLState => useProgramStore(s => s.urlState);
export const useUrlVýběr = (): ProgramTabulkaVýběr => useProgramStore((s) => s.urlState.výběr);
export const useUrlStateMožnosti = (): ProgramTabulkaVýběr[] => useProgramStore(s => s.urlStateMožnosti);

export const useUživatel = (): PřihlášenýUživatel => useProgramStore(s => s.přihlášenýUživatel.data);
export const useUživatelPohlaví = (): Pohlavi | undefined => useProgramStore((s) => s.přihlášenýUživatel.data?.pohlavi);

