import { FunctionComponent } from "preact";
import { useAktivita, useOdhlasitModalAktivitaId } from "../../../../store/program/selektory";
import { nastavModalOdhlásit } from "../../../../store/program/slices/všeobecnéSlice";
import { proveďAkciAktivity } from "../../../../store/program/slices/programDataSlice";


type PotvrzeniModalProps = {};

export const OdhlasitAktivituModal: FunctionComponent<PotvrzeniModalProps> = (props) => {
  const aktivitaId = useOdhlasitModalAktivitaId();
  const aktivita = useAktivita(aktivitaId ?? -1);

  if (!aktivitaId) return <></>;

  return (
    <>
      <div className="modalOdhlasit_obal" onClick={(e) => {
        if (e.target === e.currentTarget)
          nastavModalOdhlásit();
      }}>
        <div className="modalOdhlasit clearfix">
          <h3>Opravdu se chceš odhlásit z aktivity {aktivita?.nazev}?</h3>
          <button onClick={() => {
            nastavModalOdhlásit();
            void proveďAkciAktivity(aktivitaId, "odhlasit");
          }}>Odhlásit!</button>
          <button class="zpet" onClick={() => { nastavModalOdhlásit(); }}>Zavřít</button>
        </div>
      </div>
    </>
  );
};
