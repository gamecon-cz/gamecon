import { FunctionComponent } from "preact";
import { useAktivita, useNastaveniTymuModalAktivitaId, useNastaveniTymuModalData, useOdhlasitModalAktivitaId } from "../../../../store/program/selektory";
import { dotáhniNastaveníTýmuProModal, nastavModalNastaveníTýmu, nastavModalOdhlásit } from "../../../../store/program/slices/všeobecnéSlice";
import { proveďAkciAktivity } from "../../../../store/program/slices/programDataSlice";
import { useProgramStore } from "../../../../store/program";
import { useState } from "preact/hooks";
import { fetchNastavVerejnostTymu } from "../../../../api/program";



export const registrujDotahováníNastaveníTýmu = () => {
  useProgramStore.subscribe(s => s.všeobecné.nastaveniTymu?.aktivitaId, (aktivitaId) => {
    if (aktivitaId)
      void dotáhniNastaveníTýmuProModal();
  });
}


export const NastaveniTymuModal: FunctionComponent<{}> = (props) => {
  const aktivitaId = useNastaveniTymuModalAktivitaId();
  const aktivita = useAktivita(aktivitaId ?? -1);
  const data = useNastaveniTymuModalData();
  const [kódPřipojeníDoTýmu, setKódPřipojeníDoTýmu] = useState("");

  if (!aktivitaId) return <></>;

  const přihlášen = aktivita?.stavPrihlaseni === "prihlasen"
    || aktivita?.stavPrihlaseni === "dorazilJakoNahradnik"
    || aktivita?.stavPrihlaseni === "pozdeZrusil"
    || aktivita?.stavPrihlaseni === "prihlasenADorazil"
    || aktivita?.stavPrihlaseni === "prihlasenAleNedorazil"
    ;


  // todo: loading
  if (přihlášen && !data) return <></>;


  const sleduji = aktivita?.stavPrihlaseni === "sledujici";

  // todo:
  const můžeZaložitNovýTým = true;

  const zavřítModal = () => nastavModalNastaveníTýmu();

  const přepniVerejnost = async () => {
    if (!data || data.verejny === undefined || !data.kod) return;
    const nováHodnota = !data.verejny;
    await fetchNastavVerejnostTymu(aktivitaId, data.kod, nováHodnota);
    void dotáhniNastaveníTýmuProModal();
  };


  return (
    <>
      <div className="modal_obal" onClick={(e) => {
        if (e.target === e.currentTarget)
          nastavModalOdhlásit();
      }}>
        <div className="modal clearfix">
          <div className="clearfix">
            <h3 style={{ float: "left" }}>Nastavení týmu aktivity {aktivita?.nazev}</h3>
            {
              přihlášen &&
              <button class="vpravo" onClick={() => {
                nastavModalOdhlásit(aktivitaId);
              }}>Odhlásit!</button>
            }
          </div>
          <div style={{ gap: "16px", display: "flex", flexDirection: "column", alignItems: "start" }}>
            {
              !přihlášen && <>
                <button disabled={!můžeZaložitNovýTým}
                  onClick={() => {
                    void proveďAkciAktivity(aktivitaId, "prihlasit")
                      .then(zavřítModal)
                      ;
                  }}
                >Založ tým</button>
                <div style={{ gap: "4px", display: "flex", flexDirection: "column", alignItems: "start" }}>
                  <label>
                    kód:
                    <input placeholder="XXXX" onChange={x=>setKódPřipojeníDoTýmu(x.currentTarget.value)} value={kódPřipojeníDoTýmu}/>
                  </label>
                  <button style={{ width: "unset" }}
                   onClick={() => {
                    // todo: validace
                    const kód = +kódPřipojeníDoTýmu;
                    void proveďAkciAktivity(aktivitaId, "prihlasit", kód)
                      .then(zavřítModal)
                      ;
                  }}
                  >Připoj se do týmu</button>
                </div>

                {data?.verejneTymy && data.verejneTymy.length > 0 && (
                  <div style={{ marginTop: "8px" }}>
                    <strong>Veřejné týmy:</strong>
                    <ul style={{ listStyle: "none", padding: 0, margin: "4px 0" }}>
                      {data.verejneTymy.map(tym => {
                        const plny = tym.limit !== null && tym.pocetClenu >= tym.limit;
                        return (
                          <li key={tym.kod} style={{ display: "flex", alignItems: "center", gap: "8px", padding: "4px 0" }}>
                            <span>{tym.nazev || `Tým ${tym.kod}`}</span>
                            <span style={{ color: "#888" }}>
                              {tym.pocetClenu}{tym.limit !== null ? `/${tym.limit}` : ""}
                            </span>
                            <button
                              disabled={plny}
                              style={{ width: "unset" }}
                              onClick={() => {
                                void proveďAkciAktivity(aktivitaId, "prihlasit", tym.kod)
                                  .then(zavřítModal);
                              }}
                            >{plny ? "Plný" : "Připojit se"}</button>
                          </li>
                        );
                      })}
                    </ul>
                  </div>
                )}
              </>
            }

            {
              přihlášen &&
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
                      onChange={přepniVerejnost}
                    />
                    Veřejný tým (kdokoliv se může přihlásit bez kódu)
                  </label>
                )}
              </>
            }
          </div>

          <button class="vpravo zpet" onClick={zavřítModal}>Zavřít</button>
        </div>
      </div>
    </>
  );
};
