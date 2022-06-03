import { FunctionComponent } from "preact";
import { useEffect, useRef, useState } from "preact/hooks";
import { Aktivita, fetchAktivity } from "../../../api";
import { Timetable } from "../../../components/Timetable";
import { GAMECON_KONSTANTY } from "../../../env";
import { distinct } from "../../../utils";
import { ProgramPosuv } from "./ProgramPosuv";
import { TabulkaBuňka } from "./TabulkaBuňka";

type ProgramTabulkaProps = {};

export const ProgramTabulka: FunctionComponent<ProgramTabulkaProps> = () => {
  const [aktivity, setAktivity] = useState<Aktivita[]>([]);

  useEffect(() => {
    (async () => {
      const aktivity = await fetchAktivity(GAMECON_KONSTANTY.ROK);
      setAktivity(aktivity);
    })();
  }, []);

  // TODO: předávání parametrů funkce pře zásobník (...spread operátor), fuj
  const minTime = Math.min(...aktivity.map((x) => x.cas.od));
  const _maxTime = Math.max(...aktivity.map((x) => x.cas.do));

  const tabulka = (
    <Timetable
      cells={aktivity.map((x) => ({
        element: <TabulkaBuňka aktivita={x} />,
        group: x.linie,
        time: {
          from: x.cas.od,
          to: x.cas.do,
        },
      }))}
      groups={distinct(aktivity.map((x) => x.linie))}
      timeRange={{ from: minTime, to: 24 }}
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
