import { useProgramStore } from ".";
import { Pohlavi, PřihlášenýUživatel } from "../../api/přihlášenýUživatel";
import { ProgramTabulkaVýběr, ProgramURLStav } from "./logic/url";
import shallow from "zustand/shallow";
import { FiltrAktivit, filtrujAktivity, MapováníŠtítků, vytvořMapováníŠtítků } from "./logic/aktivity";
import { Aktivita, filtrujDotaženéAktivity, jeAktivitaDotažená } from "./slices/programDataSlice";
import { PRÁZDNÉ_POLE, distinct } from "../../utils";
import { useMemo } from "preact/hooks";

const useFiltrAktivitNeboZeStavu = (aktivitaFiltr?: FiltrAktivit) => {
  const urlStav = useProgramStore((s) => s.urlStav);

  return aktivitaFiltr ?? (urlStav as FiltrAktivit);
};

const useŠtítkyMapováníKategorieŠtítků = () => {
  const štítky = useŠtítky();

  const mapování: MapováníŠtítků =
    useMemo(() => vytvořMapováníŠtítků(štítky), [štítky]);

  return mapování;
};

/**
 * Všechny dotažené aktivity
 */
export const useAktivityDotažené = () =>
  useProgramStore((s) => filtrujDotaženéAktivity(s.data.aktivityPodleId));

/**
 * Aplikuje filtr na aktivity, pokud není předaný
 */
export const useAktivityFiltrované = (aktivitaFiltr?: FiltrAktivit): Aktivita[] => {
  const filtr = useFiltrAktivitNeboZeStavu(aktivitaFiltr);
  const mapaŠtítků = useŠtítkyMapováníKategorieŠtítků();

  const aktivityDotažené = useAktivityDotažené();

  const aktivityFiltrované = filtrujAktivity(aktivityDotažené, filtr, mapaŠtítků);

  return aktivityFiltrované;
};

export const useAktivita = (akitivitaId: number): Aktivita | undefined =>
  useProgramStore((s) => {
    const aktivita = s.data.aktivityPodleId[akitivitaId];
    return jeAktivitaDotažená(aktivita) ? aktivita : undefined;
  });

export const useAktivitaNáhled = (): Aktivita | undefined =>
  useProgramStore(s => {
    const aktivita = s.data.aktivityPodleId[s.urlStav.aktivitaNáhledId ?? -1];
    return jeAktivitaDotažená(aktivita) ? aktivita : undefined;
  }, shallow);

export const useŠtítkyPodleKategorie = () => {
  const štítky = useŠtítky();

  const štítkyPodleKategorie = useMemo(() => {
    const všechnyKategorie = distinct(štítky.map(x => x.nazevKategorie));

    return všechnyKategorie.map(kategorie => ({
      kategorie: kategorie,
      štítky: štítky.filter(štítek => štítek.nazevKategorie === kategorie)
    }));
  }, [štítky]);

  return štítkyPodleKategorie;
};

export const useŠtítkyVybranéPodleKategorie = () => {
  const urlStav = useUrlStav();
  const vybranéŠtítkyId = urlStav.filtrTagy ?? PRÁZDNÉ_POLE;
  const štítkyPodleKategorie = useŠtítkyPodleKategorie();
  const vybranéŠtítkyPodleKategorie = useMemo(
    () =>
      štítkyPodleKategorie
        .map(({ kategorie, štítky }) => ({
          kategorie,
          štítky: štítky.filter(štítek => vybranéŠtítkyId.some(x => x === štítek.id))
        }))
        .filter(x => x.štítky.length)
    ,
    [štítkyPodleKategorie, vybranéŠtítkyId]);

  return vybranéŠtítkyPodleKategorie;
};

export const useŠtítkyPočetAktivit = () => {
  const štítky = useŠtítky();
  const aktivityDotažené = useAktivityDotažené();
  const mapaŠtítků = useŠtítkyMapováníKategorieŠtítků();
  const filtr = useFiltrAktivitNeboZeStavu();

  const štítekSPočtemAktivit = štítky.map(štítek => ({
    štítekId: štítek.id,
    počet: filtrujAktivity(aktivityDotažené, {
      ...filtr,
      filtrTagy: (filtr.filtrTagy ?? []).concat([štítek.id]),
    }, mapaŠtítků).length,
  }));

  return štítekSPočtemAktivit;
};

export const useUrlStav = (): ProgramURLStav => useProgramStore(s => s.urlStav);
export const useUrlVýběr = (): ProgramTabulkaVýběr => useProgramStore((s) => s.urlStav.výběr);
export const useUrlStavMožnostiDny = (): ProgramTabulkaVýběr[] => useProgramStore(s => s.urlStavMožnosti.dny);
export const useUrlStavMožnosti = () => useProgramStore(s => s.urlStavMožnosti);
export const useUrlStavStavyFiltr = () => useProgramStore(s => s.urlStav.filtrStavAktivit ?? []);

export const useUživatel = (): PřihlášenýUživatel => useProgramStore(s => s.přihlášenýUživatel.data);
export const useUživatelPohlaví = (): Pohlavi | undefined => useProgramStore((s) => s.přihlášenýUživatel.data?.pohlavi);

export const useFiltryOtevřené = (): boolean => useProgramStore(s => s.všeobecné.filtryOtevřené);

export const useŠtítky = () => useProgramStore((s) => s.data.štítky);

