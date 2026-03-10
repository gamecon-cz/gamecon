import { FunctionComponent } from "preact";
import { useAktivita, useNastaveniTymuModalAktivitaId, useNastaveniTymuModalData, useOdhlasitModalAktivitaId } from "../../../../store/program/selektory";
import { dotáhniNastaveníTýmuProModal, nastavModalNastaveníTýmu, nastavModalOdhlásit } from "../../../../store/program/slices/všeobecnéSlice";
import { proveďAkciAktivity } from "../../../../store/program/slices/programDataSlice";
import { useProgramStore } from "../../../../store/program";
import { useState } from "preact/hooks";



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

              </>
            }
          </div>

          <button class="vpravo zpet" onClick={zavřítModal}>Zavřít</button>
        </div>
      </div>
    </>
  );
};
