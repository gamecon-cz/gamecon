import { distinct, range, zip } from "../utils";


export type TimeRange = { from: number; to: number };

export type Cell = {
  time: TimeRange;
  group: string;
  element: JSXElement;
};

export type StructureCell = {
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

const getTracksForCells = (
  cells: Cell[]
): { tracks: number[]; groupTracksLen: Record<string, number> } => {
  const groups = distinct(cells.map((x) => x.group)).sort();
  const tracks: number[] = Array(cells.length);
  const groupTracksLen: Record<string, number> = {};
  for (let gi = 0; gi < groups.length; gi++) {
    const group = groups[gi];
    const cellsWIndex = cells
      .map((cell, index) => ({ index, cell }))
      .filter(({ cell }) => cell.group === group);
    const groupTracks = getTracks(cellsWIndex.map((x) => x.cell.time));
    // TODO: replace zip with faster for loop
    zip(cellsWIndex, groupTracks).forEach(([{ index }, track]) => {
      tracks[index] = track;
    });
    groupTracksLen[group] = Math.max(...groupTracks);
  }
  return { tracks, groupTracksLen };
};

const getTableStructure = (
  cells: Cell[],
  groups: string[],
  timeRange: TimeRange
): StructureCell[][] => {
  const table: StructureCell[][] = [];

  const { tracks, groupTracksLen } = getTracksForCells(cells);

  {
    const headerRow: StructureCell[] = [{}];
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
      const c = zip(cells, tracks)
        .filter(([, t]) => t === track)
        .map(([cell]) => cell)
        .filter((cell) => cell.group === group)
        .sort((a, b) => a.time.from - b.time.from);
      range(timeRange.from, timeRange.to + 1).forEach((t) => {
        if (c[0] && c[0].time.to <= t) c.splice(0, 1);
        const cell = c[0];
        if (cell) {
          if (cell.time.from === t) {
            row.push({
              span: cell.time.to - cell.time.from,
              content: cell.element,
            });
          } else if (cell.time.from > t) {
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


export type TimetableProps = {
  cells: Cell[];
  groups: string[];
  timeRange: TimeRange;
};

export const Timetable = (props: TimetableProps) => {
  const { cells, groups, timeRange } = props;
  const rowsStructure = getTableStructure(cells, groups, timeRange);

  const rows = rowsStructure.map((r, i) => (
    <tr>
      {r.map((c) =>
        !c.header ? (
          // Normal cell
          i ? (
            <td colSpan={c?.span}>{c.content}</td>
          ) : (
            <th colSpan={c?.span}>{c.content}</th>
          )
        ) : // Left header
        i ? (
          <td rowSpan={c?.span || 1}>
            <div class="program_nazevLinie">{c.content}</div>
          </td>
        ) : (
          <th rowSpan={c?.span || 1}>
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
