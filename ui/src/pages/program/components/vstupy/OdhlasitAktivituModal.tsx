import { FunctionComponent } from "preact";
import { useRef } from "preact/hooks";
import { useAktivita, useOdhlasitModalAktivitaId } from "../../../../store/program/selektory";
import { nastavModalOdhlásit } from "../../../../store/program/slices/všeobecnéSlice";
import { fetchAktivitaAkce } from "../../../../api/program";
import { načtiRok } from "../../../../store/program/slices/programDataSlice";
import { GAMECON_KONSTANTY } from "../../../../env";
import { useProgramStore } from "../../../../store/program";


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
            useProgramStore.setState(s => { s.všeobecné.načítání = true; });
            fetchAktivitaAkce("odhlasit", aktivitaId)
              .then(async () => načtiRok(GAMECON_KONSTANTY.ROCNIK))
              .catch(x => { console.error(x); })
              .finally(() => { useProgramStore.setState(s => { s.všeobecné.načítání = false; }); });
          }}>Odhlásit!</button>
          <button class="zpet" onClick={() => { nastavModalOdhlásit(); }}>Zavřít</button>
        </div>
      </div>
    </>
  );
};
