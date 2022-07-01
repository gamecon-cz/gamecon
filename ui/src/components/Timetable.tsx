import { distinct, range, zip } from "../utils";


export type TimeRange = { from: number; to: number };

export type Buňka = {
  time: TimeRange;
  group: string;
  element: JSXElement;
};

export type StructureBuňka = {
  span?: number;
  content?: JSXElement;
  header?: true;
};


const getTracks = (timeRanges: TimeRange[]): number[] => {
  const timeRangesWIndex = timeRanges.map((x, i) => ({ ...x, i }));
  timeRangesWIndex.sort((a, b) => a.from - b.from);
  const tracks = Array(timeRanges.length);

  let trackIndex = 0;
  while (timeRangesWIndex.length) {
    let popIndex = 0;
    do {
      const { to, i } = timeRangesWIndex.splice(popIndex, 1)[0];
      tracks[i] = trackIndex;
      popIndex = timeRangesWIndex.findIndex((x) => x.from >= to);
    } while (popIndex !== -1);
    trackIndex++;
  }

  return tracks;
};

const getTracksForBuňkas = (
  buňky: Buňka[]
): { tracks: number[]; groupTracksLen: Record<string, number> } => {
  const groups = distinct(buňky.map((x) => x.group)).sort();
  const tracks: number[] = Array(buňky.length);
  const groupTracksLen: Record<string, number> = {};
  for (let gi = 0; gi < groups.length; gi++) {
    const group = groups[gi];
    const buňkyWIndex = buňky
      .map((buňka, index) => ({ index, buňka }))
      .filter(({ buňka }) => buňka.group === group);
    const groupTracks = getTracks(buňkyWIndex.map((x) => x.buňka.time));
    // TODO: replace zip with faster for loop
    zip(buňkyWIndex, groupTracks).forEach(([{ index }, track]) => {
      tracks[index] = track;
    });
    groupTracksLen[group] = Math.max(...groupTracks);
  }
  return { tracks, groupTracksLen };
};

const getTableStructure = (
  buňky: Buňka[],
  groups: string[],
  timeRange: TimeRange
): StructureBuňka[][] => {
  const table: StructureBuňka[][] = [];

  const { tracks, groupTracksLen } = getTracksForBuňkas(buňky);

  {
    const headerRow: StructureBuňka[] = [{}];
    range(timeRange.from, timeRange.to + 1).forEach((x) =>
      headerRow.push({ content: <>{x}</> })
    );
    table.push(headerRow);
  }

  groups.forEach((group) => {
    const maxTrack = groupTracksLen[group] || 0;
    range(maxTrack + 1).map((track) => {
      const row: { span?: number; content?: JSXElement; header?: true }[] =
        track ? [] : [{ content: <>{group}</>, span: maxTrack + 1, header: true }];
      const c = zip(buňky, tracks)
        .filter(([, t]) => t === track)
        .map(([buňka]) => buňka)
        .filter((buňka) => buňka.group === group)
        .sort((a, b) => a.time.from - b.time.from);
      range(timeRange.from, timeRange.to + 1).forEach((t) => {
        if (c[0] && c[0].time.to <= t) c.splice(0, 1);
        const buňka = c[0];
        if (buňka) {
          if (buňka.time.from === t) {
            row.push({
              span: buňka.time.to - buňka.time.from,
              content: buňka.element,
            });
          } else if (buňka.time.from > t) {
            row.push({});
          }
        } else {
          row.push({});
        }
      });
      table.push(row);
    });
  });

  return table;
};

const getRangeFromTimetableProp = (props: TimetableProps): TimeRange => {
  const { buňky, timeRange } = props;

  if (timeRange && timeRange !== "auto") return timeRange;

  // TODO: předávání parametrů funkce pře zásobník (...spread operátor), fuj
  const minTimeHours = Math.min(...buňky.map((x) => x.time.from));
  const maxTimeHours = Math.max(...buňky.map((x) => x.time.to));

  return { from: minTimeHours, to: maxTimeHours };
};

export type TimetableProps = {
  buňky: Buňka[];
  groups: string[];
  timeRange?: TimeRange | "auto";
};

// TODO: quickfix, přerenderuje pokaždé všechny buňky, bylo by potřeba nějak udělat ID pro každou událost asi
let key = 0;

export const Timetable = (props: TimetableProps) => {
  const { buňky, groups } = props;
  const timeRange = getRangeFromTimetableProp(props);

  const rowsStructure = getTableStructure(buňky, groups, timeRange);

  const rows = rowsStructure.map((r, i) => (
    <tr>
      {r.map((c) =>
        !c.header ? (
          // Normal buňka
          i ? (
            <td colSpan={c?.span}>{c.content}</td>
          ) : (
            <th colSpan={c?.span}>{c.content}</th>
          )
        ) : // Left header
        i ? (
          <td key={key++} rowSpan={c?.span || 1}>
            <div class="program_nazevLinie">{c.content}</div>
          </td>
        ) : (
          <th key={key++} rowSpan={c?.span || 1}>
            <div class="program_nazevLinie">{c.content}</div>
          </th>
        )
      )}
    </tr>
  ));

  return (
    <>
      <table class="program">
        <tbody>{rows}</tbody>
      </table>
    </>
  );
};
