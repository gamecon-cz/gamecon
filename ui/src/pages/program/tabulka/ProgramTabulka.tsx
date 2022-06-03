import { FunctionComponent } from "preact";
import { useEffect, useRef, useState } from "preact/hooks";
import { Aktivita, fetchAktivity } from "../../../api";
import { Timetable } from "../../../components/Timetable";
import { GAMECON_KONSTANTY } from "../../../env";
import { distinct } from "../../../utils";
import { ProgramTabulkaVýběr, ProgramURLState } from "../routing";
import { ProgramPosuv } from "./ProgramPosuv";
import { TabulkaBuňka } from "./TabulkaBuňka";

type ProgramTabulkaProps = {
  urlState: ProgramURLState;
  setUrlState: (urlState: ProgramURLState) => void;
  možnosti: ProgramTabulkaVýběr[];
};

export const ProgramTabulka: FunctionComponent<ProgramTabulkaProps> = (
  props
) => {
  const { urlState } = props;
  const [aktivity, setAktivity] = useState<Aktivita[]>([]);

  useEffect(() => {
    (async () => {
      const aktivity = await fetchAktivity(GAMECON_KONSTANTY.ROK);
      setAktivity(aktivity);
    })();
  }, []);

  const tabulka = (
    <Timetable
      cells={aktivity
        .filter((x) =>
          urlState.výběr.typ === "můj"
            ? true
            : new Date(x.cas.od).getDay() === urlState.výběr.datum.getDay()
        )
        .map((x) => ({
          element: <TabulkaBuňka aktivita={x} />,
          group: x.linie,
          time: {
            from: new Date(x.cas.od).getHours(),
            to: new Date(x.cas.do).getHours(),
          },
        }))}
      groups={distinct(aktivity.map((x) => x.linie))}
      timeRange={"auto"}
    />
  );

  const obalRef = useRef<HTMLDivElement>(null);

  return (
    <>
      <div class="programNahled_obalProgramu">
        <div class="programPosuv_obal2">
          <div class="programPosuv_obal" ref={obalRef}>
            {tabulka}
          </div>
          <ProgramPosuv {...{ obalRef }} />
        </div>
      </div>
    </>
  );
};

ProgramTabulka.displayName = "programNáhled";
