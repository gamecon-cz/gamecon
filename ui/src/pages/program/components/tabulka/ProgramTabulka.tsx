import { FunctionComponent } from "preact";
import { useRef } from "preact/hooks";
import { DNY_NÁZVY_S_HÁČKY, formátujDenVTýdnu, range } from "../../../../utils";
import { ProgramPosuv } from "./ProgramPosuv";
import {
  připravTabulkuAktivit,
  SeskupováníAktivit,
  PředpřivenáTabulkaAktivit,
  PředpřivenáTabulkaAktivitHierarchie,
} from "./seskupování";
import { GAMECON_KONSTANTY } from "../../../../env";
import {
  useAktivitaNáhled,
  useAktivityFiltrované,
  useAktivityStatus,
  useUrlStav,
  useUrlVýběr,
} from "../../../../store/program/selektory";
import { ProgramTabulkaBuňka } from "./ProgramTabulkaBuňka";
import { useProgramStore } from "../../../../store/program";
import { useEffect } from "react";
import { nastavZvětšeno } from "../../../../store/program/slices/všeobecnéSlice";
import { PřekrývacíNačítač } from "../Načítač";
import { exitFullscreen, requestFullscreen } from "../../../../utils/dom";
import { pražskéHodiny } from "../../../../utils/czech-time";

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

  const urlStav = useUrlStav();
  const urlStavVýběr = useUrlVýběr();
  const aktivityFiltrované = useAktivityFiltrované();
  const aktivityStatus = useAktivityStatus();

  const kompaktní = useProgramStore(s => s.všeobecné.kompaktní);

  const seskupPodle =
    urlStav.podleMístnosti
      ? SeskupováníAktivit.mistnost
      : urlStavVýběr.typ === "všechny_dny"
      ? SeskupováníAktivit.denALinie
      : urlStavVýběr.typ === "můj"
      ? SeskupováníAktivit.den
      : SeskupováníAktivit.linie;

  // Je aktivní nějaké omezení obsahu (mimo výběr dne)? Když ano, kostru prázdných
  // místností nevyplňujeme – jinak by filtr vizuálně „nic nedělal“, protože by
  // prázdné řádky zůstaly zobrazené bez ohledu na to, co filtr vyhodil. Výběr
  // „Můj program“ je taky omezení obsahu (aktivity se filtrují na přihlášené),
  // takže s ním kostru prázdných místností taky nevyplňujeme.
  const obsahovýFiltrAktivní = !!(
    urlStavVýběr.typ === "můj"
    || urlStav.filtrLinie?.length
    || urlStav.filtrTagy?.length
    || urlStav.filtrText
    || urlStav.filtrStavAktivit?.length
    || urlStav.filtrPřihlašovatelné
  );

  // V zobrazení po místnostech s aktivním přepínačem „Prázdné“ doplníme i
  // místnosti bez aktivit, aby tabulka odpovídala kompletnímu rozpisu místností.
  // Seznam místností ze serveru platí pro aktuální ročník, takže ho použijeme
  // jen když je zobrazený právě ten – u starších ročníků by místnosti neseděly.
  const prázdnéMístnosti =
    seskupPodle === SeskupováníAktivit.mistnost
      && urlStav.zobrazPrázdné
      && urlStav.ročník === GAMECON_KONSTANTY.ROCNIK
      && !obsahovýFiltrAktivní
      ? GAMECON_KONSTANTY.PROGRAM_MISTNOSTI.map((místnost) => místnost.nazev)
      : [];

  // V hierarchickém zobrazení (po dnech) ukazujeme jen vybraný den, pokud je
  // zvolený konkrétní den – jinak by přepínač dnů „nic nedělal“, protože by se
  // pořád renderovaly všechny dny (zvlášť s vyplněnou kostrou místností).
  const zobrazitDenVHierarchii = (denKlíč: string): boolean =>
    urlStavVýběr.typ !== "den"
      ? true
      : denKlíč === formátujDenVTýdnu(urlStavVýběr.datum, true);

  const předpřipravenáTabulka = připravTabulkuAktivit(
    aktivityFiltrované,
    seskupPodle,
    prázdnéMístnosti
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

  const vytvořŘádkyZeSeskupiny = (tabulka: PředpřivenáTabulkaAktivit, zobrazitLinii: boolean, jeMístnost = false) =>
    Object.entries(tabulka)
      .sort((a, b) => indexŘazení(a[0]) - indexŘazení(b[0]))
      .flatMap(([klíč, skupina]) => {
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

        return range(řádků).map((řádek) => {
          const klíčSkupiny = řádek === 0 ? nadpisSkupiny : <></>;

          let posledníAktivitaDo = GAMECON_KONSTANTY.PROGRAM_ZACATEK;
          return (
            <tr key={`${klíč}-${řádek}`}>
              {klíčSkupiny}
              {skupina
                .filter((x) => x.řádek === řádek)
                .map((x) => x.aktivita)
                .sort((a1, a2) => a1.cas.od - a2.cas.od)
                .map((aktivita) => {
                  const hodinOd = pražskéHodiny(new Date(aktivita.cas.od));
                  const hodinDo = pražskéHodiny(new Date(aktivita.cas.do));

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
                        zobrazLinii={zobrazitLinii}
                        kompaktní={kompaktní}
                        lokaceNazev={jeMístnost ? klíč : undefined}
                      />
                    </>
                  );
                })}
              {
                <td colSpan={(GAMECON_KONSTANTY.PROGRAM_KONEC - posledníAktivitaDo + 24) % 24}></td>
              }
            </tr>
          );
        });
      });

  const tabulkaŘádky =
    (seskupPodle === SeskupováníAktivit.denALinie || seskupPodle === SeskupováníAktivit.mistnost)
      ? Object.entries(předpřipravenáTabulka as PředpřivenáTabulkaAktivitHierarchie)
          .filter(([denKlíč]) => zobrazitDenVHierarchii(denKlíč))
          .flatMap(([denKlíč, tabulkaProDen]) => [
            <tr key={`nadpis-${denKlíč}`}>
              <td colSpan={PROGRAM_ČASY.length + 1}>
                <div class="program_den_nadpis">{denKlíč}</div>
              </td>
            </tr>,
            ...vytvořŘádkyZeSeskupiny(tabulkaProDen, true, seskupPodle === SeskupováníAktivit.mistnost),
          ])
      : vytvořŘádkyZeSeskupiny(předpřipravenáTabulka as PředpřivenáTabulkaAktivit, seskupPodle === SeskupováníAktivit.den);

  const tabulka = (
    <>
      {tabulkaHlavičkaČasy}
      {aktivityFiltrované.length
        || seskupPodle === SeskupováníAktivit.den
        || seskupPodle === SeskupováníAktivit.denALinie
        || (seskupPodle === SeskupováníAktivit.mistnost && prázdnéMístnosti.length)
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
