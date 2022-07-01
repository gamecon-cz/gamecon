import { GAMECON_KONSTANTY } from "../../env";
import { ProgramLegenda } from "./ProgramLegenda";
import { ProgramURLState, useProgramSemanticRoute } from "./routing";
import { ProgramTabulka } from "./tabulka/ProgramTabulka";
import { ProgramUživatelskéVstupy } from "./vstupy/Vstupy";

import "./program.less";
import { ProgramNáhled } from "./náhled/ProgramNáhled";
import { useState, useEffect } from "preact/hooks";
import { Aktivita, fetchAktivity } from "../../api";


/** část odazu od které začíná programově specifické url managované preactem */
export const PROGRAM_URL_NAME = "program";

export function Program() {
  const semanticRoute = useProgramSemanticRoute();
  const { urlState } = semanticRoute;

  const [aktivity, setAktivity] = useState<Aktivita[]>([]);

  const aktivitaNáhled =
    urlState.aktivitaNáhledId !== undefined
      ? aktivity.find((x) => x.id === urlState.aktivitaNáhledId)
      : undefined;

  useEffect(() => {
    (async () => {
      const aktivity = await fetchAktivity(GAMECON_KONSTANTY.ROK);
      setAktivity(aktivity);
    })();
  }, []);

  return (
    <ProgramURLState.Provider value={semanticRoute}>
      <div style={{ position: "relative" }}>
        {aktivitaNáhled ? (
          <ProgramNáhled aktivita={aktivitaNáhled} />
        ) : undefined}
        <ProgramUživatelskéVstupy />
        <ProgramLegenda />
        <ProgramTabulka {...{ aktivity }} />
      </div>
    </ProgramURLState.Provider>
  );
}
