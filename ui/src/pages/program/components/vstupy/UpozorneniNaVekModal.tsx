import { Fragment, FunctionComponent } from "preact";
import { GAMECON_KONSTANTY } from "../../../../env";
import { useUpozorněníNaVěkModal } from "../../../../store/program/selektory";
import { zavřiUpozorněníNaVěk } from "../../../../store/program/slices/všeobecnéSlice";

/**
 * Před přihlášením na aktivitu, na jejíž minimální věk (štítek „Věk: 15+") účastník v den aktivity nedosáhne.
 * Přihlášení nebrání, účastník se má jen předem domluvit se šéfem programu.
 * Text je celý z PHP (Aktivita::getUpozorneniNaVek()), stejný ukazuje záložní confirm() na stránkách linií.
 */
export const UpozorneniNaVekModal: FunctionComponent = () => {
  const upozornění = useUpozorněníNaVěkModal();

  if (!upozornění) return <></>;

  const { nadpis, text } = upozornění;
  const kontakt = GAMECON_KONSTANTY.KONTAKT_NA_SEFA_PROGRAMU;
  // adresu šéfa programu v textu udělá odkazem
  const částiTextu = kontakt ? text.split(kontakt) : [text];

  return (
    <div className="modal_obal" onClick={(e) => {
      if (e.target === e.currentTarget)
        zavřiUpozorněníNaVěk(false);
    }}>
      <div className="modal clearfix">
        <h3>{nadpis}</h3>
        <p>
          {částiTextu.map((část, index) => (
            <Fragment key={index}>
              {index > 0 && <a href={`mailto:${kontakt}`}>{kontakt}</a>}
              {část}
            </Fragment>
          ))}
        </p>
        <button class="vpravo" style={{ marginLeft: "8px" }} onClick={() => { zavřiUpozorněníNaVěk(true); }}>Přihlásit</button>
        <button class="vpravo zpet" onClick={() => { zavřiUpozorněníNaVěk(false); }}>Zpět</button>
      </div>
    </div>
  );
};
