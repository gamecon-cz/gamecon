/**
 * Dvoustupňový flow přípravy nově vytvořeného týmu:
 * 1. Výběr aktivit (termínů) pro jednotlivá kola
 * 2. Přihlášení se jako kapitán
 *
 * Automatický odpočet 30 minut - pokud se nehotovo, tým se smaže.
 */
import { FunctionComponent } from "preact";
import { useState, useEffect } from "preact/hooks";
import { produce } from "immer";
import { GAMECON_KONSTANTY } from "../../env";
import { VyberKolAktivity, KoloAktivity } from "./VyberKolAktivity";
import { PrihlaseniKapitana } from "./PrihlaseniKapitana";

export type PripravaTymuState = "vyber-kol" | "prihlas-kapitana" | "hotovo";

type PripravaTymuProps = {
  casZalozeniMs: number;
  kola: KoloAktivity[];
  onHotovo: (vybranAktivity: Record<number, number>) => void;
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
  kola,
  onHotovo,
  onSmazat,
  nacita,
}) => {
  const [krok, setKrok] = useState<PripravaTymuState>("vyber-kol");
  const [vybrane, setVybrane] = useState<Record<number, number>>({});
  const [zbyvaCas, vyprselo] = useOdpočet(casZalozeniMs);

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

  const handleDalsi = () => {
    setKrok("prihlas-kapitana");
  };

  const handlePrihlasit = () => {
    onHotovo(vybrane);
    setKrok("hotovo");
  };

  const vybraneAktivityText = kola
    .map((k) => {
      const vybranaId = vybrane[k.cisloKola];
      const vybranaAktivita = k.aktivity.find((a) => a.id === vybranaId);
      return vybranaAktivita?.cas ?? "—";
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
            padding: "8px 16px",
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
        {krok === "vyber-kol" && (
          <VyberKolAktivity
            kola={kola}
            vybrane={vybrane}
            onVyber={handleVyber}
            onDalsi={handleDalsi}
            zbyvajiciCas={zbyvaCas}
            nacita={nacita}
          />
        )}

        {krok === "prihlas-kapitana" && (
          <PrihlaseniKapitana
            vybranAktivity={vybraneAktivityText}
            onPrihlasit={handlePrihlasit}
            zbyvajiciCas={zbyvaCas}
            nacita={nacita}
          />
        )}

        {krok === "hotovo" && (
          <div style={{ padding: "20px", textAlign: "center", color: "#4a4" }}>
            <div style={{ fontSize: "2em", marginBottom: "12px" }}>✓</div>
            <div>Tým je připraven!</div>
          </div>
        )}
      </div>
    </div>
  );
};
