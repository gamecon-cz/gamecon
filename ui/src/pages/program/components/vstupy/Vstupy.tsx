import { FunctionComponent } from "preact";
import { useContext } from "preact/hooks";
import { GAMECON_KONSTANTY } from "../../../../env";
import {
  formátujDatum,
} from "../../../../utils";
import {
  porovnejTabulkaVýběr,
  ProgramURLState,
} from "../../routing";

type ProgramUživatelskéVstupyProps = {};

export const ProgramUživatelskéVstupy: FunctionComponent<
  ProgramUživatelskéVstupyProps
> = (props) => {
  const { možnosti, setUrlState, urlState } = useContext(ProgramURLState);

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
