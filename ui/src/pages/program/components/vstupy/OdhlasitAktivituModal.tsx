import { FunctionComponent } from "preact";
import { useAktivita, useOdhlasitModalAktivitaId } from "../../../../store/program/selektory";
import { nastavModalOdhlásit } from "../../../../store/program/slices/všeobecnéSlice";
import { proveďAkciAktivity } from "../../../../store/program/slices/programDataSlice";
import { t } from "../../../../i18n";


type PotvrzeniModalProps = {};

export const OdhlasitAktivituModal: FunctionComponent<PotvrzeniModalProps> = (props) => {
  const aktivitaId = useOdhlasitModalAktivitaId();
  const aktivita = useAktivita(aktivitaId ?? -1);

  if (!aktivitaId) return <></>;

  return (
    <>
      <div className="modal_obal" onClick={(e) => {
        if (e.target === e.currentTarget)
          nastavModalOdhlásit();
      }}>
        <div className="modal clearfix">
          <h3>{t("program.odhlasit.otazka", { aktivita: aktivita?.nazev ?? "" })}</h3>
          <button class="vpravo" style={{marginLeft:"8px"}} onClick={() => {
            nastavModalOdhlásit();
            void proveďAkciAktivity(aktivitaId, "odhlasit");
          }}>{t("program.odhlasit.potvrdit")}</button>
          <button class="vpravo zpet" onClick={() => { nastavModalOdhlásit(); }}>{t("program.odhlasit.zavrit")}</button>
        </div>
      </div>
    </>
  );
};
