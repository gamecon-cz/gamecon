import { FunctionComponent } from "preact";
import { useEffect, useState } from "preact/hooks";
import { fetchAktivitaTým, fetchAktivitaAkce, fetchNastavVerejnostTymu, fetchPregenerujKodTymu, fetchOdhlasClena } from "../../api/program";
import { NastaveniTymuData } from "../../store/program/slices/všeobecnéSlice";
import { NastaveniTymuView } from "../../components/NastaveniTymuView/NastaveniTymuView";

export const PrihlaseniTymuWidget: FunctionComponent = () => {
  const [aktivitaId, setAktivitaId] = useState<number | null>(null);
  const [nazevAktivity, setNazevAktivity] = useState<string>("");
  const [data, setData] = useState<NastaveniTymuData | null>(null);
  const [načítá, setNačítá] = useState(false);
  const [chyba, setChyba] = useState<string | null>(null);

  useEffect(() => {
    window.preactMost.prihlaseniTymu.otevri = (id, nazev = "") => {
      setAktivitaId(id);
      setNazevAktivity(nazev);
      setData(null);
      setChyba(null);
    };
    return () => {
      window.preactMost.prihlaseniTymu.otevri = undefined;
    };
  }, []);

  useEffect(() => {
    if (!aktivitaId) return;
    setNačítá(true);
    fetchAktivitaTým(aktivitaId)
      .then((response) => setData({
        kod: response.kod,
        nazev: "",
        muzeZalozitNovy: true,
        jeKapitan: response.jeKapitan,
        verejny: response.verejny,
        casText: response.casText,
        clenove: response.clenove,
        vsechnyTymy: response.vsechnyTymy,
      }))
      .finally(() => setNačítá(false));
  }, [aktivitaId]);

  if (!aktivitaId) return <></>;

  const zavřít = () => {
    setAktivitaId(null);
    setData(null);
    setChyba(null);
  };

  const přihlásit = async (kód?: number) => {
    if (!aktivitaId) return;
    setChyba(null);
    const { chyba: chybaPřihlášení } = await fetchAktivitaAkce(aktivitaId, "prihlasit", kód);
    if (chybaPřihlášení?.hláška) {
      setChyba(chybaPřihlášení.hláška);
    } else {
      zavřít();
    }
  };

  const přepniVerejnost = async () => {
    if (!data || data.verejny === undefined || !data.kod || !aktivitaId) return;
    await fetchNastavVerejnostTymu(aktivitaId, data.kod, !data.verejny);
    const response = await fetchAktivitaTým(aktivitaId);
    setData((prev) => prev && { ...prev, verejny: response.verejny, vsechnyTymy: response.vsechnyTymy });
  };

  const načtiZnovu = async () => {
    if (!aktivitaId) return;
    const response = await fetchAktivitaTým(aktivitaId);
    setData({
      kod: response.kod,
      nazev: "",
      muzeZalozitNovy: true,
      jeKapitan: response.jeKapitan,
      verejny: response.verejny,
      casText: response.casText,
      clenove: response.clenove,
      vsechnyTymy: response.vsechnyTymy,
    });
  };

  const přegenerujKód = async () => {
    if (!data?.kod || !aktivitaId) return;
    await fetchPregenerujKodTymu(aktivitaId, data.kod);
    await načtiZnovu();
  };

  const odhlásitČlena = async (idČlena: number) => {
    if (!data?.kod || !aktivitaId) return;
    await fetchOdhlasClena(aktivitaId, data.kod, idČlena);
    await načtiZnovu();
  };

  return (
    <NastaveniTymuView
      nazevAktivity={nazevAktivity}
      data={data}
      přihlášen={data !== null && data.kod > 0}
      načítá={načítá}
      chyba={chyba}
      onZavřít={zavřít}
      onZaložitTým={() => void přihlásit()}
      onPřipojitSe={(kód) => void přihlásit(kód)}
      onPřepniVerejnost={() => void přepniVerejnost()}
      onPregenerujKód={() => void přegenerujKód()}
      onOdhlásitČlena={(id) => void odhlásitČlena(id)}
    />
  );
};
