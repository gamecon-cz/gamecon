import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { PŘIHLÁŠEN } from "../../../api";
import { GAMECON_KONSTANTY } from "../../../env";
import {
  DNY_NÁZVY,
  doplňHáčkyDoDne,
  formátujDatum,
  formátujDenVTýdnu,
} from "../../../utils";
import {
  porovnejTabulkaVýběr,
  ProgramTabulkaVýběr,
  ProgramURLState,
} from "../routing";

type ProgramUživatelskéVstupyProps = {
  urlState: ProgramURLState;
  setUrlState: (urlState: ProgramURLState) => void;
  možnosti: ProgramTabulkaVýběr[];
};

export const ProgramUživatelskéVstupy: FunctionComponent<
  ProgramUživatelskéVstupyProps
> = (props) => {
  const { urlState, setUrlState, možnosti } = props;

  const rok = GAMECON_KONSTANTY.ROK;

  return (
    <>
      <div class="program_hlavicka">
        <h1>Program {rok}</h1>
        <div class="program_dny">
          {možnosti.map((možnost) => {
            return (
              <button
                class={
                  "program_den" +
                  (porovnejTabulkaVýběr(možnost, urlState.výběr)
                    ? " program_den-aktivni"
                    : "")
                }
                onClick={() => setUrlState({ výběr: možnost })}
              >
                {možnost.typ === "můj"
                  ? "můj program"
                  : formátujDatum(možnost.datum)}
              </button>
            );
          })}
        </div>
      </div>
    </>
  );
};
