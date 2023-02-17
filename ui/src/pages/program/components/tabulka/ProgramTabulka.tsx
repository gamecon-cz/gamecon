import { FunctionComponent } from "preact";
import { useRef } from "preact/hooks";
import { range, volnoTypZObsazenost } from "../../../../utils";
import { ProgramPosuv } from "./ProgramPosuv";
import {
  PROGRAM_DNY_TEXT,
  připravTabulkuAktivit,
  SeskupováníAktivit,
} from "./seskupování";
import { GAMECON_KONSTANTY } from "../../../../env";
import { useProgramStore } from "../../../../store/program";
import {
  useAktivitaNáhled,
  useAktivity,
} from "../../../../store/program/selektory";
import produce from "immer";
import { Přihlašovátko } from "./Přihlašovátko";
import { AktivitaPřihlášen } from "../../../../api/program";
import { generujUrl } from "../../../../store/program/logic/url";

type ProgramTabulkaProps = {};

const ZAČÁTEK_AKTIVIT = 8;
const KONEC_AKITIVIT = 24;

const PROGRAM_ČASY = range(ZAČÁTEK_AKTIVIT, KONEC_AKITIVIT);

const indexŘazení = (klíč: string) => {
  const index = GAMECON_KONSTANTY.PROGRAM_ŘAZENÍ_LINIE.findIndex(
    (x) => x === klíč
  );
  const indexDen = PROGRAM_DNY_TEXT.findIndex((x) => x === klíč);

  return index !== -1 ? index : indexDen !== -1 ? indexDen : 1000;
};

export const ProgramTabulka: FunctionComponent<ProgramTabulkaProps> = (
  props
) => {
  const {} = props;

  const urlState = useProgramStore((s) => s.urlState);
  const { aktivity, aktivityPřihlášen } = useAktivity();
  const uživatel = useProgramStore((s) => s.přihlášenýUživatel.data);

  const aktivityFiltrované = aktivity.filter((aktivita) =>
    urlState.výběr.typ === "můj"
      ? aktivityPřihlášen.find((x) => x.id === aktivita.id)?.prihlasen
      : new Date(aktivita.cas.od).getDay() === urlState.výběr.datum.getDay()
  );

  const seskupPodle =
    urlState.výběr.typ === "můj"
      ? SeskupováníAktivit.den
      : SeskupováníAktivit.linie;

  const předpřipravenáTabulka = připravTabulkuAktivit(
    aktivityFiltrované,
    seskupPodle
  );

  const tabulkaHlavičkaČasy = (
    <tr>
      <th></th>
      {PROGRAM_ČASY.map((čas) => (
        <th>{čas}:00</th>
      ))}
    </tr>
  );

  const tabulkaŽádnéAktivity = (
    <tr>
      <td colSpan={PROGRAM_ČASY.length + 1}>Žádné aktivity tento den</td>
    </tr>
  );

  const tabulkaŘádky = (
    <>
      {Object.entries(předpřipravenáTabulka)
        .sort((a, b) => indexŘazení(a[0]) - indexŘazení(b[0]))
        .map(([klíč, skupina]) => {
          const řádků = Math.max(...skupina.map((x) => x.řádek)) + 1;

          const nadpisSkupiny = (
            <td rowSpan={Math.max(řádků, 1)}>
              <div class="program_nazevLinie">{klíč}</div>
            </td>
          );

          if (řádků <= 0) {
            return (
              <tr>
                {nadpisSkupiny}
                <td></td>
              </tr>
            );
          }

          return (
            <>
              {range(řádků).map((řádek) => {
                const klíčSkupiny = řádek === 0 ? nadpisSkupiny : <></>;

                let posledníAktivitaDo = ZAČÁTEK_AKTIVIT;
                return (
                  <tr>
                    {klíčSkupiny}
                    {skupina
                      .filter((x) => x.řádek === řádek)
                      .map((x) => x.aktivita)
                      .sort((a1, a2) => a1.cas.od - a2.cas.od)
                      .map((aktivita) => {
                        const hodinOd = new Date(aktivita.cas.od).getHours();
                        const hodinDo = new Date(aktivita.cas.do).getHours();
                        const rozsah = hodinDo - hodinOd;
                        const aktivitaPřihlášen =
                          aktivityPřihlášen.find((x) => x.id === aktivita.id) ??
                          ({ id: aktivita.id } as AktivitaPřihlášen);

                        const classes: string[] = [];
                        if (
                          aktivitaPřihlášen.prihlasen &&
                          aktivitaPřihlášen.stavPrihlaseni !== "sledujici"
                        ) {
                          classes.push("prihlasen");
                        }
                        if (aktivitaPřihlášen.vedu) {
                          classes.push("organizator");
                        }
                        if (aktivitaPřihlášen.stavPrihlaseni === "sledujici") {
                          classes.push("sledujici");
                        }
                        if (aktivita.vdalsiVlne) {
                          classes.push("vDalsiVlne");
                        }
                        if (aktivita.vBudoucnu) {
                          classes.push("vBudoucnu");
                        }

                        let aktivitaObsazenost: JSX.Element | undefined =
                          undefined;
                        if (aktivitaPřihlášen.obsazenost) {
                          const { m, f, km, kf, ku } =
                            aktivitaPřihlášen.obsazenost;
                          const volnoTyp = volnoTypZObsazenost(
                            aktivitaPřihlášen.obsazenost
                          );
                          if (
                            volnoTyp !== "u" &&
                            volnoTyp !== uživatel.pohlavi
                          ) {
                            classes.push("plno");
                          }

                          const celkem = m + f;
                          const kapacitaCelkem = km + kf + ku;
                          // TODO: jak poznám aktivitu bez omezení ?
                          if (kapacitaCelkem) {
                            if (
                              !aktivitaPřihlášen.prihlasovatelna &&
                              !aktivita.probehnuta
                            ) {
                              aktivitaObsazenost = (
                                <span class="neprihlasovatelna">
                                  {`(${celkem}/${kapacitaCelkem})`}
                                </span>
                              );
                            }

                            switch (volnoTyp) {
                              case "u":
                              case "x":
                                aktivitaObsazenost = (
                                  <>{`(${celkem}/${kapacitaCelkem})`}</>
                                );
                                break;
                              case "f":
                              case "m":
                                aktivitaObsazenost = (
                                  <>
                                    <span class="f">{`(${f}/${
                                      kf + (volnoTyp === "m" ? ku : 0)
                                    })`}</span>
                                    <span class="m">{`(${m}/${
                                      km + (volnoTyp === "f" ? ku : 0)
                                    })`}</span>
                                  </>
                                );
                                break;
                              default:
                                aktivitaObsazenost = <>{` (${f + m}/${ku})`}</>;
                                break;
                            }
                            if (aktivitaObsazenost) {
                              aktivitaObsazenost = (
                                <span class="program_obsazenost">
                                  {aktivitaObsazenost}
                                </span>
                              );
                            }
                          }
                        }

                        const časOdsazení = hodinOd - posledníAktivitaDo;
                        posledníAktivitaDo = hodinDo;
                        return (
                          <>
                            {range(časOdsazení).map(() => (
                              <td></td>
                            ))}
                            <td colSpan={rozsah}>
                              <div class={classes.join(" ")}>
                                <a
                                  href={generujUrl(
                                    produce(urlState, (s) => {
                                      s.aktivitaNáhledId = aktivita.id;
                                    })
                                  )}
                                  class="programNahled_odkaz"
                                  onClick={(e) => {
                                    e.preventDefault();
                                    useProgramStore.setState(
                                      (s) => {
                                        s.urlState.aktivitaNáhledId =
                                          // TODO: pěkná pyramida -> refactor
                                          aktivita.id;
                                      },
                                      undefined,
                                      "tabulka akitvita klik"
                                    );
                                  }}
                                >
                                  {aktivita.nazev}
                                </a>
                                {aktivitaObsazenost}
                                {` `}<Přihlašovátko akitivitaId={aktivita.id} />
                                {(aktivitaPřihlášen.mistnost || undefined) && (
                                  <div class="program_lokace">
                                    {aktivitaPřihlášen.mistnost}
                                  </div>
                                )}
                                {seskupPodle === SeskupováníAktivit.den ? (
                                  <span class="program_osobniTyp">
                                    {aktivita.linie}
                                  </span>
                                ) : undefined}
                              </div>
                            </td>
                          </>
                        );
                      })}
                    {posledníAktivitaDo > 0
                      ? range(KONEC_AKITIVIT - posledníAktivitaDo).map(() => (
                          <td></td>
                        ))
                      : undefined}
                  </tr>
                );
              })}
            </>
          );
        })}
    </>
  );

  const tabulka = (
    <>
      {tabulkaHlavičkaČasy}
      {aktivityFiltrované.length || seskupPodle === SeskupováníAktivit.den
        ? tabulkaŘádky
        : tabulkaŽádnéAktivity}
    </>
  );

  const obalRef = useRef<HTMLDivElement>(null);

  const aktivitaNáhled = useAktivitaNáhled();

  const programNáhledObalProgramuClass =
    "programNahled_obalProgramu" +
    (aktivitaNáhled ? " programNahled_obalProgramu-zuzeny" : "");

  return (
    <>
      <div class={programNáhledObalProgramuClass}>
        <div class="programPosuv_obal2">
          <div class="programPosuv_obal" ref={obalRef}>
            <table class="program">
              <tbody>{tabulka}</tbody>
            </table>
          </div>
          <ProgramPosuv {...{ obalRef }} />
        </div>
      </div>
    </>
  );
};

ProgramTabulka.displayName = "programNáhled";
