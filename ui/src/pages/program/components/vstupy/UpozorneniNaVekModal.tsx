import { FunctionComponent } from "preact";
import { useUpozorněníNaVěkModal } from "../../../../store/program/selektory";
import { zavřiUpozorněníNaVěk } from "../../../../store/program/slices/všeobecnéSlice";

/** stejná adresa je v Aktivita::KONTAKT_NA_SEFA_PROGRAMU (záložní confirm() na stránkách linií) */
const KONTAKT_NA_ŠÉFA_PROGRAMU = "info@gamecon.cz";

/**
 * Před přihlášením na aktivitu, na jejíž minimální věk (štítek „Věk: 15+") účastník v den aktivity nedosáhne.
 * Přihlášení nebrání, účastník se má jen předem domluvit se šéfem programu.
 */
export const UpozorneniNaVekModal: FunctionComponent = () => {
  const upozornění = useUpozorněníNaVěkModal();

  if (!upozornění) return <></>;

  const { nazevAktivity, minimalniVek } = upozornění;

  return (
    <div className="modal_obal" onClick={(e) => {
      if (e.target === e.currentTarget)
        zavřiUpozorněníNaVěk(false);
    }}>
      <div className="modal clearfix">
        <h3>Aktivita {nazevAktivity} je od {minimalniVek} let</h3>
        <p>
          V den aktivity ti ještě nebude {minimalniVek}. Přihlas se, jen když máš výjimku domluvenou se šéfem programu.
          Domluvit se můžeš na <a href={`mailto:${KONTAKT_NA_ŠÉFA_PROGRAMU}`}>{KONTAKT_NA_ŠÉFA_PROGRAMU}</a>.
        </p>
        <button class="vpravo" style={{ marginLeft: "8px" }} onClick={() => { zavřiUpozorněníNaVěk(true); }}>Přihlásit</button>
        <button class="vpravo zpet" onClick={() => { zavřiUpozorněníNaVěk(false); }}>Zpět</button>
      </div>
    </div>
  );
};
