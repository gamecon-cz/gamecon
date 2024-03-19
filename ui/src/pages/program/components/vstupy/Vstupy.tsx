import { FunctionComponent } from "preact";
import { formátujDatum } from "../../../../utils";
import produce from "immer";
import {
  generujUrl,
  porovnejTabulkaVýběr,
} from "../../../../store/program/logic/url";
import { nastavUrlVýběr } from "../../../../store/program/slices/urlSlice";
import {
  useFiltryOtevřené,
  useUrlStav,
  useUrlStavMožnostiDny,
} from "../../../../store/program/selektory";
import { Filtry } from "./Filtry";
import { GAMECON_KONSTANTY } from "../../../../env";
import { nastavFiltryOtevřené } from "../../../../store/program/slices/všeobecnéSlice";

type ProgramUživatelskéVstupyProps = {};

export const ProgramUživatelskéVstupy: FunctionComponent<
  ProgramUživatelskéVstupyProps
> = (props) => {
  const {} = props;
  const urlStav = useUrlStav();
  const urlStavMožnosti = useUrlStavMožnostiDny();

  const jeLetošníRočník = urlStav.ročník === GAMECON_KONSTANTY.ROCNIK;

  const filtryOtevřené = useFiltryOtevřené();

  return (
    <>
      <div class="program_hlavicka">
        <h1>Program {urlStav.ročník}</h1>
        <div class="program_dny">
          {urlStavMožnosti.map((možnost) => {
            return (
              <a
                href={generujUrl(
                  produce(urlStav, (s) => {
                    s.výběr = možnost;
                  })
                )}
                class={
                  "program_den" +
                  (porovnejTabulkaVýběr(možnost, urlStav.výběr)
                    ? " program_den-aktivni"
                    : "")
                }
                onClick={(e) => {
                  e.preventDefault();
                  nastavUrlVýběr(možnost);
                }}
              >
                {možnost.typ === "můj"
                  ? "můj program"
                  : formátujDatum(možnost.datum, !jeLetošníRočník)}
              </a>
            );
          })}
          <button
            class={
              "program_filtry_tlacitko" + (filtryOtevřené ? " aktivni" : "")
            }
            onClick={() => {
              nastavFiltryOtevřené(!filtryOtevřené);
            }}
          >
            Filtry
          </button>
        </div>
        <Filtry {...{ otevřeno: filtryOtevřené }} />
      </div>
    </>
  );
};
