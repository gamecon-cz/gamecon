import { GAMECON_KONSTANTY } from "../../env";
import { ProgramLegenda } from "./ProgramLegenda";
import { useProgramSemanticRoute } from "./routing";
import { ProgramTabulka } from "./tabulka/ProgramTabulka";
import { ProgramUživatelskéVstupy } from "./vstupy/Vstupy";

import "./program.less";

const dny = GAMECON_KONSTANTY.PROGRAM_DNY;

/** část odazu od které začíná programově specifické url managované preactem */
export const PROGRAM_URL_NAME = "program";

export function Program() {
  const { urlState, setUrlState, možnosti } = useProgramSemanticRoute();

  return (
    <div style={{ position: "relative" }}>
      {/* <ProgramNáhled {} /> */}
      <ProgramUživatelskéVstupy {...{ urlState, setUrlState, možnosti }} />
      <ProgramLegenda />
      <ProgramTabulka {...{ urlState, setUrlState, možnosti }} />
    </div>
  );
}
