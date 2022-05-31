import "./program.less";
import { ProgramLegenda } from "./ProgramLegenda";
import { ProgramTabulka } from "./tabulka/ProgramTabulka";
import { ProgramUživatelskéVstupy } from "./vstupy/Vstupy";

export function Program() {
  return (
    <div style={{position:"relative"}}>
      {/* <ProgramNáhled {} /> */}
      <ProgramUživatelskéVstupy />
      <ProgramLegenda />
      <ProgramTabulka />
    </div>
  );
}
