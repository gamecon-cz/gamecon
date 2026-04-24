/**
 * Krok 2: Přihlášení se jako kapitán.
 * Zobrazí potvrzení vybraných termínů.
 * Po kliknutí bude tým aktivní.
 */
import { FunctionComponent } from "preact";
import { UpozorneniOdpocet } from "./UpozorneniOdpocet";

type PrihlaseniKapitanaProps = {
  vybranéAktivity: string;
  onPrihlasit: () => void;
  zbyvajiciCas: string;
  nacita?: boolean;
};

export const PrihlaseniKapitana: FunctionComponent<PrihlaseniKapitanaProps> = ({
  vybranéAktivity,
  onPrihlasit,
  zbyvajiciCas,
  nacita,
}) => {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
      <UpozorneniOdpocet
        zbyvajiciCas={zbyvajiciCas}
        podtexty="Přihlaste se jako kapitán nebo tým bude automaticky smazán"
      />

      {vybranéAktivity && (
        <div style={{ backgroundColor: "#f5f5f5", padding: "12px", borderRadius: "4px", fontSize: "0.9em" }}>
          <strong>Vybrané termíny:</strong>
          <div style={{ marginTop: "8px", whiteSpace: "pre-wrap", fontFamily: "monospace", color: "#555" }}
            dangerouslySetInnerHTML={{__html: vybranéAktivity}}
          />
        </div>
      )}

      {/* Hlavní akce - přihlášení */}
      <div
        style={{
          backgroundColor: "#e8f5e9",
          border: "2px solid #4a4",
          padding: "16px",
          borderRadius: "4px",
          textAlign: "center",
        }}
      >
        <div style={{ marginBottom: "12px", color: "#333", fontSize: "0.95em" }}>
          Kliknutím níže se přihlásíte jako kapitán a tým bude připraven
        </div>
        <button
          onClick={onPrihlasit}
          disabled={nacita}
          style={{
            width: "100%",
            padding: "0",
            fontSize: "1.1em",
            fontWeight: "bold",
            backgroundColor: "#4a4",
            color: "white",
            border: "none",
            borderRadius: "4px",
            cursor: nacita ? "not-allowed" : "pointer",
            opacity: nacita ? 0.6 : 1,
            marginBottom: "1em",
          }}
        >
          {nacita ? "Přihlašuji..." : "✓ Přihlásit se jako kapitán"}
        </button>
      </div>

    </div>
  );
};
