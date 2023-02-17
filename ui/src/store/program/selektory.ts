import { useProgramStore } from ".";
import { Aktivita } from "../../api/program";

export const useAktivity = () => {
  const urlState = useProgramStore((s) => s.urlState);
  const aktivity = useProgramStore(
    (s) => s.data.aktivityPodleRoku[urlState.rok] ?? []
  );
  const aktivityPřihlášen = useProgramStore(
    (s) => s.data.aktivityPřihlášenPodleRoku[urlState.rok] ?? []
  );

  return { aktivity, aktivityPřihlášen };
};

export const useAktivitaNáhled = (): Aktivita | undefined => {
  const urlState = useProgramStore(s => s.urlState);
  const aktivita = useProgramStore(s => s.data.aktivityPodleId[urlState.aktivitaNáhledId ?? -1]);
  return aktivita;
};
