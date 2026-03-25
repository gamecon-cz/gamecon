import { FunctionComponent } from "preact";
import { useState } from "preact/hooks";
import { NastaveniTymuData } from "../../store/program/slices/všeobecnéSlice";

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
  } = props;

  const [kódPřipojeníDoTýmu, setKódPřipojeníDoTýmu] = useState("");

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

          {načítá && <div>Načítám...</div>}

          {chyba && <div style={{ color: "red" }}>{chyba}</div>}

          {!načítá && (
            <div style={{ gap: "16px", display: "flex", flexDirection: "column", alignItems: "start" }}>
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

                  {data?.verejneTymy && data.verejneTymy.length > 0 && (
                    <div style={{ marginTop: "8px" }}>
                      <strong>Veřejné týmy:</strong>
                      <ul style={{ listStyle: "none", padding: 0, margin: "4px 0" }}>
                        {data.verejneTymy.map((tym) => {
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
                              <button
                                disabled={plny}
                                style={{ width: "unset" }}
                                onClick={() => onPřipojitSe(tym.kod)}
                              >
                                {plny ? "Plný" : "Připojit se"}
                              </button>
                            </li>
                          );
                        })}
                      </ul>
                    </div>
                  )}
                </>
              )}

              {přihlášen && (
                <>
                  <div style={{ fontSize: "1.3em" }}>kód týmu: {data?.kod}</div>
                  <label>
                    jméno týmu: <input>pojmenuj</input>
                  </label>
                  <button>Pojmenuj tým</button>

                  {data?.verejny !== undefined && (
                    <label style={{ display: "flex", alignItems: "center", gap: "8px", marginTop: "8px" }}>
                      <input
                        type="checkbox"
                        checked={data.verejny}
                        onChange={onPřepniVerejnost}
                      />
                      Veřejný tým (kdokoliv se může přihlásit bez kódu)
                    </label>
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
