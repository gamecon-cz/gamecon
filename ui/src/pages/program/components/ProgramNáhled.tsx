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
              {tagyZId(aktivita?.stitkyId, tagy).map((x) => (
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
                obsazenost={aktivita?.obsazenost}
                prihlasovatelna={aktivita?.prihlasovatelna ?? false}
                probehnuta={aktivita?.probehnuta ?? false}
                tymPocetClenu={aktivita?.tymPocetClenu}
                tymLimit={aktivita?.tymLimit}
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
                  : aktivita.cenaZaklad * (aktivita.slevaNasobic ?? 1)}
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
