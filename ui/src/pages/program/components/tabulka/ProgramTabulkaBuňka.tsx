import produce from "immer";
import { FunctionComponent } from "preact";
import { Pohlavi } from "../../../../api/přihlášenýUživatel";
import { generujUrl } from "../../../../store/program/logic/url";
import { useAktivita, useUrlStav, useÚčastníkPohlaví } from "../../../../store/program/selektory";
import { nastavUrlAktivitaNáhledId } from "../../../../store/program/slices/urlSlice";
import { volnoTypZObsazenost } from "../../../../utils";
import { Obsazenost } from "./Obsazenost";
import { Přihlašovátko } from "./Přihlašovátko";
import { Aktivita } from "../../../../store/program/slices/programDataSlice";

export const tabulkaBuňkaAktivitaTřídy = (
  aktivita: Aktivita,
  pohlavi: Pohlavi | undefined
) => {
  const classes: string[] = [];
  if (
    aktivita.stavPrihlaseni &&
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
    if (volnoTyp !== "u" && volnoTyp !== "t" && volnoTyp !== pohlavi) {
      classes.push("plno");
    }
  }
  if (!classes.length) {
    classes.push("otevrene")
  }
  classes.push("aktivita")

  return classes.join(" ");
};

type TProgramTabulkaBuňkaProps = {
  aktivitaId: number;
  zobrazLinii?: boolean;
  kompaktní?: boolean;
  /**
   * Název místnosti, ve které se karta vypisuje. V zobrazení po místnostech
   * může být aktivita ve víc sálech zároveň, takže karta musí ukázat právě tu
   * místnost, do jejíhož řádku patří – ne vždy hlavní lokaci (mistnosti[0]).
   */
  lokaceNazev?: string;
};

export const ProgramTabulkaBuňka: FunctionComponent<
  TProgramTabulkaBuňkaProps
> = (props) => {
  const { aktivitaId, zobrazLinii, kompaktní, lokaceNazev } = props;

  const aktivita = useAktivita(aktivitaId);
  const pohlavi = useÚčastníkPohlaví();
  const urlStav = useUrlStav();

  const onAktivitaOdkazKlik = (
    e: JSX.TargetedMouseEvent<HTMLAnchorElement>
  ) => {
    e.preventDefault();
    nastavUrlAktivitaNáhledId(aktivitaId);
  };

  if (!aktivita) return <></>;

  // V zobrazení po místnostech dostaneme název místnosti řádku (lokaceNazev) –
  // jinak (linie/den) padneme zpět na hlavní lokaci aktivity.
  const zobrazenáLokace = lokaceNazev ?? aktivita.mistnosti?.[0]?.nazev;

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
          {zobrazenáLokace && (
            <div class="program_lokace">{zobrazenáLokace}</div>
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
