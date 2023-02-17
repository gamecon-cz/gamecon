import { FunctionComponent } from "preact";
import { useProgramStore } from "../../../../store/program";
import { formátujDatum } from "../../../../utils";
import produce from "immer";
import { generujUrl, porovnejTabulkaVýběr } from "../../../../store/program/logic/url";

type ProgramUživatelskéVstupyProps = {};

export const ProgramUživatelskéVstupy: FunctionComponent<
  ProgramUživatelskéVstupyProps
> = (props) => {
  const {} = props;
  const urlState = useProgramStore((s) => s.urlState);
  const urlStateMožnosti = useProgramStore(s=>s.urlStateMožnosti);

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
                  useProgramStore.setState((s) => {
                    s.urlState.výběr = možnost;
                  }, undefined, "nastav program den");
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
