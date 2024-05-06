import { useProgramStore } from ".";
import { Pohlavi, PřihlášenýUživatel } from "../../api/přihlášenýUživatel";
import { ProgramTabulkaVýběr, ProgramURLStav } from "./logic/url";
import shallow from "zustand/shallow";
import { FiltrAktivit, filtrujAktivity, KategorieŠtítků } from "./logic/aktivity";
import { Aktivita, filtrujDotaženéAktivity, jeAktivitaDotažená } from "./slices/programDataSlice";
import { distinct } from "../../utils";
import { useMemo } from "preact/hooks";

const useFiltrAktivitNeboZeStavu = (aktivitaFiltr?: FiltrAktivit) => {
  const urlStav = useProgramStore((s) => s.urlStav);

  return aktivitaFiltr ?? (urlStav as FiltrAktivit);
};

const useŠtítkyMapováníKategorieŠtítků = () => {
  const štítky = useŠtítky();

  const mapování: KategorieŠtítků =
    useMemo(
      () => Object.fromEntries(štítky.map(x => [x.nazev, x.nazevKategorie])),
      [štítky]
    );

  return mapování;
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
  const filtr = useFiltrAktivitNeboZeStavu(aktivitaFiltr);
  const mapaŠtítků = useŠtítkyMapováníKategorieŠtítků();

  const aktivityDotažené = useAktivityDotažené();

  const aktivityFiltrované = filtrujAktivity(aktivityDotažené, filtr, mapaŠtítků);

  return aktivityFiltrované;
};

export const useAktivita = (akitivitaId: number): Aktivita | undefined =>
  useProgramStore(s => {
    const aktivita = s.data.aktivityPodleId[akitivitaId];
    return jeAktivitaDotažená(aktivita) ? aktivita : undefined;
  });


export const useAktivitaNáhled = (): Aktivita | undefined =>
  useProgramStore(s => {
    const aktivita = s.data.aktivityPodleId[s.urlStav.aktivitaNáhledId ?? -1];
    return jeAktivitaDotažená(aktivita) ? aktivita : undefined;
  }, shallow);

// TODO: pouze jako inspirace pro implementaci počtu aktivit pro štítek, potom smazat
/**
 * @deprecated použít useŠtítkyPodleKategorie
 */
export const useTagySPočtemAktivit = () => {
  const urlStavMožnosti = useUrlStavMožnosti();

  const urlStav = useProgramStore((s) => s.urlStav);

  const aktivvityRočník = useAktivityFiltrované({
    ročník: urlStav.ročník,
  });

  const tagy = urlStavMožnosti.tagy;

  const tagyPočetVRočníku = new Map<string, number>(tagy.map(x => [x, 0] as [string, number]));

  for (const aktivita of aktivvityRočník) {
    for (const tag of aktivita.stitky) {
      tagyPočetVRočníku.set(tag, (tagyPočetVRočníku.get(tag) ?? 0) + 1);
    }
  }

  return Array.from(tagyPočetVRočníku).map(x => ({ tag: x[0], celkemVRočníku: x[1] }))
    .sort((a, b) => b.celkemVRočníku - a.celkemVRočníku);
};

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

const prázdnéPole: string[] = [];
export const useŠtítkyVybranéPodleKategorie = () => {
  const urlStav = useUrlStav();
  const vybranéŠtítky = urlStav.filtrTagy ?? prázdnéPole;
  const štítkyPodleKategorie = useŠtítkyPodleKategorie();
  const vybranéŠtítkyPodleKategorie = useMemo(
    () =>
      štítkyPodleKategorie
        .map(({ kategorie, štítky }) => ({
          kategorie,
          štítky: štítky.filter(štítek => vybranéŠtítky.some(x => x === štítek.nazev))
        }))
        .filter(x => x.štítky.length)
    ,
    [štítkyPodleKategorie, vybranéŠtítky]);

  return vybranéŠtítkyPodleKategorie;
};

export const useŠtítkyPočetAktivit = () =>{
  const štítky = useŠtítky();
  const aktivityDotažené = useAktivityDotažené();
  const mapaŠtítků = useŠtítkyMapováníKategorieŠtítků();
  const filtr = useFiltrAktivitNeboZeStavu();

  const štítekSPočtemAktivit = štítky.map(štítek =>({
    štítek: štítek.nazev,
    počet: filtrujAktivity(aktivityDotažené, {
      ...filtr,
      filtrTagy: (filtr.filtrTagy ?? []).concat([štítek.nazev]),
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

