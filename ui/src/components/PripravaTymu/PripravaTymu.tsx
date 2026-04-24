/**
 * Dvoustupňový flow přípravy nově vytvořeného týmu:
 * 1. Výběr aktivit (termínů) pro jednotlivá kola
 * 2. Přihlášení se jako kapitán
 *
 * Automatický odpočet 30 minut - pokud se nehotovo, tým se smaže.
 */
import { FunctionComponent } from "preact";
import { useState, useEffect, useMemo } from "preact/hooks";
import { produce } from "immer";
import { GAMECON_KONSTANTY } from "../../env";
import { VyberKolAktivity, KoloAktivity } from "./VyberKolAktivity";
import { PrihlaseniKapitana } from "./PrihlaseniKapitana";
import { useAktivity, useNastaveniTymuModalAktivitaId, useNastaveniTymuModalData } from "../../store/program/selektory";
import { denAktivity } from "../../store/program/logic/aktivity";
import { Aktivita } from "../../store/program/slices/programDataSlice";

type PripravaTymuProps = {
  casZalozeniMs: number;
  onVybranéAktivity: (vybranAktivity: number[]) => void;
  onPrihlasitKapitana: () => void;
  onSmazat: () => void;
  nacita?: boolean;
};

const formatZbyvaCas = (ms: number): string => {
  if (ms <= 0) return "0:00";
  const h = Math.floor(ms / 3600000);
  const m = Math.floor((ms % 3600000) / 60000);
  const s = Math.floor((ms % 60000) / 1000);
  return `${h}:${m.toString().padStart(2, "0")}:${s.toString().padStart(2, "0")}`;
};

const useOdpočet = (casZalozeniMs: number): [string, boolean] => {
  const [zbyvaCas, setZbyvaCas] = useState<string>("");
  const [vyprselo, setVyprselo] = useState(false);

  useEffect(() => {
    const pripraveniMs = casZalozeniMs + (GAMECON_KONSTANTY.CAS_NA_PRIPRAVENI_TYMU_MINUT ?? 30) * 60 * 1000;
    const update = () => {
      const nyni = Date.now();
      const zbyva = Math.max(0, pripraveniMs - nyni);
      setZbyvaCas(formatZbyvaCas(zbyva));
      if (zbyva === 0) {
        setVyprselo(true);
      }
    };

    update();
    const id = setInterval(update, 1000);
    return () => clearInterval(id);
  }, [casZalozeniMs]);

  return [zbyvaCas, vyprselo];
};

export const PripravaTymu: FunctionComponent<PripravaTymuProps> = ({
  casZalozeniMs,
  onVybranéAktivity,
  onPrihlasitKapitana,
  onSmazat,
  nacita,
}) => {
  const [vybrane, setVybrane] = useState<Record<number, number>>({});
  const [zbyvaCas, vyprselo] = useOdpočet(casZalozeniMs);

  const aktivitaId = useNastaveniTymuModalAktivitaId()!;
  const dataTymu = useNastaveniTymuModalData()!;
  const vsechnyAktivity = useAktivity();
  const idTurnaje = vsechnyAktivity.find(x=>x.id === aktivitaId)?.turnajId;
  if (!idTurnaje) {
    throw new Error("Nenalezen id turnaje");
  }

  const ziskejDenCasAktivity = (a: Aktivita): string => {
    const den = denAktivity(new Date(a.cas.od))?.toLocaleDateString("cs-CZ", {
      weekday: "short"
    }) ?? "";
    // todo: casText by bylo lepší vůbec nepoužívat kvůli speciálním symbolům se musí pak dangerouslySetInnerHTML
    return den + " " + a.casText;
  }

  const kola = useMemo<KoloAktivity[]>(() => {
    const aktivityTurnaje = vsechnyAktivity.filter(x=>x.turnajId === idTurnaje);
    const mapa = new Map<number, KoloAktivity>();
    for (const a of aktivityTurnaje) {
      const cisloKola = a.turnajKolo ?? 1;
      if (!mapa.has(cisloKola)) mapa.set(cisloKola, { cisloKola, aktivity: [] });


      mapa.get(cisloKola)!.aktivity.push({ id: a.id, nazev: a.nazev, cas: ziskejDenCasAktivity(a) });
    }
    return [...mapa.values()].sort((a, b) => a.cisloKola - b.cisloKola);
  }, [vsechnyAktivity, aktivitaId]);

  const aktivityTymuId = dataTymu.aktivityTymuId ?? [];
  const aktivityTymu = aktivityTymuId.map(id=>vsechnyAktivity.find(a=>a.id===id)).filter(x=>x !== undefined);
  const aktivityProVšechnyKolaVybrané = kola
    .every(kolo=>kolo.aktivity.some(a => aktivityTymuId.includes(a.id)))
    ;
  const jeVýběrKol = !aktivityProVšechnyKolaVybrané;

  // Pokud vypršel čas, označíme to
  useEffect(() => {
    if (vyprselo) {
      // Zde by měla být logika na smazání týmu, ale to je na backendu
      // Komponenta jenom zobrazuje urgenci
    }
  }, [vyprselo]);

  const handleVyber = (cisloKola: number, idAktivity: number) => {
    setVybrane(
      produce((draft) => {
        draft[cisloKola] = idAktivity;
      })
    );
  };

  const onPotvrdit = () => {
    onVybranéAktivity(Object.values(vybrane));
  };

  const handlePrihlasit = () => {
    onPrihlasitKapitana();
  };

  const vybraneAktivityText = kola
    .map((k) => {
      const aktivitaKola = aktivityTymu
        .find(aktivita => k.aktivity.some(a=>a.id === aktivita.id))
      return "kolo " + k.cisloKola.toString(10) + ". "+ (aktivitaKola ? ziskejDenCasAktivity(aktivitaKola) : "");
    })
    .join("\n");

  return (
    <div>
      {/* Header se smazáním */}
      <div
        style={{
          display: "flex",
          justifyContent: "flex-end",
          marginBottom: "16px",
          paddingBottom: "12px",
          borderBottom: "1px solid #ddd",
        }}
      >
        <button
          onClick={onSmazat}
          style={{
            backgroundColor: "#f44",
            color: "white",
            border: "none",
            borderRadius: "4px",
            cursor: "pointer",
            fontWeight: "bold",
          }}
          disabled={nacita}
        >
          🗑️ Smazat tým
        </button>
      </div>

      <div>
        {jeVýběrKol ? (
          <VyberKolAktivity
            kola={kola}
            vybrane={vybrane}
            onVyber={handleVyber}
            onPotvrdit={onPotvrdit}
            zbyvajiciCas={zbyvaCas}
            nacita={nacita}
          />
        ) : (
          <PrihlaseniKapitana
            vybranéAktivity={vybraneAktivityText}
            onPrihlasit={handlePrihlasit}
            zbyvajiciCas={zbyvaCas}
            nacita={nacita}
          />
        )}
      </div>
    </div>
  );
};
