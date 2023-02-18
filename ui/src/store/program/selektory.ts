import { useProgramStore } from ".";
import { Aktivita, AktivitaPřihlášen } from "../../api/program";
import { Pohlavi, PřihlášenýUživatel } from "../../api/přihlášenýUživatel";
import { ProgramTabulkaVýběr, ProgramURLState } from "./logic/url";

export const useAktivity = (): { aktivity: Aktivita[]; aktivityPřihlášen: AktivitaPřihlášen[]; } => {
  const urlState = useProgramStore((s) => s.urlState);
  const aktivity = useProgramStore(
    (s) => s.data.aktivityPodleRoku[urlState.rok] ?? []
  );
  const aktivityPřihlášen = useProgramStore(
    (s) => s.data.aktivityPřihlášenPodleRoku[urlState.rok] ?? []
  );

  return { aktivity, aktivityPřihlášen };
};

export const useAktivita = (akitivitaId: number): { aktivita: Aktivita | undefined; aktivitaPřihlášen: AktivitaPřihlášen | undefined; } => {
  const aktivita = useProgramStore(s => s.data.aktivityPodleId[akitivitaId]);
  const aktivitaPřihlášen = useProgramStore(s => s.data.aktivityPřihlášenPodleId[aktivita.id]);

  return { aktivita, aktivitaPřihlášen };
};

export const useAktivitaNáhled = (): Aktivita | undefined => useProgramStore(s => s.data.aktivityPodleId[s.urlState.aktivitaNáhledId ?? -1]);
export const useUrlState = (): ProgramURLState => useProgramStore(s=>s.urlState);
export const useUrlVýběr = (): ProgramTabulkaVýběr => useProgramStore((s) => s.urlState.výběr);
export const useUrlStateMožnosti = (): ProgramTabulkaVýběr[] => useProgramStore(s => s.urlStateMožnosti);

export const useUživatel = (): PřihlášenýUživatel => useProgramStore(s => s.přihlášenýUživatel.data);
export const useUživatelPohlaví = (): Pohlavi | undefined => useProgramStore((s) => s.přihlášenýUživatel.data?.pohlavi);

