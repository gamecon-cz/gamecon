import { FunctionComponent } from "preact";
import { useState } from "preact/hooks";
import {
  useAktivita,
  useNastaveniTymuModalAktivitaId,
  useNastaveniTymuModalData,
  useNastaveniTymuModalNazevAktivity,
  useUživatelJeSefInfa,
} from "../../../../store/program/selektory";
import {
  dotáhniNastaveníTýmuProModal,
  nastavChyba,
  nastavModalNastaveníTýmu,
} from "../../../../store/program/slices/všeobecnéSlice";
import { proveďAkciAktivity } from "../../../../store/program/slices/programDataSlice";
import { useProgramStore } from "../../../../store/program";
import { AkceTymu, fetchAktivitaTymAkce } from "../../../../api/program";
import { NastaveniTymuView } from "../../../../components/NastaveniTymuView/NastaveniTymuView";
import produce from "immer";

/** tohel je quick fix na dvojí registraci registrujDotahováníNastaveníTýmu */
let registrované = false;
export const registrujDotahováníNastaveníTýmu = () => {
  if (registrované) return;
  registrované = true;
  useProgramStore.subscribe(s => s.všeobecné.nastaveniTymu?.aktivitaId, (aktivitaId) => {
    if (aktivitaId)
      void dotáhniNastaveníTýmuProModal();
  });
}

export const NastaveniTymuModal: FunctionComponent<{}> = () => {
  const aktivitaId = useNastaveniTymuModalAktivitaId();
  const aktivita = useAktivita(aktivitaId ?? -1);
  const storeNazevAktivity = useNastaveniTymuModalNazevAktivity();
  const data = useNastaveniTymuModalData();
  const [načítáAkci, setNačítáAkci] = useState(false);
  const [bylaZměna, setBylaZměna] = useState(false);
  const setChyba = nastavChyba;

  const sNačítáním = <T,>(fn: () => Promise<T>, jeZměna = false) => async () => {
    setNačítáAkci(true);
    try {
      const res = await fn();
      if (jeZměna) setBylaZměna(true);
      return res;
    } finally {
      setNačítáAkci(false);
    }
  };

  if (!aktivitaId) return <></>;

  const přihlášenZAktivity = aktivita?.stavPrihlaseni === "prihlasen"
    || aktivita?.stavPrihlaseni === "dorazilJakoNahradnik"
    || aktivita?.stavPrihlaseni === "pozdeZrusil"
    || aktivita?.stavPrihlaseni === "prihlasenADorazil"
    || aktivita?.stavPrihlaseni === "prihlasenAleNedorazil"
    ;

  // Pokud aktivita není v store (stránka bez programu), odvodíme přihlášení z dat týmu
  const přihlášen = aktivita ? přihlášenZAktivity : (data?.id ?? 0) > 0;

  // Program page: skryj modal dokud se data nenačtou
  if (aktivita && přihlášen && !data) return <></>;

  const zavřít = () => {
    if (!aktivita && bylaZměna) {
      window.location.reload();
      return;
    }
    setChyba(undefined);
    nastavModalNastaveníTýmu();
  };

  const proveďAkciTymu = async (akceTymu: Omit<AkceTymu, "idTymu" | "aktivitaId">) => {
    const akceTymuCpy = produce(akceTymu as AkceTymu, akce=>{
      if (akce.typ !== "zalozPrazdnyTym" && data?.id) {
        akce.idTymu = data.id;
      }
      if (aktivita?.id
          && (akce.typ === "zalozPrazdnyTym"
            || akce.typ === "odhlasClena"
            || akce.typ === "potvrdVyberAktivit"
          )) {
        akce.aktivitaId = aktivita?.id;
      }
    })
    const result = await sNačítáním(async ()=> await fetchAktivitaTymAkce(akceTymuCpy))();
    if (!result.úspěch) {
      setChyba(result.chyba?.hláška);
      return;
    }
    void dotáhniNastaveníTýmuProModal();
  };

  return (
    <NastaveniTymuView
      nazevAktivity={aktivita?.nazev ?? storeNazevAktivity}
      data={data ?? null}
      přihlášen={přihlášen}
      načítá={!aktivita && !data}
      načítáAkci={načítáAkci}
      onZavřít={zavřít}
      onPřipojitSe={(idTýmu, kód) => void sNačítáním(() => proveďAkciAktivity(aktivitaId, "prihlasit", idTýmu, kód).then(zavřít), true)()}
      onOdhlásit={() => void proveďAkciAktivity(aktivitaId, "odhlasit")}
      onProveďAkci={proveďAkciTymu}
    />
  );
};
