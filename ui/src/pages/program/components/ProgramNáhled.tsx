import { FunctionComponent } from "preact";
import { useEffect, useRef } from "preact/hooks";
import {
  useAktivitaNáhled, useTagy,
} from "../../../store/program/selektory";
import { skryjAktivitaNáhledId } from "../../../store/program/slices/urlSlice";
import { Obsazenost } from "./tabulka/Obsazenost";
import { tagyZId } from "../../../utils";

type ProgramNáhledProps = {};

// TODO: přihlašovátko ?
export const ProgramNáhled: FunctionComponent<ProgramNáhledProps> = (props) => {
  const {} = props;
  const aktivita = useAktivitaNáhled();
  const tagy = useTagy();

  const programNáhledObsahRef = useRef<HTMLDivElement>(null);

  const obalClass =
    "programPreview_obalNahledu" +
    (aktivita
      ? " programPreview_obalNahledu-viditelny programPreview_obalNahledu-maData"
      : "");

  useEffect(() => {
    programNáhledObsahRef.current?.scrollTo?.({ top: 0, left: 0, behavior: "auto" });
  }, [aktivita]);

  return (
    <div class={obalClass}>
      <div class="programPreview_nahled">
        <div class="programPreview_placeholder" onClick={skryjAktivitaNáhledId}></div>

        <div class="programPreview_obsah" ref={programNáhledObsahRef}>
          <div
            class="programNahled_zaviratko"
            onClick={skryjAktivitaNáhledId}
          ></div>
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
              {tagyZId(aktivita?.stitkyId, tagy).map((x) => (
                <div class="programNahled_stitek">{x}</div>
              ))}
            </div>
          </div>
          <div class="programNahled_text">
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
            <div class="programNahled_obsazenost">
              {` `}
              <Obsazenost
                obsazenost={aktivita?.obsazenost}
                prihlasovatelna={aktivita?.prihlasovatelna ?? false}
                probehnuta={aktivita?.probehnuta ?? false}
                bezObalu
              />
            </div>
            <div
              class="programNahled_cas"
              dangerouslySetInnerHTML={{ __html: aktivita?.casText ?? "" }}
            ></div>
            <div class="programNahled_cena">
              {aktivita === undefined
                ? " - "
                : aktivita.slevaNasobic === 0 || aktivita.cenaZaklad === 0
                  ? "zdarma"
                  : aktivita.cenaZaklad * aktivita.slevaNasobic}
              <p style={{ opacity: 0.3 }}>
                {aktivita !== undefined && aktivita.slevaNasobic !== 1
                  ? `*${aktivita.cenaZaklad === 0 ? "zdarma" : aktivita.cenaZaklad}`
                  : undefined}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
