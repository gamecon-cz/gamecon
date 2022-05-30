import { useEffect, useState } from "preact/hooks";
import { Aktivita, fetchAktivity } from "../../../api";
import { Timetable } from "../../../components/Timetable";
import { distinct } from "../../../utils";
import { TabulkaBuňka } from "./TabulkaBuňka";

type ProgramNáhledProps = {};

export const ProgramNáhled = (_props: ProgramNáhledProps) => {
  const [aktivity, setAktivity] = useState<Aktivita[]>([]);

  useEffect(() => {
    (async () => {
      const aktivity = await fetchAktivity(2022);
      console.log(aktivity);
      setAktivity(aktivity);
    })();
  }, []);

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
      timeRange={{ from: 8, to: 22 }}
    />
  );

  return (
    <>
      <div class="programNahled_obalProgramu">
        <div class="programPosuv_obal2">
          <div class="programPosuv_obal">{tabulka}</div>
        </div>
      </div>
    </>
  );
};

ProgramNáhled.displayName = "programNáhled";
