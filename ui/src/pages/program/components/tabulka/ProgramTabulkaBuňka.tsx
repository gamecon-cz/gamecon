import produce from "immer";
import { FunctionComponent } from "preact";
import { APIAktivita, APIAktivitaPřihlášen } from "../../../../api/program";
import { Pohlavi } from "../../../../api/přihlášenýUživatel";
import { generujUrl } from "../../../../store/program/logic/url";
import { useAktivita, useUrlState, useUživatelPohlaví } from "../../../../store/program/selektory";
import { nastavUrlAktivitaNáhledId } from "../../../../store/program/slices/urlSlice";
import { volnoTypZObsazenost } from "../../../../utils";
import { Obsazenost } from "./Obsazenost";
import { Přihlašovátko } from "./Přihlašovátko";

const aktivitaTřídy = (
  aktivita: APIAktivita,
  aktivitaPřihlášen: APIAktivitaPřihlášen,
  pohlavi: Pohlavi | undefined
) => {
  const classes: string[] = [];
  if (
    aktivitaPřihlášen.stavPrihlaseni != undefined &&
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

  if (aktivitaPřihlášen.obsazenost) {
    const volnoTyp = volnoTypZObsazenost(aktivitaPřihlášen.obsazenost);
    if (volnoTyp !== "u" && volnoTyp !== pohlavi) {
      classes.push("plno");
    }
  }
  return classes.join(" ");
};

type TProgramTabulkaBuňkaProps = {
  aktivitaId: number;
  zobrazLinii?: boolean;
};

export const ProgramTabulkaBuňka: FunctionComponent<
  TProgramTabulkaBuňkaProps
> = (props) => {
  const { aktivitaId, zobrazLinii } = props;

  const {aktivita, aktivitaPřihlášen} = useAktivita(aktivitaId);
  const pohlavi = useUživatelPohlaví();
  const urlState = useUrlState();

  const onAktivitaOdkazKlik = (
    e: JSX.TargetedMouseEvent<HTMLAnchorElement>
  ) => {
    e.preventDefault();
    nastavUrlAktivitaNáhledId(aktivitaId);
  };

  if (!aktivita || !aktivitaPřihlášen) return <></>;

  const hodinOd = new Date(aktivita.cas.od).getHours();
  const hodinDo = new Date(aktivita.cas.do).getHours();
  const rozsah = hodinDo - hodinOd;

  return (
    <>
      <td colSpan={rozsah}>
        <div class={aktivitaTřídy(aktivita, aktivitaPřihlášen, pohlavi)}>
          <a
            href={generujUrl(
              produce(urlState, (s) => {
                s.aktivitaNáhledId = aktivita.id;
              })
            )}
            class="programNahled_odkaz"
            onClick={onAktivitaOdkazKlik}
          >
            {aktivita.nazev}
          </a>
          <Obsazenost
            obsazenost={aktivitaPřihlášen.obsazenost}
            prihlasovatelna={aktivitaPřihlášen.prihlasovatelna ?? false}
            probehnuta={aktivita.probehnuta ?? false}
          />
          <Přihlašovátko akitivitaId={aktivita.id} />
          {(aktivitaPřihlášen.mistnost || undefined) && (
            <div class="program_lokace">{aktivitaPřihlášen.mistnost}</div>
          )}
          {zobrazLinii ? (
            <span class="program_osobniTyp">{aktivita.linie}</span>
          ) : undefined}
        </div>
      </td>
    </>
  );
};

ProgramTabulkaBuňka.displayName = "ProgramTabulkaBuňka";
