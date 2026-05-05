import { FunctionComponent } from "preact";
import { NastaveniTymuData } from "../../store/program/slices/všeobecnéSlice";
import { ApiAktivitaTym, ClenTymu } from "../../api/program";
import { useEffect, useState } from "preact/hooks";

type TymDetailProps = {
  data: NastaveniTymuData;
  přihlášenNaAktivitě: boolean;
  jeKapitán: boolean;
  onPřepniVerejnost: () => void;
  onPřegenrovatSPotvrzením: () => void;
  onPředejKapitánaSPotvrzením: (clen: ClenTymu) => void;
  onOdebratČlenaSPotvrzením: (clen: ClenTymu) => void;
  onNastavLimit: (limit: number) => void;
  onZamkniTym: () => void;
};

export const TymDetail: FunctionComponent<TymDetailProps> = ({
  data,
  přihlášenNaAktivitě,
  jeKapitán,
  onPřepniVerejnost,
  onPřegenrovatSPotvrzením,
  onPředejKapitánaSPotvrzením,
  onOdebratČlenaSPotvrzením,
  onNastavLimit,
  onZamkniTym,
}) => {
  const dataTymu = data.tym;
  const časExpiraceMs = !dataTymu?.zamceny ? dataTymu?.casExpiraceMs : undefined;

  const jeVTymu = !!dataTymu?.id;
  const maxKapacita = dataTymu?.maxKapacita ?? null;
  const minKapacita = dataTymu?.minKapacita ?? 0;
  const pocetClenu = dataTymu?.clenove?.length ?? 0;

  const minKapacitaNaplněna = pocetClenu >= minKapacita;
  const tymJePlny = minKapacita > 0 && pocetClenu >= minKapacita;
  const týmJePřipravený = pocetClenu > 0;



  const [zkopírováno, setZkopírováno] = useState(false);
  const zkopírujKód = (kód: number) => {
    void navigator.clipboard.writeText(String(kód)).then(() => {
      setZkopírováno(true);
      setTimeout(() => setZkopírováno(false), 1500);
    });
  };

  return (
    <div>
      {přihlášenNaAktivitě && dataTymu?.zamceny && (
        <div style={{ color: "#4a4", marginBottom: "8px", fontWeight: "bold" }}>
          ✓ Tým je zamčený a připravený k hraní.
        </div>
      )}

      <OdpočetExpiraceAktivity
        dataTym={dataTymu}
        časExpiraceMs={časExpiraceMs}
        minKapacitaNaplněna={minKapacitaNaplněna}
        přihlášenNaAktivitě={přihlášenNaAktivitě}
      />

      {/* Kód týmu — všichni přihlášení */}
      {!dataTymu?.zamceny && dataTymu?.kod && (
        <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
          <span style={{ fontSize: "1.3em" }}>
            kód týmu:{" "}
            <span
              title="Klikni pro zkopírování"
              style={{ cursor: "pointer", textDecoration: "underline dotted" }}
              onClick={() => zkopírujKód(dataTymu?.kod ?? -666)}
            >
              {dataTymu.kod}
            </span>
            {zkopírováno && <span style={{ color: "#4a4", marginLeft: "6px", fontSize: "0.8em" }}>zkopírováno!</span>}
          </span>
          {jeKapitán && (
            <button style={{ width: "unset" }} onClick={onPřegenrovatSPotvrzením}>
              Přegenerovat kód
            </button>
          )}
        </div>
      )}

      {/* Veřejnost — jen kapitán */}
      {!dataTymu?.zamceny && jeKapitán && dataTymu?.verejny !== undefined && (
        <label style={{ display: "flex", alignItems: "center", gap: "8px" }}>
          <input
            type="checkbox"
            checked={dataTymu.verejny}
            onChange={onPřepniVerejnost}
          />
          Veřejný tým (kdokoliv se může přihlásit bez kódu)
        </label>
      )}

      {/* Seznam členů */}
      {dataTymu?.clenove && dataTymu.clenove.length > 0 && (
        <div style={{ width: "100%" }}>
          <strong>Členové týmu</strong>
          {dataTymu.limitTymu !== null && dataTymu.limitTymu !== undefined && (
            <span style={{ color: "#666", marginLeft: "6px" }}>
              ({pocetClenu}/{dataTymu.limitTymu}{dataTymu.minKapacita ? `, min. ${dataTymu.minKapacita}${maxKapacita !== null && dataTymu.limitTymu < maxKapacita ? ` max. ${maxKapacita}` : ""}` : ""})
            </span>
          )}
          <ul style={{ listStyle: "none", padding: 0, margin: "4px 0" }}>
            {dataTymu.clenove.map((clen) => (
              <li
                key={clen.id}
                style={{ display: "flex", alignItems: "center", gap: "8px", padding: "4px 0" }}
              >
                <span>
                  {clen.jmeno}
                  {clen.jeKapitan && <span style={{ color: "#888", marginLeft: "4px" }}>(kapitán)</span>}
                </span>
                {!dataTymu?.zamceny && jeKapitán && !clen.jeKapitan && (
                  <div style={{ display: "flex", gap: "4px" }}>
                    {(
                      <button
                        style={{ width: "unset", padding: "2px 8px" }}
                        onClick={()=>onPředejKapitánaSPotvrzením(clen)}
                      >
                        Předat kapitána
                      </button>
                    )}
                    <button
                      style={{ width: "unset", padding: "2px 8px" }}
                      onClick={() => onOdebratČlenaSPotvrzením(clen)}
                    >
                      Odebrat
                    </button>
                  </div>
                )}
              </li>
            ))}
            {dataTymu.limitTymu !== null && dataTymu.limitTymu !== undefined && Array.from({ length: dataTymu.limitTymu - pocetClenu }).map((_, i) => (
              <li
                key={`volne-${i}`}
                style={{ padding: "4px 0", color: "#888", fontStyle: "italic" }}
              >
                volné místo
              </li>
            ))}
          </ul>

          {/* Úprava limitu po jednom — jen kapitán */}
          {!dataTymu?.zamceny && jeKapitán && dataTymu.limitTymu !== null && dataTymu.limitTymu !== undefined && (
            <div style={{ display: "flex", alignItems: "center", gap: "8px", marginTop: "4px" }}>
              <button
                style={{ width: "unset", padding: "2px 10px" }}
                disabled={dataTymu.limitTymu <= Math.max(pocetClenu, minKapacita)}
                onClick={() => onNastavLimit(dataTymu.limitTymu! - 1)}
              >
                −
              </button>
              <span style={{ color: "#666" }}>volná místa</span>
              <button
                style={{ width: "unset", padding: "2px 10px" }}
                disabled={maxKapacita !== null && dataTymu.limitTymu >= maxKapacita}
                onClick={() => onNastavLimit(dataTymu.limitTymu! + 1)}
              >
                +
              </button>
            </div>
          )}
        </div>
      )}

      {/* Zavřít tým — jen kapitán, pokud tým není zamčený a má min. počet lidí */}
      {!dataTymu?.zamceny && jeKapitán && (
        <button
          style={{ width: "unset" }}
          disabled={pocetClenu < minKapacita}
          onClick={onZamkniTym}
        >
          Zamknout tým
        </button>
      )}
    </div>
  );
};


const OdpočetExpiraceAktivity: FunctionComponent<{
  dataTym: ApiAktivitaTym | undefined;
  časExpiraceMs: number | undefined;
  minKapacitaNaplněna: boolean;
  přihlášenNaAktivitě: boolean;
}> = ({ dataTym, časExpiraceMs, minKapacitaNaplněna, přihlášenNaAktivitě }) => {
  const odpočet = useOdpočet(časExpiraceMs);
  if (!přihlášenNaAktivitě || !odpočet || odpočet <= 0) return null;

  return (
    <div style={{
      background: dataTym?.smazatPoExpiraci ?? false ? "#fff3f3" : "#fffbe6",
      border: `1px solid ${dataTym?.smazatPoExpiraci ? "#c33" : "#b80"}`,
      borderRadius: "4px",
      padding: "8px 12px",
      marginBottom: "8px",
    }}>
      {dataTym?.smazatPoExpiraci
        ? <>
          Za {formatZbývá(odpočet)} h bude tým automaticky{" "}
          <strong style={{ color: "#c00" }}>smazán</strong>
          {" "}— kapitán musí tým zamknout. {minKapacitaNaplněna? "" : "Nejdříve ale musí tým naplnit alespoň min kapacitu"}
        </>
        : <>
          Za {formatZbývá(odpočet)} h bude tým automaticky zveřejněn
          {" "}— kapitán musí tým zamknout. {minKapacitaNaplněna? "" : "Nejdříve ale musí tým naplnit alespoň min kapacitu"}
        </>
      }
    </div>
  );
};


const formatZbývá = (ms: number): string => {
  if (ms <= 0) return "0:00";
  const h = Math.floor(ms / 3600000);
  const m = Math.floor((ms % 3600000) / 60000);
  return `${h}:${m.toString().padStart(2, "0")}`;
};

const useOdpočet = (casExpiraceMs: number | undefined): number | null => {
  const [zbývá, setZbývá] = useState<number | null>(null);

  useEffect(() => {
    if (!casExpiraceMs) return;
    const update = () => setZbývá(Math.max(0, casExpiraceMs - Date.now()));
    update();
    const id = setInterval(update, 60000);
    return () => clearInterval(id);
  }, [casExpiraceMs]);

  return zbývá;
};

