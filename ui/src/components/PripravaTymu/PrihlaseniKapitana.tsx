/**
 * Krok 2: Přihlášení se jako kapitán.
 * Zobrazí potvrzení vybraných termínů.
 * Po kliknutí bude tým aktivní.
 */
import { FunctionComponent } from "preact";
import { UpozorneniOdpocet } from "./UpozorneniOdpocet";

type AktivitaSPrekryvem = {
  id: number;
  nazev: string;
  cas: string;
};

type PrihlaseniKapitanaProps = {
  vybranéAktivity: string;
  překrývajícíSeAktivity: AktivitaSPrekryvem[];
  onPrihlasit: () => void;
  onOdhlasitAktivitu: (aktivitaId: number) => void;
  zbyvajiciCas: string;
  nacita?: boolean;
};

export const PrihlaseniKapitana: FunctionComponent<PrihlaseniKapitanaProps> = ({
  vybranéAktivity,
  překrývajícíSeAktivity,
  onPrihlasit,
  onOdhlasitAktivitu,
  zbyvajiciCas,
  nacita,
}) => {
  const máPřekryv = překrývajícíSeAktivity.length > 0;

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

      {máPřekryv && (
        <div style={{ backgroundColor: "#fff3e0", border: "1px solid #e65100", padding: "12px", borderRadius: "4px", fontSize: "0.9em" }}>
          <strong style={{ color: "#e65100" }}>Pro přihlášení jako kapitán se musíte odhlásit z těchto aktivit:</strong>
          <div style={{ marginTop: "8px", display: "flex", flexDirection: "column", gap: "6px" }}>
            {překrývajícíSeAktivity.map(a => (
              <div key={a.id} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: "8px" }}>
                <span style={{ color: "#555" }}>{a.nazev} – <span style={{ fontFamily: "monospace" }} dangerouslySetInnerHTML={{__html:a.cas}} /></span>
                <button
                  onClick={() => onOdhlasitAktivitu(a.id)}
                  disabled={nacita}
                  style={{
                    padding: "2px 10px",
                    fontSize: "0.85em",
                    backgroundColor: "#e65100",
                    color: "white",
                    border: "none",
                    borderRadius: "4px",
                    cursor: nacita ? "not-allowed" : "pointer",
                    opacity: nacita ? 0.6 : 1,
                    whiteSpace: "nowrap",
                  }}
                >
                  Odhlásit
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Hlavní akce - přihlášení */}
      <div
        style={{
          backgroundColor: máPřekryv ? "#f5f5f5" : "#e8f5e9",
          border: `2px solid ${máPřekryv ? "#bbb" : "#4a4"}`,
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
          disabled={nacita || máPřekryv}
          style={{
            width: "100%",
            padding: "0",
            fontSize: "1.1em",
            fontWeight: "bold",
            backgroundColor: máPřekryv ? "#bbb" : "#4a4",
            color: "white",
            border: "none",
            borderRadius: "4px",
            cursor: (nacita || máPřekryv) ? "not-allowed" : "pointer",
            opacity: (nacita || máPřekryv) ? 0.6 : 1,
            marginBottom: "1em",
          }}
        >
          {nacita ? "Přihlašuji..." : "✓ Přihlásit se jako kapitán"}
        </button>
      </div>

    </div>
  );
};
