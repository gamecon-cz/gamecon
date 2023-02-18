import { FunctionComponent } from "preact";
import { useEffect, useRef } from "preact/hooks";
import {
  useAktivitaNáhled,
  useUživatel,
} from "../../../store/program/selektory";
import { skryjAktivitaNáhledId } from "../../../store/program/slices/urlSlice";
import { Obsazenost } from "./tabulka/Obsazenost";

type ProgramNáhledProps = {};

// TODO: přihlašovátko ?
export const ProgramNáhled: FunctionComponent<ProgramNáhledProps> = (props) => {
  const {} = props;
  const { aktivita, aktivitaPřihlášen } = useAktivitaNáhled();

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
            <div class="programNahled_obsazenost">
              {` `}
              <Obsazenost
                obsazenost={aktivitaPřihlášen?.obsazenost}
                prihlasovatelna={aktivitaPřihlášen?.prihlasovatelna ?? false}
                probehnuta={aktivita?.probehnuta ?? false}
                bezObalu
              />
            </div>
            <div
              class="programNahled_cas"
              dangerouslySetInnerHTML={{ __html: aktivita?.casText ?? "" }}
            ></div>
            <div class="programNahled_cena">
              {aktivitaPřihlášen?.slevaNasobic !== 0 &&
              aktivita?.cenaZaklad !== 0
                ? aktivita?.cenaZaklad != undefined
                  ? aktivita?.cenaZaklad *
                    (aktivitaPřihlášen?.slevaNasobic ?? 1)
                  : " - "
                : "zdarma"}
              <p style={{ opacity: 0.3, display:"inline" }}>
                {aktivitaPřihlášen?.slevaNasobic !== undefined &&
                aktivitaPřihlášen?.slevaNasobic !== 1
                  ? `*(osobni sleva ${
                    (1 - aktivitaPřihlášen?.slevaNasobic) * 100
                  }%)`
                  : undefined}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
