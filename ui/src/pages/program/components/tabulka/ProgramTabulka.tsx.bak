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
  konecAktivityNaSlotu,
  KONEC_PROGRAMU_V_MINUTACH,
  PROGRAM_CASOVE_SLOTY,
  PROGRAM_HODINY,
  PROGRAM_KROK_CASU_MINUTY,
  SLOTU_ZA_HODINU,
  ZACATEK_PROGRAMU_V_MINUTACH,
  zacatekAktivityNaSlotu,
} from "./casoveSloty";
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

const jeHodinaStartMinuta = (minute: number): boolean =>
  Math.round((minute - ZACATEK_PROGRAMU_V_MINUTACH) / PROGRAM_KROK_CASU_MINUTY) % SLOTU_ZA_HODINU === 0;

const generujSpacerBuňky = (odMinuty: number, doMinuty: number) => {
  const buňky = [];
  let pos = odMinuty;
  while (pos < doMinuty) {
    const jeStart = jeHodinaStartMinuta(pos);
    const slotIndex = Math.round((pos - ZACATEK_PROGRAMU_V_MINUTACH) / PROGRAM_KROK_CASU_MINUTY);
    const slotyDoKonceHodiny = jeStart ? SLOTU_ZA_HODINU : SLOTU_ZA_HODINU - (slotIndex % SLOTU_ZA_HODINU);
    const konec = Math.min(pos + slotyDoKonceHodiny * PROGRAM_KROK_CASU_MINUTY, doMinuty);
    const colSpan = Math.round((konec - pos) / PROGRAM_KROK_CASU_MINUTY);
    if (colSpan > 0) {
      buňky.push(
        <td key={pos} colSpan={colSpan} class={jeStart ? "program_bunka-hodinaStart" : undefined}></td>
      );
    }
    pos = konec;
  }
  return buňky;
};

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
      {PROGRAM_HODINY.map((čas) => (
        <th colSpan={SLOTU_ZA_HODINU}>{čas}:00</th>
      ))}
    </tr>
  );

  const tabulkaŽádnéAktivity = (
    <tr>
      <td colSpan={PROGRAM_CASOVE_SLOTY.length + 1}>Žádné aktivity tento den</td>
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
            {generujSpacerBuňky(ZACATEK_PROGRAMU_V_MINUTACH, KONEC_PROGRAMU_V_MINUTACH)}
          </tr>
        );
      }

      return (
        <>
          {range(řádků).map((řádek) => {
            const klíčSkupiny = řádek === 0 ? nadpisSkupiny : <></>;

            let posledníAktivitaDo = ZACATEK_PROGRAMU_V_MINUTACH;
            return (
              <tr key={`${klíč}-${řádek}`}>
                {klíčSkupiny}
                {skupina
                  .filter((x) => x.řádek === řádek)
                  .map((x) => x.aktivita)
                  .sort((a1, a2) => a1.cas.od - a2.cas.od)
                  .map((aktivita) => {
                    const zacatekAktivity = new Date(aktivita.cas.od);
                    const konecAktivity = new Date(aktivita.cas.do);
                    const časOd = zacatekAktivityNaSlotu(zacatekAktivity);
                    const časDo = konecAktivityNaSlotu(zacatekAktivity, konecAktivity);
                    const časOdOřezaný = Math.max(
                      časOd,
                      ZACATEK_PROGRAMU_V_MINUTACH,
                    );
                    const odsazení = generujSpacerBuňky(posledníAktivitaDo, časOdOřezaný);
                    posledníAktivitaDo = Math.max(posledníAktivitaDo, časDo);

                    return (
                      <>
                        {odsazení}
                        <ProgramTabulkaBuňka
                          aktivitaId={aktivita.id}
                          zobrazLinii={seskupPodle === SeskupováníAktivit.den}
                          kompaktní={kompaktní}
                          jeHodinaStart={jeHodinaStartMinuta(časOdOřezaný)}
                        />
                      </>
                    );
                  })}
                {generujSpacerBuňky(posledníAktivitaDo, KONEC_PROGRAMU_V_MINUTACH)}
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

  const tabulkaSloupce = (
    <colgroup>
      <col class="program_col-linie" />
      {PROGRAM_CASOVE_SLOTY.map((slot) => (
        <col key={slot} class="program_col-slot" />
      ))}
    </colgroup>
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
              {tabulkaSloupce}
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
