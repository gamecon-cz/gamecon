import { FunctionComponent } from "preact";
import { useState } from "preact/hooks";
import { NastaveniTymuData } from "../../store/program/slices/všeobecnéSlice";
import { TymVSeznamu } from "../../api/program";

type NastaveniTymuViewProps = {
  nazevAktivity?: string;
  data: NastaveniTymuData | null;
  přihlášen: boolean;
  načítá?: boolean;
  chyba?: string | null;
  onZavřít: () => void;
  onZaložitTým: () => void;
  onPřipojitSe: (kód: number) => void;
  onPřepniVerejnost: () => void;
  onOdhlásit?: () => void;
  onPregenerujKód?: () => void;
  onOdhlásitČlena?: (idČlena: number) => void;
};

// todo(tym): dodělat potvrzovaci modaly
// todo(tym): manuální předání kapitána
// todo(tym): loading animace
const SeznamTymu: FunctionComponent<{
  tymy: TymVSeznamu[];
  zobrazitPřipojení: boolean;
  onPřipojitSe: (kód: number) => void;
}> = ({ tymy, zobrazitPřipojení, onPřipojitSe }) => {
  if (tymy.length === 0) return <div style={{ color: "#888" }}>Zatím žádné týmy</div>;

  return (
    <ul style={{ listStyle: "none", padding: 0, margin: "4px 0" }}>
      {tymy.map((tym) => {
        const plny = tym.limit !== null && tym.pocetClenu >= tym.limit;
        return (
          <li
            key={tym.kod}
            style={{ display: "flex", alignItems: "center", gap: "8px", padding: "4px 0" }}
          >
            <span>{tym.nazev || `Tým ${tym.kod}`}</span>
            <span style={{ color: "#888" }}>
              {tym.pocetClenu}{tym.limit !== null ? `/${tym.limit}` : ""}
            </span>
            {tym.verejny
              ? <span style={{ color: "#4a4", fontSize: "0.85em" }}>veřejný</span>
              : <span style={{ color: "#888", fontSize: "0.85em" }}>soukromý</span>
            }
            {zobrazitPřipojení && tym.verejny && (
              <button
                disabled={plny}
                style={{ width: "unset" }}
                onClick={() => onPřipojitSe(tym.kod)}
              >
                {plny ? "Plný" : "Připojit se"}
              </button>
            )}
          </li>
        );
      })}
    </ul>
  );
};

export const NastaveniTymuView: FunctionComponent<NastaveniTymuViewProps> = (props) => {
  const {
    nazevAktivity,
    data,
    přihlášen,
    načítá,
    chyba,
    onZavřít,
    onZaložitTým,
    onPřipojitSe,
    onPřepniVerejnost,
    onOdhlásit,
    onPregenerujKód,
    onOdhlásitČlena,
  } = props;

  const [kódPřipojeníDoTýmu, setKódPřipojeníDoTýmu] = useState("");
  const [zkopírováno, setZkopírováno] = useState(false);

  const zkopírujKód = (kód: number) => {
    void navigator.clipboard.writeText(String(kód)).then(() => {
      setZkopírováno(true);
      setTimeout(() => setZkopírováno(false), 1500);
    });
  };

  const jeKapitán = přihlášen && data?.jeKapitan;

  return (
    <>
      <div
        className="modal_obal"
        onClick={(e) => {
          if (e.target === e.currentTarget) onZavřít();
        }}
      >
        <div className="modal clearfix">
          <div className="clearfix">
            <h3 style={{ float: "left" }}>
              Nastavení týmu{nazevAktivity ? ` aktivity ${nazevAktivity}` : ""}
            </h3>
            {přihlášen && onOdhlásit && (
              <button class="vpravo" onClick={onOdhlásit}>Odhlásit!</button>
            )}
          </div>

          {data?.casText && (
            <div style={{ color: "#666", marginBottom: "8px" }}>{data.casText}</div>
          )}

          {načítá && <div>Načítám...</div>}

          {chyba && <div style={{ color: "red" }}>{chyba}</div>}

          {!načítá && (
            <div style={{ gap: "16px", display: "flex", flexDirection: "column", alignItems: "start" }}>

              {/* === Nepřihlášený === */}
              {!přihlášen && (
                <>
                  <button onClick={onZaložitTým}>Založ tým</button>
                  <div style={{ gap: "4px", display: "flex", flexDirection: "column", alignItems: "start" }}>
                    <label>
                      kód:
                      <input
                        placeholder="XXXX"
                        onChange={(x) => setKódPřipojeníDoTýmu(x.currentTarget.value)}
                        value={kódPřipojeníDoTýmu}
                      />
                    </label>
                    <button
                      style={{ width: "unset" }}
                      onClick={() => onPřipojitSe(+kódPřipojeníDoTýmu)}
                    >
                      Připoj se do týmu
                    </button>
                  </div>

                  {data?.vsechnyTymy && (
                    <div style={{ marginTop: "8px", width: "100%" }}>
                      <strong>Týmy:</strong>
                      <SeznamTymu
                        tymy={data.vsechnyTymy}
                        zobrazitPřipojení={true}
                        onPřipojitSe={onPřipojitSe}
                      />
                    </div>
                  )}
                </>
              )}

              {/* === Přihlášený (kapitán i člen) === */}
              {přihlášen && (
                <>
                  {/* Kód týmu — všichni přihlášení */}
                  {data?.kod && (
                    <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                      <span style={{ fontSize: "1.3em" }}>
                        kód týmu:{" "}
                        <span
                          title="Klikni pro zkopírování"
                          style={{ cursor: "pointer", textDecoration: "underline dotted" }}
                          onClick={() => zkopírujKód(data.kod)}
                        >
                          {data.kod}
                        </span>
                        {zkopírováno && <span style={{ color: "#4a4", marginLeft: "6px", fontSize: "0.8em" }}>zkopírováno!</span>}
                      </span>
                      {jeKapitán && onPregenerujKód && (
                        <button style={{ width: "unset" }} onClick={onPregenerujKód}>
                          Přegenerovat kód
                        </button>
                      )}
                    </div>
                  )}

                  {/* Veřejnost — jen kapitán */}
                  {jeKapitán && data?.verejny !== undefined && (
                    <label style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                      <input
                        type="checkbox"
                        checked={data.verejny}
                        onChange={onPřepniVerejnost}
                      />
                      Veřejný tým (kdokoliv se může přihlásit bez kódu)
                    </label>
                  )}

                  {/* Seznam členů */}
                  {data?.clenove && data.clenove.length > 0 && (
                    <div style={{ width: "100%" }}>
                      <strong>Členové týmu:</strong>
                      <ul style={{ listStyle: "none", padding: 0, margin: "4px 0" }}>
                        {data.clenove.map((clen) => (
                          <li
                            key={clen.id}
                            style={{ display: "flex", alignItems: "center", gap: "8px", padding: "4px 0" }}
                          >
                            <span>
                              {clen.jmeno}
                              {clen.jeKapitan && <span style={{ color: "#888", marginLeft: "4px" }}>(kapitán)</span>}
                            </span>
                            {jeKapitán && !clen.jeKapitan && onOdhlásitČlena && (
                              <button
                                style={{ width: "unset", padding: "2px 8px" }}
                                onClick={() => onOdhlásitČlena(clen.id)}
                              >
                                Odebrat
                              </button>
                            )}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}

                  {/* Ostatní týmy (bez přihlašování) */}
                  {data?.vsechnyTymy && data.vsechnyTymy.length > 0 && (
                    <div style={{ marginTop: "8px", width: "100%" }}>
                      <strong>Všechny týmy:</strong>
                      <SeznamTymu
                        tymy={data.vsechnyTymy}
                        zobrazitPřipojení={false}
                        onPřipojitSe={onPřipojitSe}
                      />
                    </div>
                  )}
                </>
              )}
            </div>
          )}

          <button class="vpravo zpet" onClick={onZavřít}>Zavřít</button>
        </div>
      </div>
    </>
  );
};
