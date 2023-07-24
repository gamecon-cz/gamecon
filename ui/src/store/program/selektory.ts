import { useProgramStore } from ".";
import { Pohlavi, PřihlášenýUživatel } from "../../api/přihlášenýUživatel";
import { ProgramTabulkaVýběr, ProgramURLState } from "./logic/url";
import shallow from "zustand/shallow";
import { FiltrAktivit, filtrujAktivity } from "./logic/aktivity";
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

