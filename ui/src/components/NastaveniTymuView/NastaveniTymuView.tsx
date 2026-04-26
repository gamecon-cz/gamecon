import { FunctionComponent } from "preact";
import { useState, useEffect, useMemo } from "preact/hooks";
import { GAMECON_KONSTANTY } from "../../env";
import { NastaveniTymuData } from "../../store/program/slices/všeobecnéSlice";
import { AkceTymuBezKontextu, ClenTymu, ApiTymVSeznamu } from "../../api/program";
import { PripravaTymu, KoloAktivity } from "../PripravaTymu";
import { proveďAkciAktivity } from "../../store/program/slices/programDataSlice";
import { TymDetail } from "./TymDetail";
import { useAktivity } from "../../store/program/selektory";
import { denAktivity, denČasAktivityText } from "../../store/program/logic/aktivity";

type NastaveniTymuViewProps = {
  nazevAktivity?: string;
  data: NastaveniTymuData | undefined;
  přihlášenNaAktivitě: boolean;
  jeKapitán: boolean;
  načítá?: boolean;
  načítáAkci?: boolean;
  chyba?: string | undefined;
  onZavřít: () => void;
  onPřipojitSe: (idTýmu?: number, kód?: number) => void;
  onOdhlásit: () => void;
  onProveďAkci: (akceTymu: AkceTymuBezKontextu, dotáhniIpřiNeúspěchu?: boolean) => Promise<void>
};

const SeznamTymu: FunctionComponent<{
  tymy: ApiTymVSeznamu[];
  zobrazitPřipojení: boolean;
  onPřipojitSe: (idTýmu?: number, kód?: number) => void;
}> = ({ tymy, zobrazitPřipojení, onPřipojitSe }) => {
  if (tymy.length === 0)
    return <div style={{ color: "#888" }}>Žádné týmy</div>;

  return (
    <div style={{ marginTop: "8px", width: "100%" }}>
      <strong>Všechny týmy:</strong>
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
    </div>
  );
};


export const NastaveniTymuView: FunctionComponent<NastaveniTymuViewProps> = (props) => {
  const {
    nazevAktivity,
    data,
    přihlášenNaAktivitě,
    jeKapitán,
    načítá,
    načítáAkci,
    chyba,
    onZavřít,
    onPřipojitSe,
    onOdhlásit,
    onProveďAkci,
  } = props;
  const vsechnyAktivity = useAktivity();

  const [kódPřipojeníDoTýmu, setKódPřipojeníDoTýmu] = useState("");
  const [potvrzení, setPotvrzení] = useState<{ text: string; akce: () => void } | null>(null);

  const sPotvrzením = (text: string, akce: () => void) => () => setPotvrzení({ text, akce });

  const modalMáTým = !!data?.tym?.id;
  const pocetClenu = data?.tym?.clenove?.length ?? 0;
  const týmJePřipravený = pocetClenu > 0;

  const aktivityTymuId = data?.tym?.aktivityTymuId ?? [];
  const aktivityTymu = aktivityTymuId.map(id=>vsechnyAktivity.find(a=>a.id===id)).filter(x=>x !== undefined);

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

  const týmNázev = data?.tym?.nazev ?? "";

  const onOdemkniSPotvrzenim = sPotvrzením(
    `Opravdu chcete odemknout tým${týmNázev}?`, () => {
      void onProveďAkci({ typ: "odemkni" });
    });

  const onOdhlasitSPotvrzenim = sPotvrzením(
    `Opravdu se chcete odhlásit z aktivity${nazevAktivity ? ` ${nazevAktivity}` : ""} a opustit tým?`,
    onOdhlásit
  )

  const onPotvrditVýběrAktivit = (idVybranychAktivit: number[]) => {
    void onProveďAkci({ typ: "potvrdVyberAktivit", idVybranychAktivit }, true);
  };

  const onPrihlasitKapitana = () => {
    void onProveďAkci({ typ: "prihlasKapitana" });
  }

  const onSmazatTym =
    sPotvrzením(`Opravdu chcete smazat tým z aktivity ${nazevAktivity ? ` ${nazevAktivity}` : ""} ?`,
      () => void onProveďAkci({ typ: "smazTym" }))

  const onZamkniTym = sPotvrzením(
    `Opravdu chcete zamknout tým${týmNázev}? Tým se poté nebude moci editovat. Tato akce je nevratná.`,
    () => void onProveďAkci({ typ: "zamkni" })
  );

  const onPřegenrovatSPotvrzením = sPotvrzením("Opravdu chcete přegenerovat kód týmu? Starý kód přestane fungovat.",
    ()=>void onProveďAkci({typ:"pregenerujKod"})
  );

  const onPředejKapitánaSPotvrzením = (clen: ClenTymu) => sPotvrzením(
    `Opravdu chcete předat kapitána hráči ${clen.jmeno}?`,
    () => void onProveďAkci({typ: "predejKapitana", idNovehoKapitana: clen.id})
  )();

  const onOdebratČlenaSPotvrzením = (clen: ClenTymu) => sPotvrzením(
    `Opravdu chcete odebrat hráče ${clen.jmeno} z týmu${týmNázev}${nazevAktivity ? ` na aktivitě ${nazevAktivity}` : ""}?`,
    () => void onProveďAkci({typ: "odhlasClena", idClena: clen.id})
  )();

  const onZaložitTým = () => void onProveďAkci({typ:"zalozPrazdnyTym"});

  const onPřepniVerejnost = () => void onProveďAkci({typ:"nastavVerejnost", verejny: !data?.tym?.verejny});

  const onNastavLimit = (limit: number) => void onProveďAkci({typ: "nastavLimit", limit});

  const seznamTýmů = data?.vsechnyTymy && data.vsechnyTymy.length > 0 && (
    <SeznamTymu
      tymy={data.vsechnyTymy}
      zobrazitPřipojení={false}
      onPřipojitSe={onPřipojitSe}
    />
  );

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
            <div class="vpravo" style={{ display: "flex", gap: "8px", alignItems: "center", flexWrap: "wrap", justifyContent: "flex-end" }}>
                {modalMáTým && !týmJePřipravený && (
                  <button
                    onClick={onSmazatTym}
                    style={{
                      backgroundColor: "#f44",
                      color: "white",
                      border: "none",
                      borderRadius: "4px",
                      cursor: "pointer",
                      fontWeight: "bold",
                    }}
                    disabled={načítá || načítáAkci}
                    >
                      🗑️ Smazat tým
                  </button>
                )}
              {přihlášenNaAktivitě && data?.tym?.zamceny && (
                <button style={{ width: "unset" }} onClick={onOdemkniSPotvrzenim}>Odemknout</button>
              )}
              {přihlášenNaAktivitě && (
                <button disabled={data?.tym?.zamceny} onClick={onOdhlasitSPotvrzenim}>Odhlásit!</button>
              )}
            </div>
          </div>

          {aktivityTymu.map(aktivita=>{
            <div style={{ color: "#666", marginBottom: "8px" }}>{denČasAktivityText(aktivita)}</div>
          })}

          {(načítá || načítáAkci) && <div>Načítám...</div>}

          {chyba && <div style={{ color: "red" }}>{chyba}</div>}

          {/* === Příprava nového týmu (výběr kol + přihlášení kapitána) === */}
          {!načítá && data && data.tym && !týmJePřipravený && (
            <PripravaTymu
              casExpiraceMs={data.tym?.casExpiraceMs}
              onVybranéAktivity={onPotvrditVýběrAktivit}
              onPrihlasitKapitana={onPrihlasitKapitana}
              nacita={načítáAkci}
            />
          )}

          {!načítá && !modalMáTým && !načítáAkci &&
              (
                <>
                  <button onClick={onZaložitTým}>Založ tým</button>
                  <div style={{ marginTop:"1em", gap: "4px", display: "flex", flexDirection: "column", alignItems: "start" }}>
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
              )
          }

          {!načítá && týmJePřipravený && !načítáAkci && (
            <div style={{ gap: "16px", display: "flex", flexDirection: "column", alignItems: "start" }}>
              {přihlášenNaAktivitě && data && (
                <>
                  <TymDetail
                    data={data}
                    přihlášenNaAktivitě={přihlášenNaAktivitě}
                    jeKapitán={jeKapitán}
                    onPřepniVerejnost={onPřepniVerejnost}
                    onPřegenrovatSPotvrzením={onPřegenrovatSPotvrzením}
                    onPředejKapitánaSPotvrzením={onPředejKapitánaSPotvrzením}
                    onOdebratČlenaSPotvrzením={onOdebratČlenaSPotvrzením}
                    onNastavLimit={onNastavLimit}
                    onZamkniTym={onZamkniTym}
                  />
                </>
              )}
            </div>
          )}

          {seznamTýmů}

          <button class="vpravo zpet" onClick={onZavřít}>Zavřít</button>
        </div>
      </div>
    </>
  );
};
