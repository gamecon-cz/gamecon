import { FunctionComponent } from "preact";
import { formátujDatum } from "../../../../utils";
import produce from "immer";
import { generujUrl, porovnejTabulkaVýběr } from "../../../../store/program/logic/url";
import { nastavUrlVýběr } from "../../../../store/program/slices/urlSlice";
import { useUrlState, useUrlStateMožnosti } from "../../../../store/program/selektory";

type ProgramUživatelskéVstupyProps = {};

export const ProgramUživatelskéVstupy: FunctionComponent<
  ProgramUživatelskéVstupyProps
> = (props) => {
  const {} = props;
  const urlState = useUrlState();
  const urlStateMožnosti = useUrlStateMožnosti();

  return (
    <>
      <div class="program_hlavicka">
        <h1>Program {urlState.rok}</h1>
        <div class="program_dny">
          {urlStateMožnosti.map((možnost) => {
            return (
              <a
                href={
                  generujUrl(produce(urlState, (s) => {
                    s.výběr = možnost;
                  }))
                }
                class={
                  "program_den" +
                  (porovnejTabulkaVýběr(možnost, urlState.výběr)
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
                  : formátujDatum(možnost.datum)}
              </a>
            );
          })}
        </div>
      </div>
    </>
  );
};
