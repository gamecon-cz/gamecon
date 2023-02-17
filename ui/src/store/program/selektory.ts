import { useProgramStore } from ".";
import { Pohlavi, PřihlášenýUživatel } from "../../api/přihlášenýUživatel";
import { ProgramTabulkaVýběr, ProgramURLState } from "./logic/url";
import shallow from "zustand/shallow";
import { Aktivita, jeAktivitaDotažená } from "./slices/programDataSlice";

// TODO: přidat zbytek filtrů
export const useAktivityFiltrované = (): Aktivita[] => {
  const urlState = useProgramStore((s) => s.urlState);
  const aktivity = useProgramStore(
    (s) => Object.values(s.data.aktivityPodleId).filter(jeAktivitaDotažená).filter(x => new Date(x.cas.od).getFullYear() === urlState.rok)
  );
  const urlStateVýběr = useUrlVýběr();

  const aktivityFiltrované = aktivity.filter((aktivita) =>
    urlStateVýběr.typ === "můj"
      ? aktivita?.stavPrihlaseni !=
      undefined
      : new Date(aktivita.cas.od).getDay() === urlStateVýběr.datum.getDay()
  );

  return aktivityFiltrované;
};

export const useAktivita = (akitivitaId: number): Aktivita | undefined =>
  useProgramStore(s => {
    const aktivita = s.data.aktivityPodleId[akitivitaId];
    return jeAktivitaDotažená(aktivita) ? aktivita : undefined;
  });


export const useAktivitaNáhled = (): Aktivita | undefined =>
  useProgramStore(s => {
    const aktivita = s.data.aktivityPodleId[s.urlState.aktivitaNáhledId ?? -1];
    return jeAktivitaDotažená(aktivita) ? aktivita : undefined;
  }, shallow);

export const useUrlState = (): ProgramURLState => useProgramStore(s => s.urlState);
export const useUrlVýběr = (): ProgramTabulkaVýběr => useProgramStore((s) => s.urlState.výběr);
export const useUrlStateMožnosti = (): ProgramTabulkaVýběr[] => useProgramStore(s => s.urlStateMožnosti);

export const useUživatel = (): PřihlášenýUživatel => useProgramStore(s => s.přihlášenýUživatel.data);
export const useUživatelPohlaví = (): Pohlavi | undefined => useProgramStore((s) => s.přihlášenýUživatel.data?.pohlavi);

