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
import {
  useAktivitaNáhled,
  useAktivity,
  useUrlVýběr,
} from "../../../../store/program/selektory";
import { ProgramTabulkaBuňka } from "./ProgramTabulkaBuňka";

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

  const urlStateVýběr = useUrlVýběr();
  const { aktivity, aktivityPřihlášen } = useAktivity();

  const aktivityFiltrované = aktivity.filter((aktivita) =>
    urlStateVýběr.typ === "můj"
      ? aktivityPřihlášen.find((x) => x.id === aktivita.id)?.prihlasen
      : new Date(aktivita.cas.od).getDay() === urlStateVýběr.datum.getDay()
  );

  const seskupPodle =
    urlStateVýběr.typ === "můj"
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

  const tabulkaŘádky = Object.entries(předpřipravenáTabulka)
    .sort((a, b) => indexŘazení(a[0]) - indexŘazení(b[0]))
    .map(([klíč, skupina]) => {
      const řádků: number = Math.max(...skupina.map((x) => x.řádek)) + 1;

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

                    const časOdsazení = hodinOd - posledníAktivitaDo;
                    posledníAktivitaDo = hodinDo;
                    const odsazení = range(časOdsazení).map(() => <td></td>);

                    return (
                      <>
                        {odsazení}
                        <ProgramTabulkaBuňka
                          aktivitaId={aktivita.id}
                          zobrazLinii={seskupPodle === SeskupováníAktivit.den}
                        />
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
    });

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
