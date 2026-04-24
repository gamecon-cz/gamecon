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
  nastavModalNastaveníTýmu,
  nastavModalOdhlásit,
} from "../../../../store/program/slices/všeobecnéSlice";
import { proveďAkciAktivity } from "../../../../store/program/slices/programDataSlice";
import { useProgramStore } from "../../../../store/program";
import { fetchNastavVerejnostTymu, fetchPregenerujKodTymu, fetchOdhlasClena, fetchZalozPrazdnyTym, fetchPotvrdVyberAktivit, fetchPredejKapitana, fetchNastavLimitTymu, fetchSmazTym, fetchOdemkniTym } from "../../../../api/program";
import { NastaveniTymuView } from "../../../../components/NastaveniTymuView/NastaveniTymuView";

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
  const jeSefInfa = useUživatelJeSefInfa();
  const [chyba, setChyba] = useState<string | null>(null);
  const [načítáAkci, setNačítáAkci] = useState(false);
  const [bylaZměna, setBylaZměna] = useState(false);

  const sNačítáním = (fn: () => Promise<void>, jeZměna = false) => async () => {
    setNačítáAkci(true);
    try {
      await fn();
      if (jeZměna) setBylaZměna(true);
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
  const přihlášen = aktivita ? přihlášenZAktivity : (data?.kod ?? 0) > 0;

  // Program page: skryj modal dokud se data nenačtou
  if (aktivita && přihlášen && !data) return <></>;

  const zavřít = () => {
    if (!aktivita && bylaZměna) {
      window.location.reload();
      return;
    }
    setChyba(null);
    nastavModalNastaveníTýmu();
  };

  const přepniVerejnost = async () => {
    if (!data || data.verejny === undefined || !data.kod) return;
    await fetchNastavVerejnostTymu(aktivitaId, data.kod, !data.verejny);
    void dotáhniNastaveníTýmuProModal();
  };

  const přegenerujKód = async () => {
    if (!data?.kod) return;
    await fetchPregenerujKodTymu(aktivitaId, data.kod);
    void dotáhniNastaveníTýmuProModal();
  };

  const odhlásitČlena = async (idČlena: number) => {
    if (!data?.kod) return;
    await fetchOdhlasClena(aktivitaId, data.kod, idČlena);
    void dotáhniNastaveníTýmuProModal();
  };

  const nastavLimit = async (limit: number) => {
    if (!data?.kod) return;
    const result = await fetchNastavLimitTymu(aktivitaId, data.kod, limit);
    if (!result.úspěch) {
      setChyba(result.chyba?.hláška ?? "Nepodařilo se nastavit limit");
      return;
    }
    void dotáhniNastaveníTýmuProModal();
  };

  const predejKapitana = async (idNovehoKapitana: number) => {
    if (!data?.kod) return;
    const result = await fetchPredejKapitana(aktivitaId, data.kod, idNovehoKapitana);
    if (!result.úspěch) {
      setChyba(result.chyba?.hláška ?? "Nepodařilo se předat kapitána");
      return;
    }
    void dotáhniNastaveníTýmuProModal();
  };

  const založitTým = async () => {
    setChyba(null);
    if (data?.jeTrebaPredpripravit) {
      const result = await fetchZalozPrazdnyTym(aktivitaId);
      if (!result.úspěch) {
        setChyba(result.chyba?.hláška ?? "Nepodařilo se založit tým");
        return;
      }
      void dotáhniNastaveníTýmuProModal();
    } else {
      void proveďAkciAktivity(aktivitaId, "prihlasit").then(zavřít);
    }
  };

  const hotovoPripravaTymu = async (vybrane: Record<number, number>) => {
    if (!data?.kod) return;
    setChyba(null);
    const idVybranychAktivit = Object.values(vybrane);
    const result = await fetchPotvrdVyberAktivit(aktivitaId, data.kod, idVybranychAktivit);
    if (!result.úspěch) {
      setChyba(result.chyba?.hláška ?? "Nepodařilo se přihlásit tým na aktivity");
      return;
    }
    void dotáhniNastaveníTýmuProModal();
  };

  const odemkni = async () => {
    if (!data?.kod) return;
    setChyba(null);
    const result = await fetchOdemkniTym(aktivitaId, data.kod);
    if (!result.úspěch) {
      setChyba(result.chyba?.hláška ?? "Nepodařilo se odemknout tým");
      return;
    }
    void dotáhniNastaveníTýmuProModal();
  };

  const smazatTym = async () => {
    if (!data?.kod) return;
    setChyba(null);
    const result = await fetchSmazTym(aktivitaId, data.kod);
    if (!result.úspěch) {
      setChyba(result.chyba?.hláška ?? "Nepodařilo se smazat tým");
      return;
    }
    zavřít();
  };

  return (
    <NastaveniTymuView
      nazevAktivity={aktivita?.nazev ?? storeNazevAktivity}
      data={data ?? null}
      přihlášen={přihlášen}
      načítá={!aktivita && !data}
      načítáAkci={načítáAkci}
      chyba={chyba}
      onZavřít={zavřít}
      onZaložitTým={() => void sNačítáním(založitTým, true)()}
      onPřipojitSe={(idTýmu, kód) => void sNačítáním(() => proveďAkciAktivity(aktivitaId, "prihlasit", idTýmu, kód).then(zavřít), true)()}
      onPřepniVerejnost={() => void sNačítáním(přepniVerejnost, true)()}
      onOdhlásit={() => void proveďAkciAktivity(aktivitaId, "odhlasit")}
      onPregenerujKód={() => void sNačítáním(přegenerujKód, true)()}
      onOdhlásitČlena={(id) => void sNačítáním(() => odhlásitČlena(id), true)()}
      onPredejKapitana={(id) => void sNačítáním(() => predejKapitana(id), true)()}
      onNastavLimit={(limit) => void sNačítáním(() => nastavLimit(limit), true)()}
      onHotovoPripravaTymu={(vybrane) => void sNačítáním(() => hotovoPripravaTymu(vybrane), true)()}
      onSmazatTym={() => void sNačítáním(smazatTym, true)()}
      onOdemkni={jeSefInfa ? () => void sNačítáním(odemkni, true)() : undefined}
    />
  );
};
