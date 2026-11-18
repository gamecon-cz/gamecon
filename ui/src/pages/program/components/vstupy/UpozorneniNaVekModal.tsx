import { Fragment, FunctionComponent } from "preact";
import { useUpozorněníNaVěkModal } from "../../../../store/program/selektory";
import { zavřiUpozorněníNaVěk } from "../../../../store/program/slices/všeobecnéSlice";

/**
 * Před přihlášením na aktivitu, na jejíž minimální věk (štítek „Věk: 15+") účastník v den aktivity nedosáhne.
 * Přihlášení nebrání: šéf sekce dostane e-mail a ozve se účastníkovi, ten se mu může ozvat i sám.
 * Text je celý z PHP (Aktivita::getUpozorneniNaVek()), stejný ukazuje záložní confirm() na stránkách linií.
 */
export const UpozorneniNaVekModal: FunctionComponent = () => {
  const upozornění = useUpozorněníNaVěkModal();

  if (!upozornění) return <></>;

  const { nadpis, text, kontakt } = upozornění;
  // adresu šéfa sekce v textu udělá odkazem
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
