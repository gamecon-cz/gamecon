import { FunctionComponent } from "preact";
import { useState } from "preact/hooks";
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
import { fetchNastavVerejnostTymu, fetchPregenerujKodTymu, fetchOdhlasClena, fetchZalozPrazdnyTym, fetchPotvrdVyberAktivit, fetchPredejKapitana, fetchNastavLimitTymu, AktivitaKVyberu } from "../../../../api/program";
import { NastaveniTymuView } from "../../../../components/NastaveniTymuView/NastaveniTymuView";

type VyberAktivitState = {
  kodTymu: number;
  aktivity: AktivitaKVyberu[];
  vybrane: Set<number>;
};


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
  const [vyberAktivit, setVyberAktivit] = useState<VyberAktivitState | null>(null);
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
    setVyberAktivit(null);
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
      if (!result.úspěch || !result.kodTymu) {
        setChyba(result.chyba?.hláška ?? "Nepodařilo se založit tým");
        return;
      }
      setVyberAktivit({
        kodTymu: result.kodTymu,
        aktivity: result.aktivityKVyberu ?? [],
        vybrane: new Set(),
      });
    } else {
      void proveďAkciAktivity(aktivitaId, "prihlasit").then(zavřít);
    }
  };

  const přepniVybranou = (idAktivity: number) => {
    if (!vyberAktivit) return;
    const vybrane = new Set(vyberAktivit.vybrane);
    if (vybrane.has(idAktivity)) {
      vybrane.delete(idAktivity);
    } else {
      vybrane.add(idAktivity);
    }
    setVyberAktivit({ ...vyberAktivit, vybrane });
  };

  const potvrdVyber = async () => {
    if (!vyberAktivit) return;
    setChyba(null);
    const result = await fetchPotvrdVyberAktivit(aktivitaId, vyberAktivit.kodTymu, [...vyberAktivit.vybrane]);
    if (!result.úspěch) {
      setChyba(result.chyba?.hláška ?? "Nepodařilo se přihlásit tým");
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
      vyberAktivit={vyberAktivit}
      onZavřít={zavřít}
      onZaložitTým={() => void sNačítáním(založitTým, true)()}
      onPřipojitSe={(idTýmu, kód) => void sNačítáním(() => proveďAkciAktivity(aktivitaId, "prihlasit", idTýmu, kód).then(zavřít), true)()}
      onPřepniVerejnost={() => void sNačítáním(přepniVerejnost, true)()}
      onOdhlásit={() => nastavModalOdhlásit(aktivitaId)}
      onPregenerujKód={() => void sNačítáním(přegenerujKód, true)()}
      onOdhlásitČlena={(id) => void sNačítáním(() => odhlásitČlena(id), true)()}
      onPredejKapitana={(id) => void sNačítáním(() => predejKapitana(id), true)()}
      onNastavLimit={(limit) => void sNačítáním(() => nastavLimit(limit), true)()}
      onPřepniVybranou={přepniVybranou}
      onPotvrdVyber={() => void sNačítáním(potvrdVyber, true)()}
    />
  );
};
