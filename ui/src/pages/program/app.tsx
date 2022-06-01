import { usePath } from "../../utils";
import "./program.less";
import { ProgramLegenda } from "./ProgramLegenda";
import { ProgramTabulka } from "./tabulka/ProgramTabulka";
import { ProgramUživatelskéVstupy } from "./vstupy/Vstupy";

/** část odazu od které začíná programově specifické url managované preactem */
export const PROGRAM_URL_NAME = "program";

export function Program() {
  const [aktivníMožnost, setAktivníMožnost] = usePath();

  return (
    <div style={{ position: "relative" }}>
      {/* <ProgramNáhled {} /> */}
      <ProgramUživatelskéVstupy {...{ aktivníMožnost, setAktivníMožnost }} />
      <ProgramLegenda />
      <ProgramTabulka />
    </div>
  );
}
