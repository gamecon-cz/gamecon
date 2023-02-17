import { FunctionComponent } from "preact";
import { useEffect, useRef } from "preact/hooks";
import { useProgramStore } from "../../../../store/program";
import { useAktivitaNáhled } from "../../../../store/program/selektory";

const skryj = () => {
  useProgramStore.setState((s) => {
    s.urlState.aktivitaNáhledId = undefined;
  });
};

type ProgramNáhledProps = {};

// TODO: obsazenost
export const ProgramNáhled: FunctionComponent<ProgramNáhledProps> = (props) => {
  const {} = props;
  const aktivita = useAktivitaNáhled();

  const programNáhledTextRef = useRef<HTMLDivElement>(null);

  const obalClass =
    "programNahled_obalNahledu" +
    (aktivita
      ? " programNahled_obalNahledu-viditelny programNahled_obalNahledu-maData"
      : "");

  useEffect(() => {
    programNáhledTextRef.current?.scroll?.(0, 0);
  }, [aktivita]);

  return (
    <div class={obalClass}>
      <div class="programNahled_nahled">
        <div class="programNahled_placeholder"></div>

        <div class="programNahled_obsah">
          <div class="programNahled_zaviratko" onClick={skryj}></div>
          <div class="programNahled_hlavicka">
            <div
              class="programNahled_nazev"
              dangerouslySetInnerHTML={{ __html: aktivita?.nazev ?? "" }}
            ></div>
            <div
              class="programNahled_vypraveci"
              dangerouslySetInnerHTML={{
                __html: aktivita?.vypraveci?.join?.(", ") ?? "",
              }}
            ></div>
            <div class="programNahled_stitky">
              {aktivita?.stitky.map((x) => (
                <div class="programNahled_stitek">{x}</div>
              ))}
            </div>
          </div>
          <div class="programNahled_text" ref={programNáhledTextRef}>
            <div
              class="programNahled_kratkyPopis"
              dangerouslySetInnerHTML={{ __html: aktivita?.kratkyPopis ?? "" }}
            ></div>
            <div
              class="programNahled_popis"
              dangerouslySetInnerHTML={{ __html: aktivita?.popis ?? "" }}
            ></div>
          </div>
          <div class="programNahled_paticka">
            <img class="programNahled_obrazek" src={aktivita?.obrazek} />
            <div class="programNahled_obsazenost"></div>
            <div
              class="programNahled_cas"
              dangerouslySetInnerHTML={{ __html: aktivita?.casText ?? "" }}
            ></div>
            <div class="programNahled_cena">{aktivita?.cenaZaklad}</div>
          </div>
        </div>
      </div>
    </div>
  );
};
