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
import { FiltrAktivit, filtrujAktivity } from "./logic/aktivity";
import { ProgramTabulkaVýběr, ProgramURLState } from "./logic/url";
import { Aktivita, filtrujDotaženéAktivity, jeAktivitaDotažená } from "./slices/programDataSlice";

const useFiltrAktivit = (aktivitaFiltr?: FiltrAktivit) => {
  const urlState = useProgramStore((s) => s.urlState);

  return aktivitaFiltr ?? (urlState as FiltrAktivit);
};

/**
 * Všechny dotažené aktivity
 */
export const useAktivityDotažené = () => useProgramStore(
  (s) => filtrujDotaženéAktivity(s.data.aktivityPodleId)
);

/**
 * Aplikuje filtr na aktivity, pokud není předaný 
 */
export const useAktivityFiltrované = (aktivitaFiltr?: FiltrAktivit): Aktivita[] => {
  const filtr = useFiltrAktivit(aktivitaFiltr);

  const aktivityDotažené = useAktivityDotažené();

  const aktivityFiltrované = filtrujAktivity(aktivityDotažené, filtr);

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
  });

/**
 * Tagy s počtem aktivit které mají filtr daný ročník
 */
export const useTagySPočtemAktivit = () => {
  const urlStateMožnosti = useUrlStateMožnosti();

  const urlState = useProgramStore((s) => s.urlState);

  const aktivvityRočník = useAktivityFiltrované({
    ročník: urlState.ročník,
  });

  const tagy = urlStateMožnosti.tagy;

  const tagyPočetVRočníku = new Map<string, number>(tagy.map(x => [x, 0] as [string, number]));

  for (const aktivita of aktivvityRočník) {
    for (const tag of aktivita.stitky) {
      tagyPočetVRočníku.set(tag, (tagyPočetVRočníku.get(tag) ?? 0) + 1);
    }
  }

  return Array.from(tagyPočetVRočníku).map(x => ({ tag: x[0], celkemVRočníku: x[1] }))
    .sort((a, b) => b.celkemVRočníku - a.celkemVRočníku);
};

export const useUrlState = (): ProgramURLState => useProgramStore(s => s.urlState);
export const useUrlVýběr = (): ProgramTabulkaVýběr => useProgramStore((s) => s.urlState.výběr);
export const useUrlStateMožnostiDny = (): ProgramTabulkaVýběr[] => useProgramStore(s => s.urlStateMožnosti.dny);
export const useUrlStateMožnosti = () => useProgramStore(s => s.urlStateMožnosti);

export const useUživatel = (): PřihlášenýUživatel => useProgramStore(s => s.přihlášenýUživatel.data);
export const useUživatelPohlaví = (): Pohlavi | undefined => useProgramStore((s) => s.přihlášenýUživatel.data?.pohlavi);

export const useFiltryOtevřené = (): boolean => useProgramStore(s => s.všeobecné.filtryOtevřené);

