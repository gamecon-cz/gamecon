import { useProgramStore } from ".";
import { Pohlavi, ApiUživatel } from "../../api/přihlášenýUživatel";
import { ProgramTabulkaVýběr, ProgramURLStav } from "./logic/url";
import { FiltrAktivit, filtrujAktivity, MapováníTagů, vytvořMapováníTagů } from "./logic/aktivity";
import { PRÁZDNÉ_POLE, distinct } from "../../utils";
import { useMemo } from "preact/hooks";
import { GAMECON_KONSTANTY } from "../../env";
import { Aktivita } from "./slices/programDataSlice";
import { NastaveniTymuData } from "./slices/všeobecnéSlice";

const useFiltrAktivitNeboZeStavu = (aktivitaFiltr?: FiltrAktivit) => {
  const urlStav = useProgramStore((s) => s.urlStav);

  return aktivitaFiltr ?? (urlStav as FiltrAktivit);
};

const useMapováníTagů = () => {
  const tagy = useTagy();

  const mapování: MapováníTagů =
    useMemo(() => vytvořMapováníTagů(tagy), [tagy]);

  return mapování;
};

/**
 * Všechny dotažené aktivity
 */
export const useAktivity = (ročník?: number) => {
  return useProgramStore((s) => {
    if (ročník)
      return Object.values(s.data.podleRočníku[ročník].aktivityPodleId ?? {});
    else
      // todo: tahle část se opakuje na více místech
      return Object.values(s.data.podleRočníku).flatMap(x=>Object.values(x.aktivityPodleId));
  });
}

/**
 * Aplikuje filtr na aktivity, pokud není předaný
 */
export const useAktivityFiltrované = (aktivitaFiltr?: FiltrAktivit): Aktivita[] => {
  const filtr = useFiltrAktivitNeboZeStavu(aktivitaFiltr);
  const mapaTagů = useMapováníTagů();

  const aktivity = useAktivity(aktivitaFiltr?.ročník);

  const aktivityFiltrované = filtrujAktivity(aktivity, filtr, mapaTagů);

  return aktivityFiltrované;
};

/**
 * Aktuální stav dotahování aktivit pro daný ročník nebo z aktuálního filtru
 */
export const useAktivityStatus = (ročník?: number) => {
  const filtr = useFiltrAktivitNeboZeStavu();
  const ročníkZFiltru = ročník ?? filtr.ročník ?? GAMECON_KONSTANTY.ROCNIK;

  return useProgramStore(s=>s.dataStatus.podleRoku[ročníkZFiltru]);
};

export const useAktivita = (akitivitaId: number): Aktivita | undefined =>
  useProgramStore((s) => {
    for (const ročník of Object.values(s.data.podleRočníku)) {
      const aktivita = ročník.aktivityPodleId[akitivitaId];
      if (aktivita) return aktivita;
    }
  });

export const useAktivitaNáhled = (): Aktivita | undefined =>
  useProgramStore((s) => {
    for (const ročník of Object.values(s.data.podleRočníku)) {
      const aktivita = ročník.aktivityPodleId[s.urlStav.aktivitaNáhledId ?? -1];
      if (aktivita) return aktivita;
    }
  }
  // todo: použít shallow ?
  // , shallow
  );

export const useTagyPodleKategorie = () => {
  const tagy = useTagy();

  const tagyPodleKategorie = useMemo(() => {
    const všechnyKategorie = distinct(tagy.map(x => x.nazevKategorie));

    return všechnyKategorie.map(kategorie => ({
      kategorie: kategorie,
      tagy: tagy.filter(tag => tag.nazevKategorie === kategorie)
    }));
  }, [tagy]);

  return tagyPodleKategorie;
};

export const useTagyVybranéPodleKategorie = () => {
  const urlStav = useUrlStav();
  const vybranéTagyId = urlStav.filtrTagy ?? PRÁZDNÉ_POLE;
  const tagyPodleKategorie = useTagyPodleKategorie();
  const vybranéTagyPodleKategorie = useMemo(
    () =>
      tagyPodleKategorie
        .map(({ kategorie, tagy }) => ({
          kategorie,
          tagy: tagy.filter(tag => vybranéTagyId.some(x => x === tag.id))
        }))
        .filter(x => x.tagy.length)
    ,
    [tagyPodleKategorie, vybranéTagyId]);

  return vybranéTagyPodleKategorie;
};

export const useTagyPočetAktivit = () => {
  const tagy = useTagy();
  const aktivity = useAktivity();
  const mapaTagů = useMapováníTagů();
  const filtr = useFiltrAktivitNeboZeStavu();

  const tagSPočtemAktivit = tagy.map(tag => ({
    tagId: tag.id,
    počet: filtrujAktivity(aktivity, {
      ...filtr,
      filtrTagy: (filtr.filtrTagy ?? []).concat([tag.id]),
    }, mapaTagů).length,
  }));

  return tagSPočtemAktivit;
};

export const useUrlStav = (): ProgramURLStav => useProgramStore(s => s.urlStav);
export const useUrlVýběr = (): ProgramTabulkaVýběr => useProgramStore((s) => s.urlStav.výběr);
export const useUrlStavMožnostiDny = (): ProgramTabulkaVýběr[] => useProgramStore(s => s.urlStavMožnosti.dny);
export const useUrlStavMožnosti = () => useProgramStore(s => s.urlStavMožnosti);
export const useUrlStavStavyFiltr = () => useProgramStore(s => s.urlStav.filtrStavAktivit ?? []);

export const useUživatel = (): ApiUživatel => useProgramStore(s => s.přihlášenýUživatel.data);
export const useUživatelPohlaví = (): Pohlavi | undefined => useProgramStore((s) => s.přihlášenýUživatel.data?.pohlavi);
export const useUživatelJeSefInfa = (): boolean => useProgramStore((s) => s.přihlášenýUživatel.data?.sefInfa ?? false);

export const useFiltryOtevřené = (): boolean => useProgramStore(s => s.všeobecné.filtryOtevřené);
export const useOdhlasitModalAktivitaId = (): number | undefined => useProgramStore(s => s.všeobecné.modalOdhlásitAktivitaId);
export const useNastaveniTymuModalAktivitaId = (): number | undefined => useProgramStore(s => s.všeobecné.nastaveniTymu?.aktivitaId);
export const useNastaveniTymuModalNazevAktivity = (): string | undefined => useProgramStore(s => s.všeobecné.nastaveniTymu?.nazevAktivity);
export const useNastaveniTymuModalData = (): NastaveniTymuData | undefined => useProgramStore(s => s.všeobecné.nastaveniTymu?.data);

export const useTagy = () => useProgramStore((s) => s.data.tagy);

