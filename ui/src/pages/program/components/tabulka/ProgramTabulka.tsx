import { FunctionComponent } from "preact";
import { useEffect, useLayoutEffect, useRef } from "preact/hooks";
import { DNY_NÁZVY_S_HÁČKY } from "../../../../utils";
import { ProgramPosuv } from "./ProgramPosuv";
import {
  připravTabulkuAktivit,
  SeskupováníAktivit,
} from "./seskupování";
import { GAMECON_KONSTANTY } from "../../../../env";
import {
  konecAktivityNaSlotu,
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
import { nastavZvětšeno } from "../../../../store/program/slices/všeobecnéSlice";
import { PřekrývacíNačítač } from "../Načítač";
import { exitFullscreen, requestFullscreen } from "../../../../utils/dom";

type ProgramTabulkaProps = {};

const jeHodinaStartMinuta = (minute: number): boolean =>
  Math.round((minute - ZACATEK_PROGRAMU_V_MINUTACH) / PROGRAM_KROK_CASU_MINUTY) % SLOTU_ZA_HODINU === 0;

// Grid column (1-based): column 1 = linie name, column 2+ = time slots
const minutoNaGridSloupec = (minute: number): number =>
  Math.round((minute - ZACATEK_PROGRAMU_V_MINUTACH) / PROGRAM_KROK_CASU_MINUTY) + 2;

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

  // Řádek 1: hlavička s časovými popisky
  const tabulkaHlavička = (
    <>
      <div class="program_header-nazev" style={{ gridColumn: 1, gridRow: 1 }}></div>
      {PROGRAM_HODINY.map((čas, index) => (
        <div
          key={čas}
          class={"program_header-cas" + (index === 0 ? " program_bunka-hodinaStart" : "")}
          style={{
            gridColumn: `${2 + index * SLOTU_ZA_HODINU} / span ${SLOTU_ZA_HODINU}`,
            gridRow: 1,
          }}
        >
          {čas}:00
        </div>
      ))}
    </>
  );

  const tabulkaŽádnéAktivity = (
    <div class="program_empty-row" style={{ gridColumn: `1 / -1`, gridRow: 2 }}>
      Žádné aktivity tento den
    </div>
  );

  // Řádky začínají od 2 (řádek 1 je hlavička)
  let currentGridRow = 2;

  const tabulkaŘádky = Object.entries(předpřipravenáTabulka)
    .sort((a, b) => indexŘazení(a[0]) - indexŘazení(b[0]))
    .map(([klíč, skupina]) => {
      const řádků: number = Math.max(...skupina.map((x) => x.řádek)) + 1;
      const startRow = currentGridRow;
      currentGridRow += Math.max(řádků, 1);

      const nadpisSkupiny = (
        <div
          class="program_nazevLinie"
          style={{
            gridColumn: 1,
            gridRow: `${startRow} / span ${Math.max(řádků, 1)}`,
          }}
        >
          <span>{klíč}</span>
        </div>
      );

      if (řádků <= 0) {
        return <>{nadpisSkupiny}</>;
      }

      const aktivityBuňky = skupina
        .sort((a, b) => a.aktivita.cas.od - b.aktivita.cas.od)
        .map(({ aktivita, řádek }) => {
          const zacatekAktivity = new Date(aktivita.cas.od);
          const konecAktivity = new Date(aktivita.cas.do);
          const časOd = zacatekAktivityNaSlotu(zacatekAktivity);
          const časDo = konecAktivityNaSlotu(zacatekAktivity, konecAktivity);
          const časOdOřezaný = Math.max(časOd, ZACATEK_PROGRAMU_V_MINUTACH);

          return (
            <ProgramTabulkaBuňka
              key={`${klíč}-${řádek}-${aktivita.id}`}
              aktivitaId={aktivita.id}
              zobrazLinii={seskupPodle === SeskupováníAktivit.den}
              kompaktní={kompaktní}
              jeHodinaStart={jeHodinaStartMinuta(časOdOřezaný)}
              gridColumnStart={minutoNaGridSloupec(časOdOřezaný)}
              gridColumnEnd={minutoNaGridSloupec(časDo)}
              gridRow={startRow + řádek}
            />
          );
        });

      return (
        <>
          {nadpisSkupiny}
          {aktivityBuňky}
        </>
      );
    });

  const obalRef = useRef<HTMLDivElement>(null);

  const aktivitaNáhled = useAktivitaNáhled();

  const zvětšeno = useProgramStore(s => s.všeobecné.zvětšeno);
  const posledniScrollLeft = useRef(0);

  const programNáhledObalProgramuClass =
    "programNahled_obalProgramu"
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

  useEffect(() => {
    const obal = obalRef.current;
    if (!obal) return;

    const ulozScrollLeft = () => {
      posledniScrollLeft.current = obal.scrollLeft;
    };

    ulozScrollLeft();
    obal.addEventListener("scroll", ulozScrollLeft, { passive: true });
    return () => obal.removeEventListener("scroll", ulozScrollLeft);
  }, []);

  useLayoutEffect(() => {
    const obal = obalRef.current;
    if (!obal) return;
    obal.scrollLeft = posledniScrollLeft.current;
  }, [aktivitaNáhled]);

  return (
    <>
      <div ref={obalHlavníRef} class={programNáhledObalProgramuClass}
        style={{
          position: "relative",
          minHeight: 200,
        }}
      >
        <div class="programPosuv_obal2">
          <div class="programPosuv_obal" ref={obalRef}>
            <div
              class="program"
              style={{
                gridTemplateColumns: `calc(var(--program-odsazeni-zleva) + var(--program-sirka-linie) + var(--program-mezera-za-linii)) repeat(${PROGRAM_CASOVE_SLOTY.length}, var(--program-sirka-slotu))`,
              }}
            >
              {tabulkaHlavička}
              {aktivityFiltrované.length || seskupPodle === SeskupováníAktivit.den
                ? tabulkaŘádky
                : tabulkaŽádnéAktivity}
            </div>
          </div>
          <ProgramPosuv {...{ obalRef }} />
        </div>
        <PřekrývacíNačítač zobrazit={aktivityStatus === "načítání"} />
      </div>
    </>
  );
};

ProgramTabulka.displayName = "programNáhled";