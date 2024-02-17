import produce from "immer";
import { FunctionComponent } from "preact";
import { generujUrl } from "../../../../store/program/logic/url";
import { useAktivita, useUrlState, useUživatelPohlaví } from "../../../../store/program/selektory";
import { nastavUrlAktivitaNáhledId } from "../../../../store/program/slices/urlSlice";
import { tabulkaBuňkaAktivitaTřídy } from "./ProgramTabulkaBuňka";

type TProgramTabulkaBuňkaKompaktníProps = {
  aktivitaId: number;
  zobrazLinii?: boolean;
};

export const ProgramTabulkaBuňkaKompaktní: FunctionComponent<
  TProgramTabulkaBuňkaKompaktníProps
> = (props) => {
  const { aktivitaId } = props;

  const aktivita = useAktivita(aktivitaId);
  const pohlavi = useUživatelPohlaví();
  const urlState = useUrlState();

  const onAktivitaOdkazKlik = (
    e: JSX.TargetedMouseEvent<HTMLAnchorElement>
  ) => {
    e.preventDefault();
    nastavUrlAktivitaNáhledId(aktivitaId);
  };

  if (!aktivita) return <></>;

  const hodinOd = new Date(aktivita.cas.od).getHours();
  const hodinDo = new Date(aktivita.cas.do).getHours();
  const rozsah = hodinDo - hodinOd;

  return (
    <>
      <td colSpan={rozsah}>
        <div class={"kompaktni " + tabulkaBuňkaAktivitaTřídy(aktivita, pohlavi)} >
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
        </div>
      </td>
    </>
  );
};

ProgramTabulkaBuňkaKompaktní.displayName = "ProgramTabulkaBuňkaKompaktní";
