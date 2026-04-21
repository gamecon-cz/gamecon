import { FunctionComponent } from "preact";
import { useState, useEffect } from "preact/hooks";
import { GAMECON_KONSTANTY } from "../../env";
import { NastaveniTymuData } from "../../store/program/slices/všeobecnéSlice";
import { AktivitaKVyberu, TymVSeznamu } from "../../api/program";
import { PripravaTymuDemo } from "../PripravaTymu/Demo";

type VyberAktivitState = {
  kodTymu: number;
  aktivity: AktivitaKVyberu[];
  vybrane: Set<number>;
};

type NastaveniTymuViewProps = {
  nazevAktivity?: string;
  data: NastaveniTymuData | null;
  přihlášen: boolean;
  načítá?: boolean;
  načítáAkci?: boolean;
  chyba?: string | null;
  vyberAktivit?: VyberAktivitState | null;
  onZavřít: () => void;
  onZaložitTým: () => void;
  onPřipojitSe: (idTýmu?: number, kód?: number) => void;
  onPřepniVerejnost: () => void;
  onOdhlásit?: () => void;
  onPregenerujKód?: () => void;
  onOdhlásitČlena?: (idČlena: number) => void;
  onPredejKapitana?: (idČlena: number) => void;
  onNastavLimit?: (limit: number) => void;
  onPřepniVybranou?: (idAktivity: number) => void;
  onPotvrdVyber?: () => void;
};

const SeznamTymu: FunctionComponent<{
  tymy: TymVSeznamu[];
  zobrazitPřipojení: boolean;
  onPřipojitSe: (idTýmu?: number, kód?: number) => void;
}> = ({ tymy, zobrazitPřipojení, onPřipojitSe }) => {
  if (tymy.length === 0) return <div style={{ color: "#888" }}>Zatím žádné týmy</div>;

  return (
    <ul style={{ listStyle: "none", padding: 0, margin: "4px 0" }}>
      {tymy.map((tym) => {
        const plny = tym.limit !== null && tym.pocetClenu >= (tym.limit ?? 0);
        return (
          <li
            key={tym.id}
            style={{ display: "flex", alignItems: "center", gap: "8px", padding: "4px 0" }}
          >
            <span>{tym.nazev || `Tým ${tym.id}`}</span>
            <span style={{ color: "#888" }}>
              {tym.pocetClenu}{tym.limit !== null ? `/${(tym.limit ?? "")}` : ""}
            </span>
            {tym.verejny
              ? <span style={{ color: "#4a4", fontSize: "0.85em" }}>veřejný</span>
              : <span style={{ color: "#888", fontSize: "0.85em" }}>soukromý</span>
            }
            {zobrazitPřipojení && tym.verejny && (
              <button
                disabled={plny}
                style={{ width: "unset" }}
                onClick={() => onPřipojitSe(tym.id)}
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

const formatZbývá = (ms: number): string => {
  if (ms <= 0) return "0:00";
  const h = Math.floor(ms / 3600000);
  const m = Math.floor((ms % 3600000) / 60000);
  return `${h}:${m.toString().padStart(2, "0")}`;
};

const useOdpočet = (casZalozeniMs: number | undefined): number | null => {
  const [zbývá, setZbývá] = useState<number | null>(null);

  useEffect(() => {
    if (!casZalozeniMs) return;
    const zamceniMs = casZalozeniMs + GAMECON_KONSTANTY.HAJENI_TEAMU_HODIN * 3600 * 1000;
    const update = () => setZbývá(Math.max(0, zamceniMs - Date.now()));
    update();
    const id = setInterval(update, 60000);
    return () => clearInterval(id);
  }, [casZalozeniMs]);

  return zbývá;
};

export const NastaveniTymuView: FunctionComponent<NastaveniTymuViewProps> = (props) => {
  const {
    nazevAktivity,
    data,
    přihlášen,
    načítá,
    načítáAkci,
    chyba,
    vyberAktivit,
    onZavřít,
    onZaložitTým,
    onPřipojitSe,
    onPřepniVerejnost,
    onOdhlásit,
    onPregenerujKód,
    onOdhlásitČlena,
    onPredejKapitana,
    onNastavLimit,
    onPřepniVybranou,
    onPotvrdVyber,
  } = props;

  const [kódPřipojeníDoTýmu, setKódPřipojeníDoTýmu] = useState("");
  const [zkopírováno, setZkopírováno] = useState(false);
  const [potvrzení, setPotvrzení] = useState<{ text: string; akce: () => void } | null>(null);
  // todo(tym): smazat demo
  const [ukažDemo, setUkažDemo] = useState(false);

  const zkopírujKód = (kód: number) => {
    void navigator.clipboard.writeText(String(kód)).then(() => {
      setZkopírováno(true);
      setTimeout(() => setZkopírováno(false), 1500);
    });
  };

  const sPotvrzením = (text: string, akce: () => void) => () => setPotvrzení({ text, akce });

  const jeKapitán = přihlášen && data?.jeKapitan;
  const pocetClenu = data?.clenove?.length ?? 0;
  const minKapacita = data?.minKapacita ?? 0;
  const maxKapacita = data?.maxKapacita ?? null;
  const tymJePlny = minKapacita > 0 && pocetClenu >= minKapacita;
  const odpočet = useOdpočet(přihlášen && !tymJePlny ? data?.casZalozeniMs : undefined);

  if (ukažDemo) {
    return (
      <div className="modal_obal" onClick={(e) => { if (e.target === e.currentTarget) setUkažDemo(false); }}>
        <div className="modal clearfix" style={{ maxHeight: "80vh", overflowY: "auto" }}>
          <button class="vpravo zpet" onClick={() => setUkažDemo(false)}>Zavřít</button>
          <PripravaTymuDemo />
        </div>
      </div>
    );
  }

  if (potvrzení) {
    return (
      <div className="modal_obal">
        <div className="modal clearfix">
          <p>{potvrzení.text}</p>
          <div style={{ display: "flex", gap: "8px", justifyContent: "flex-end" }}>
            <button style={{ width: "unset" }} onClick={() => setPotvrzení(null)}>Zrušit</button>
            <button style={{ width: "unset" }} onClick={() => { potvrzení.akce(); setPotvrzení(null); }}>Potvrdit</button>
          </div>
        </div>
      </div>
    );
  }

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
            <div class="vpravo" style={{ display: "flex", gap: "8px" }}>
              <button style={{ width: "unset" }} onClick={() => setUkažDemo(true)}>🧪 Demo</button>
              {přihlášen && onOdhlásit && (
                <button class="" onClick={sPotvrzením(`Opravdu se chcete odhlásit z aktivity${nazevAktivity ? ` ${nazevAktivity}` : ""} a opustit tým?`, onOdhlásit)}>Odhlásit!</button>
              )}
            </div>
          </div>

          {data?.casText && (
            <div style={{ color: "#666", marginBottom: "8px" }}>{data.casText}</div>
          )}

          {přihlášen && odpočet !== null && odpočet > 0 && (
            <div style={{ color: "#b80", marginBottom: "8px" }}>
              Tým bude zveřejněn za {formatZbývá(odpočet)} h — doplňte členy nebo odeberte volná místa.
            </div>
          )}

          {(načítá || načítáAkci) && <div>Načítám...</div>}

          {chyba && <div style={{ color: "red" }}>{chyba}</div>}

          {/* === Výběr aktivit pro nový tým === */}
          {vyberAktivit && (
            <div style={{ gap: "12px", display: "flex", flexDirection: "column", alignItems: "start" }}>
              <div>Kód týmu: <strong>{vyberAktivit.kodTymu}</strong></div>
              <div>Vyberte aktivity, na které chcete tým přihlásit:</div>
              {vyberAktivit.aktivity.length === 0 && (
                <div style={{ color: "#888" }}>Žádné aktivity k výběru</div>
              )}
              <ul style={{ listStyle: "none", padding: 0, margin: 0 }}>
                {vyberAktivit.aktivity.map((a) => (
                  <li key={a.id} style={{ padding: "4px 0" }}>
                    <label style={{ display: "flex", alignItems: "center", gap: "8px", cursor: "pointer" }}>
                      <input
                        type="checkbox"
                        checked={vyberAktivit.vybrane.has(a.id)}
                        onChange={() => onPřepniVybranou?.(a.id)}
                      />
                      <span>{a.nazev}</span>
                      {a.casText && <span style={{ color: "#888", fontSize: "0.85em" }}>{a.casText}</span>}
                    </label>
                  </li>
                ))}
              </ul>
              <button
                disabled={vyberAktivit.aktivity.length > 0 && vyberAktivit.vybrane.size === 0}
                onClick={onPotvrdVyber}
              >
                Přihlásit tým na vybrané aktivity
              </button>
            </div>
          )}

          {!načítá && !načítáAkci && !vyberAktivit && (
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
                      onClick={() => onPřipojitSe(undefined, +kódPřipojeníDoTýmu)}
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
                        <button style={{ width: "unset" }} onClick={sPotvrzením("Opravdu chcete přegenerovat kód týmu? Starý kód přestane fungovat.", onPregenerujKód)}>
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
                      <strong>Členové týmu</strong>
                      {data.limitTymu !== null && data.limitTymu !== undefined && (
                        <span style={{ color: "#666", marginLeft: "6px" }}>
                          ({pocetClenu}/{data.limitTymu}{data.minKapacita ? `, min. ${data.minKapacita}${maxKapacita !== null && data.limitTymu < maxKapacita ? ` max. ${maxKapacita}` : ""}` : ""})
                        </span>
                      )}
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
                            {jeKapitán && !clen.jeKapitan && (
                              <div style={{ display: "flex", gap: "4px" }}>
                                {onPredejKapitana && (
                                  <button
                                    style={{ width: "unset", padding: "2px 8px" }}
                                    onClick={sPotvrzením(`Opravdu chcete předat kapitána hráči ${clen.jmeno}?`, () => onPredejKapitana(clen.id))}
                                  >
                                    Předat kapitána
                                  </button>
                                )}
                                {onOdhlásitČlena && (
                                  <button
                                    style={{ width: "unset", padding: "2px 8px" }}
                                    onClick={sPotvrzením(`Opravdu chcete odebrat hráče ${clen.jmeno} z týmu${data?.nazev ? ` ${data.nazev}` : ""}${nazevAktivity ? ` na aktivitě ${nazevAktivity}` : ""}?`, () => onOdhlásitČlena(clen.id))}
                                  >
                                    Odebrat
                                  </button>
                                )}
                              </div>
                            )}
                          </li>
                        ))}
                        {data.limitTymu !== null && data.limitTymu !== undefined && Array.from({ length: data.limitTymu - pocetClenu }).map((_, i) => (
                          <li
                            key={`volne-${i}`}
                            style={{ padding: "4px 0", color: "#888", fontStyle: "italic" }}
                          >
                            volné místo
                          </li>
                        ))}
                      </ul>

                      {/* Úprava limitu po jednom — jen kapitán */}
                      {jeKapitán && onNastavLimit && data.limitTymu !== null && data.limitTymu !== undefined && (
                        <div style={{ display: "flex", alignItems: "center", gap: "8px", marginTop: "4px" }}>
                          <button
                            style={{ width: "unset", padding: "2px 10px" }}
                            disabled={data.limitTymu <= Math.max(pocetClenu, minKapacita)}
                            onClick={() => onNastavLimit(data.limitTymu! - 1)}
                          >
                            −
                          </button>
                          <span style={{ color: "#666" }}>volná místa</span>
                          <button
                            style={{ width: "unset", padding: "2px 10px" }}
                            disabled={maxKapacita !== null && data.limitTymu >= maxKapacita}
                            onClick={() => onNastavLimit(data.limitTymu! + 1)}
                          >
                            +
                          </button>
                        </div>
                      )}
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
