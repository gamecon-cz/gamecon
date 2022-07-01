import { FunctionComponent } from "preact";
import { useEffect, useRef } from "preact/hooks";
import { Aktivita } from "../../../../api/program";

type ProgramNáhledProps = { aktivita: Aktivita };

// TODO: obsazenost
export const ProgramNáhled: FunctionComponent<ProgramNáhledProps> = (props) => {
  const { aktivita } = props;

  const programNáhledTextRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    programNáhledTextRef.current?.scroll?.(0, 0);
  }, [aktivita]);

  return (
    <div class="programNahled_obalNahledu programNahled_obalNahledu-maData programNahled_obalNahledu-viditelny">
      <div class="programNahled_nahled">
        <div class="programNahled_placeholder"></div>

        <div class="programNahled_obsah">
          <div class="programNahled_zaviratko"></div>
          <div class="programNahled_hlavicka">
            <div class="programNahled_nazev">{aktivita.nazev}</div>
            <div class="programNahled_vypraveci">
              {aktivita.vypraveci.join(", ")}
            </div>
            <div class="programNahled_stitky">
              {aktivita.stitky.map((x) => (
                <div class="programNahled_stitek">{x}</div>
              ))}
            </div>
          </div>
          <div class="programNahled_text" ref={programNáhledTextRef}>
            <div class="programNahled_kratkyPopis">{aktivita.kratkyPopis}</div>
            <div class="programNahled_popis">{aktivita.popis}</div>
          </div>
          <div class="programNahled_paticka">
            <img class="programNahled_obrazek" src={aktivita.obrazek} />
            <div class="programNahled_obsazenost"></div>
            <div class="programNahled_cas">{aktivita.casText}</div>
            <div class="programNahled_cena">{aktivita.cenaZaklad}</div>
          </div>
        </div>
      </div>
    </div>
  );
};
