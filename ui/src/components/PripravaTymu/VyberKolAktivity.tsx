/**
 * Krok 1: Výběr termínu pro jednotlivá kola.
 * Radiobuttony - vždy jen jeden termín na kolo.
 * Pokud má kolo jen jednu možnost, je automaticky předvybrána.
 */
import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { UpozorneniOdpocet } from "./UpozorneniOdpocet";

export type KoloAktivity = {
  cisloKola: number;
  aktivity: {
    id: number;
    nazev: string;
    cas: string;
  }[];
};

type VyberKolAktivityProps = {
  kola: KoloAktivity[];
  vybrane: Record<number, number>; // cisloKola -> id vybrané aktivity
  onVyber: (cisloKola: number, idAktivity: number) => void;
  onDalsi: () => void;
  zbyvajiciCas: string;
  nacita?: boolean;
};

export const VyberKolAktivity: FunctionComponent<VyberKolAktivityProps> = ({
  kola,
  vybrane,
  onVyber,
  onDalsi,
  zbyvajiciCas,
  nacita,
}) => {
  // Automaticky vybrat jedinou aktivitu v kole
  useEffect(() => {
    kola.forEach((kolo) => {
      if (kolo.aktivity.length === 1 && vybrane[kolo.cisloKola] === undefined) {
        onVyber(kolo.cisloKola, kolo.aktivity[0].id);
      }
    });
  }, [kola]);
  const vsechnaVybrana = kola.length > 0 && kola.every((k) => vybrane[k.cisloKola] !== undefined);

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
      <UpozorneniOdpocet zbyvajiciCas={zbyvajiciCas} />

      {/* Výběr aktivit pro jednotlivá kola */}
      <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
        {kola.map((kolo) => (
          <div key={kolo.cisloKola}>
            <div style={{ fontSize: "0.9em", color: "#666", marginBottom: "10px", fontWeight: "600" }}>
              KOLO {kolo.cisloKola}
            </div>

            {kolo.aktivity.length === 0 ? (
              <div style={{ color: "#888", fontStyle: "italic", padding: "8px" }}>
                — žádné termíny —
              </div>
            ) : (
              <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                {kolo.aktivity.map((aktivita) => {
                  const isSelected = vybrane[kolo.cisloKola] === aktivita.id;
                  return (
                    <label
                      key={aktivita.id}
                      style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "10px",
                        cursor: "pointer",
                        padding: "12px",
                        borderRadius: "6px",
                        border: isSelected ? "2px solid #4a9" : "2px solid #ddd",
                        backgroundColor: isSelected ? "#f0fdf4" : "#fafafa",
                        transition: "all 0.2s",
                        fontWeight: isSelected ? "600" : "400",
                      }}
                    >
                      <input
                        type="radio"
                        name={`kolo-${kolo.cisloKola}`}
                        checked={isSelected}
                        onChange={() => onVyber(kolo.cisloKola, aktivita.id)}
                        disabled={nacita}
                        style={{ cursor: "pointer", width: "18px", height: "18px" }}
                      />
                      <div style={{ fontSize: "1em", color: isSelected ? "#2d5f2e" : "#333" }}>
                        {aktivita.cas}
                      </div>
                    </label>
                  );
                })}
              </div>
            )}
          </div>
        ))}
      </div>

      <button
        onClick={onDalsi}
        disabled={!vsechnaVybrana || nacita}
        style={{
          width: "100%",
          padding: "12px 24px",
          fontWeight: "bold",
          fontSize: "1em",
        }}
      >
        {nacita ? "Zpracovávám..." : "Pokračovat →"}
      </button>
    </div>
  );
};
