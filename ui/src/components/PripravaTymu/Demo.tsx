import { FunctionComponent } from "preact";
import { useState } from "preact/hooks";
import { PripravaTymu, KoloAktivity } from "./index";

// todo(tym): smazat demo
/**
 * Demo komponenta pro vyvoj a testovani PripravaTymu.
 *
 * Spustime ji ve vyvoji na localhost, aby sme videli jak vypad UI
 * pred tím než ji integrujeme do skutečného modálního dialohu.
 */
export const PripravaTymuDemo: FunctionComponent = () => {
  const [log, setLog] = useState<string[]>([]);

  const addLog = (msg: string) => {
    setLog((prev) => [...prev, `[${new Date().toLocaleTimeString()}] ${msg}`]);
  };

  const demoKola: KoloAktivity[] = [
    {
      cisloKola: 1,
      aktivity: [
        { id: 101, nazev: "", cas: "čtvrtek 14:00–16:00" },
        { id: 102, nazev: "", cas: "čtvrtek 14:30–16:30" },
        { id: 103, nazev: "", cas: "čtvrtek 15:00–17:00" },
      ],
    },
    {
      cisloKola: 2,
      aktivity: [
        { id: 201, nazev: "", cas: "pátek 10:00–11:30" },
        { id: 202, nazev: "", cas: "pátek 10:00–12:00" },
      ],
    },
    {
      cisloKola: 3,
      aktivity: [
        { id: 301, nazev: "", cas: "sobota 16:00–18:30" },
      ],
    },
  ];

  return (
    <div style={{ padding: "20px", fontFamily: "sans-serif" }}>
      <h1>🧪 PripravaTymu Demo</h1>

      <div style={{ display: "grid", gridTemplateColumns: "1fr 400px", gap: "20px" }}>
        {/* Hlavní komponenta */}
        <div
          style={{
            border: "1px solid #ccc",
            borderRadius: "8px",
            padding: "20px",
            backgroundColor: "#fafafa",
          }}
        >
          <PripravaTymu
            casZalozeniMs={Date.now()}
            kola={demoKola}
            onHotovo={(vybrane) => {
              const msg = `Hotovo! Vybrané aktivity: ${Array.from(vybrane.entries())
                .map(([k, v]) => `Kolo ${k}→${v}`)
                .join(", ")}`;
              addLog(msg);
              console.log("Vybrane:", vybrane);
            }}
            onSmazat={() => {
              addLog("Tým byl smazán!");
              console.log("Smazat tým!");
            }}
          />
        </div>

        {/* Log */}
        <div
          style={{
            border: "1px solid #ccc",
            borderRadius: "8px",
            padding: "12px",
            backgroundColor: "#f5f5f5",
            maxHeight: "600px",
            overflowY: "auto",
            fontFamily: "monospace",
            fontSize: "0.8em",
          }}
        >
          <h3 style={{ margin: "0 0 12px 0" }}>Log</h3>
          {log.length === 0 ? (
            <div style={{ color: "#888" }}>Čekám na akce...</div>
          ) : (
            <ul style={{ listStyle: "none", padding: 0, margin: 0 }}>
              {log.map((entry, i) => (
                <li key={i} style={{ margin: "4px 0", color: "#333" }}>
                  {entry}
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      {/* Instrukce */}
      <div
        style={{
          marginTop: "20px",
          padding: "16px",
          backgroundColor: "#e8f5e9",
          borderRadius: "8px",
          border: "1px solid #4a4",
        }}
      >
        <h3 style={{ margin: "0 0 12px 0" }}>📝 Jak to funguje:</h3>
        <ol style={{ margin: 0, paddingLeft: "20px" }}>
          <li>Vyber aktivity pro <strong>všechna 3 kola</strong> (radiobuttony)</li>
          <li>Klikni <strong>Pokračovat →</strong></li>
          <li>Ověř vybrané aktivity a klikni <strong>✓ Přihlásit se jako kapitán</strong></li>
          <li>
            Uvidíš potvrzení <strong>Tým je připraven!</strong>
          </li>
          <li>Můžeš se vrátit zpět kliknutím na <strong>← Zpět</strong></li>
          <li>Kdykoli můžeš <strong>Smazat tým</strong> (červené tlačítko)</li>
        </ol>
      </div>

      {/* Info o datech */}
      <div
        style={{
          marginTop: "20px",
          padding: "16px",
          backgroundColor: "#e3f2fd",
          borderRadius: "8px",
          border: "1px solid #4a4",
        }}
      >
        <h3 style={{ margin: "0 0 12px 0" }}>ℹ️ Demo data:</h3>
        <pre
          style={{
            backgroundColor: "white",
            padding: "12px",
            borderRadius: "4px",
            overflowX: "auto",
            fontSize: "0.8em",
            margin: 0,
          }}
        >
          {JSON.stringify(demoKola, null, 2)}
        </pre>
      </div>

      {/* Timer info */}
      <div
        style={{
          marginTop: "20px",
          padding: "16px",
          backgroundColor: "#fff3e0",
          borderRadius: "8px",
          border: "1px solid #b80",
        }}
      >
        <h3 style={{ margin: "0 0 12px 0" }}>⏱️ Odpočet:</h3>
        <p style={{ margin: "0 0 8px 0" }}>
          <strong>Čas na přípravu:</strong> 30 minut od vytvoření týmu
        </p>
        <p style={{ margin: "0 0 8px 0" }}>
          <strong>Aktuální čas založení:</strong> {new Date().toLocaleTimeString()}
        </p>
        <p style={{ margin: 0, color: "#666", fontSize: "0.9em" }}>
          (V dev módu se odpočet běží normálně. V produkci by se tým po 30 min. smazal na backendu.)
        </p>
      </div>
    </div>
  );
};

export default PripravaTymuDemo;
