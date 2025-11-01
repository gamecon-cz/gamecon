import produce from "immer";
import { FunctionComponent } from "preact";
import { Pohlavi } from "../../../../api/přihlášenýUživatel";
import { generujUrl } from "../../../../store/program/logic/url";
import { useAktivita, useUrlStav, useUživatelPohlaví } from "../../../../store/program/selektory";
import { nastavUrlAktivitaNáhledId } from "../../../../store/program/slices/urlSlice";
import { volnoTypZObsazenost } from "../../../../utils";
import { Obsazenost } from "./Obsazenost";
import { Přihlašovátko } from "./Přihlašovátko";
import { Aktivita } from "../../../../store/program/slices/programDataSlice";
import { useState } from "preact/hooks";
import { fetchAktivitaTýmKód } from "../../../../api/program";

export const tabulkaBuňkaAktivitaTřídy = (
  aktivita: Aktivita,
  pohlavi: Pohlavi | undefined
) => {
  const classes: string[] = [];
  if (
    aktivita.stavPrihlaseni != undefined &&
    aktivita.stavPrihlaseni !== "sledujici"
  ) {
    classes.push("prihlasen");
  }
  if (aktivita.vedu) {
    classes.push("organizator");
  }
  if (aktivita.stavPrihlaseni === "sledujici") {
    classes.push("sledujici");
  }
  if (aktivita.vdalsiVlne) {
    classes.push("vDalsiVlne");
  }
  if (aktivita.vBudoucnu) {
    classes.push("vBudoucnu");
  }

  if (aktivita.obsazenost) {
    const volnoTyp = volnoTypZObsazenost(aktivita.obsazenost);
    if (volnoTyp !== "u" && volnoTyp !== pohlavi) {
      classes.push("plno");
    }
  }
  return classes.join(" ");
};

type TProgramTabulkaBuňkaProps = {
  aktivitaId: number;
  zobrazLinii?: boolean;
  kompaktní?: boolean;
};

export const ProgramTabulkaBuňka: FunctionComponent<
  TProgramTabulkaBuňkaProps
> = (props) => {
  const { aktivitaId, zobrazLinii, kompaktní } = props;

  const aktivita = useAktivita(aktivitaId);
  const pohlavi = useUživatelPohlaví();
  const urlStav = useUrlStav();

  const onAktivitaOdkazKlik = (
    e: JSX.TargetedMouseEvent<HTMLAnchorElement>
  ) => {
    e.preventDefault();
    nastavUrlAktivitaNáhledId(aktivitaId);
  };

  if (!aktivita) return <></>;

  const hodinOd = new Date(aktivita.cas.od).getHours();
  const hodinDo = new Date(aktivita.cas.do).getHours();
  const rozsah = (hodinDo - hodinOd + 24) % 24;

  return !kompaktní ? (
    <>
      <td colSpan={rozsah}>
        <div class={tabulkaBuňkaAktivitaTřídy(aktivita, pohlavi)}>
          <a
            href={generujUrl(
              produce(urlStav, (s) => {
                s.aktivitaNáhledId = aktivita.id;
              })
            )}
            class="programNahled_odkaz"
            onClick={onAktivitaOdkazKlik}
          >
            {aktivita.nazev}
          </a>
          <Obsazenost
            obsazenost={aktivita.obsazenost}
            prihlasovatelna={aktivita.prihlasovatelna ?? false}
            probehnuta={aktivita.probehnuta ?? false}
          />
          <Přihlašovátko akitivitaId={aktivita.id} />
          {(aktivita.mistnost || undefined) && (
            <div class="program_lokace">{aktivita.mistnost}</div>
          )}
          {zobrazLinii ? (
            <span class="program_osobniTyp">{aktivita.linie}</span>
          ) : undefined}
        </div>
      </td>
    </>
  ) : (
    <>
      <td colSpan={rozsah}>
        <div class={"kompaktni " + tabulkaBuňkaAktivitaTřídy(aktivita, pohlavi)} >
          <a
            href={generujUrl(
              produce(urlStav, (s) => {
                s.aktivitaNáhledId = aktivita.id;
              })
            )}
            class="programNahled_odkaz"
            onClick={onAktivitaOdkazKlik}
          >
            {aktivita.nazev}
          </a>
        </div>
      </td>
    </>
  );
};

ProgramTabulkaBuňka.displayName = "ProgramTabulkaBuňka";
