import { FunctionComponent } from "preact";
import {
  useAktivita,
  useNastaveniTymuModalAktivitaId,
  useNastaveniTymuModalData,
  useNastaveniTymuModalNazevAktivity,
} from "../../../../store/program/selektory";
import {
  dotáhniNastaveníTýmuProModal,
  nastavModalNastaveníTýmu,
  nastavModalOdhlásit,
} from "../../../../store/program/slices/všeobecnéSlice";
import { proveďAkciAktivity } from "../../../../store/program/slices/programDataSlice";
import { useProgramStore } from "../../../../store/program";
import { fetchNastavVerejnostTymu } from "../../../../api/program";
import { NastaveniTymuView } from "../../../../components/NastaveniTymuView/NastaveniTymuView";


export const registrujDotahováníNastaveníTýmu = () => {
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

  if (!aktivitaId) return <></>;

  const přihlášenZAktivity = aktivita?.stavPrihlaseni === "prihlasen"
    || aktivita?.stavPrihlaseni === "dorazilJakoNahradnik"
    || aktivita?.stavPrihlaseni === "pozdeZrusil"
    || aktivita?.stavPrihlaseni === "prihlasenADorazil"
    || aktivita?.stavPrihlaseni === "prihlasenAleNedorazil"
    ;

  // Pokud aktivita není v store (stránka bez programu), odvodíme přihlášení z dat týmu
  const přihlášen = aktivita ? přihlášenZAktivity : (data?.kod ?? 0) > 0;

  // Program page: skryj modal dokud se data nenačtou
  if (aktivita && přihlášen && !data) return <></>;

  const zavřít = () => nastavModalNastaveníTýmu();

  const přepniVerejnost = async () => {
    if (!data || data.verejny === undefined || !data.kod) return;
    await fetchNastavVerejnostTymu(aktivitaId, data.kod, !data.verejny);
    void dotáhniNastaveníTýmuProModal();
  };

  return (
    <NastaveniTymuView
      nazevAktivity={aktivita?.nazev ?? storeNazevAktivity}
      data={data ?? null}
      přihlášen={přihlášen}
      načítá={!aktivita && !data}
      onZavřít={zavřít}
      onZaložitTým={() => void proveďAkciAktivity(aktivitaId, "prihlasit").then(zavřít)}
      onPřipojitSe={(kód) => void proveďAkciAktivity(aktivitaId, "prihlasit", kód).then(zavřít)}
      onPřepniVerejnost={() => void přepniVerejnost()}
      onOdhlásit={() => nastavModalOdhlásit(aktivitaId)}
    />
  );
};
