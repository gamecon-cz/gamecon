import { FunctionComponent } from "preact";
import { useRef } from "preact/hooks";
import { DNY_NÁZVY_S_HÁČKY, range } from "../../../../utils";
import { ProgramPosuv } from "./ProgramPosuv";
import {
  připravTabulkuAktivit,
  SeskupováníAktivit,
} from "./seskupování";
import { GAMECON_KONSTANTY } from "../../../../env";
import {
  useAktivitaNáhled,
  useAktivityFiltrované,
  useAktivityStatus,
  useUrlVýběr,
} from "../../../../store/program/selektory";
import { ProgramTabulkaBuňka } from "./ProgramTabulkaBuňka";
import { useProgramStore } from "../../../../store/program";
import { useEffect } from "react";
import { nastavZvětšeno } from "../../../../store/program/slices/všeobecnéSlice";
import { PřekrývacíNačítač } from "../Načítač";
import { exitFullscreen, requestFullscreen } from "../../../../utils/dom";

type ProgramTabulkaProps = {};

const PROGRAM_ČASY =
  GAMECON_KONSTANTY.PROGRAM_ZACATEK < GAMECON_KONSTANTY.PROGRAM_KONEC
    ? range(GAMECON_KONSTANTY.PROGRAM_ZACATEK, GAMECON_KONSTANTY.PROGRAM_KONEC)
    : range(GAMECON_KONSTANTY.PROGRAM_ZACATEK, 24).concat(
      range(0, GAMECON_KONSTANTY.PROGRAM_KONEC)
    );

const indexŘazení = (klíč: string) => {
  const index = GAMECON_KONSTANTY.PROGRAM_ŘAZENÍ_LINIE.findIndex(
    (x) => x === klíč
  );
  const indexDen = DNY_NÁZVY_S_HÁČKY.findIndex((x) => x === klíč);

  return index !== -1 ? index : indexDen !== -1 ? indexDen : 1000;
};

export const ProgramTabulka: FunctionComponent<ProgramTabulkaProps> = (
  props
) => {
  const { } = props;

  const urlStavVýběr = useUrlVýběr();
  const aktivityFiltrované = useAktivityFiltrované();
  const aktivityStatus = useAktivityStatus();

  const kompaktní = useProgramStore(s => s.všeobecné.kompaktní);

  const seskupPodle =
    urlStavVýběr.typ === "můj"
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

            let posledníAktivitaDo = GAMECON_KONSTANTY.PROGRAM_ZACATEK;
            return (
              <tr key={nadpisSkupiny}>
                {klíčSkupiny}
                {skupina
                  .filter((x) => x.řádek === řádek)
                  .map((x) => x.aktivita)
                  .sort((a1, a2) => a1.cas.od - a2.cas.od)
                  .map((aktivita) => {
                    const hodinOd = new Date(aktivita.cas.od).getHours();
                    const hodinDo = new Date(aktivita.cas.do).getHours();

                    const časOdsazení = (hodinOd - posledníAktivitaDo + 24) % 24;
                    const odsazení = časOdsazení > 0
                        ? <td colSpan={časOdsazení}></td>
                        : <></>;
                    posledníAktivitaDo = hodinDo;

                    return (
                      <>
                        {odsazení}
                        <ProgramTabulkaBuňka
                          aktivitaId={aktivita.id}
                          zobrazLinii={seskupPodle === SeskupováníAktivit.den}
                          kompaktní={kompaktní}
                        />
                      </>
                    );
                  })}
                {
                  <td colSpan={(GAMECON_KONSTANTY.PROGRAM_KONEC - posledníAktivitaDo + 24) % 24}></td>
                }
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

  const zvětšeno = useProgramStore(s => s.všeobecné.zvětšeno);

  const programNáhledObalProgramuClass =
    "programNahled_obalProgramu"
    + (aktivitaNáhled ? " programNahled_obalProgramu-zuzeny" : "")
    + (zvětšeno ? " programNahled_obalProgramu-zvetseny" : "")
    ;

  const obalHlavníRef = useRef<HTMLDivElement>(null);
  const posledníZvětšeno = useRef(false);

  useEffect(() => {
    if (!obalHlavníRef.current) return;
    const nastavZvětšenoPodleDokumentu = () => {
      nastavZvětšeno(!!document.fullscreenElement);
    };
    obalHlavníRef.current.addEventListener("fullscreenchange", nastavZvětšenoPodleDokumentu);
    return () => obalHlavníRef.current?.removeEventListener("fullscreenchange", nastavZvětšenoPodleDokumentu);
  });

  useEffect(() => {
    const element = obalHlavníRef.current;
    if (!element) return;

    if (posledníZvětšeno.current === zvětšeno) return;
    posledníZvětšeno.current = zvětšeno;

    if (zvětšeno) {
      requestFullscreen(element);
    } else {
      exitFullscreen(element);
    }
  }, [zvětšeno]);

  return (
    <>
      <div ref={obalHlavníRef} class={programNáhledObalProgramuClass}
        style={{
          position:"relative",
          // místo pro načítač
          minHeight: 200,
        }}
      >
        <div class="programPosuv_obal2">
          <div class="programPosuv_obal" ref={obalRef}>
            <table class="program">
              <tbody>{tabulka}</tbody>
            </table>
          </div>
          <ProgramPosuv {...{ obalRef }} />
        </div>
        <PřekrývacíNačítač zobrazit={aktivityStatus === "načítání"} />
      </div>
    </>
  );
};

ProgramTabulka.displayName = "programNáhled";
