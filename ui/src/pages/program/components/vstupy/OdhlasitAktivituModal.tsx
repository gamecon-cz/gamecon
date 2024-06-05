import { FunctionComponent } from "preact";
import { useRef } from "preact/hooks";
import { useAktivita, useOdhlasitModalAktivitaId } from "../../../../store/program/selektory";
import { nastavModalOdhlásit } from "../../../../store/program/slices/všeobecnéSlice";


type PotvrzeniModalProps = {};

export const OdhlasitAktivituModal: FunctionComponent<PotvrzeniModalProps> = (props) => {
  const aktivitaId = useOdhlasitModalAktivitaId();
  const aktivita = useAktivita(aktivitaId ?? -1);
  const formRef = useRef<HTMLFormElement>(null);

  return (
    <>
      <form ref={formRef} method="post" style="display:inline">
        <input type="hidden" name={"odhlasit"} value={aktivitaId}></input>
        {aktivitaId
          ? <div className="modalOdhlasit_obal" onClick={(e) => {
            if (e.target === e.currentTarget)
              nastavModalOdhlásit();
          }}>
            <div className="modalOdhlasit clearfix">
              <h3>Opravdu se chceš odhlásit z aktivity {aktivita?.nazev}?</h3>
              <input type="submit">Odhlásit</input>
            </div>
          </div>
          : undefined}
      </form>
    </>
  );
};
