import { APIAktivita, Obsazenost, OdDo } from "../api/program";
import { containsSame } from ".";
import { Aktivita } from "../store/program/slices/programDataSlice";


export const tagyZAktivit = (aktivity: APIAktivita[]): string[] => {
  const tagyMap = new Set<string>();

  for (let i = aktivity.length; i--;) {
    const { stitky } = aktivity[i];

    for (let i = stitky.length; i--;) {
      const stitek: string = stitky[i];
      tagyMap.add(stitek);
    }
  }

  return Array.from(tagyMap.keys()).sort();
};

/**
 * @param denVyber Zatím dokud nebude vyřešeno jinak
 */
export const getFiltredActivities = (activity: APIAktivita[], linie: string[], tagy: string[], denVyber: string): APIAktivita[] => {
  console.log(denVyber);
  return (
    tagy.length
      ? activity.filter(a => containsSame(a.stitky, tagy))
      : activity
  )
    .filter(x => containsSame([x.linie], linie));
  // .filter(x => x.cas.den === denVyber)
};

export const volnoTypZObsazenost = (obsazenost: Obsazenost) => {
  const { m, f, km, kf, ku } = obsazenost;
  const c = m + f;
  const kc = ku + km + kf;

  if (kc <= 0) {
    return "u"; //aktivita bez omezení
  }
  if (c >= kc) {
    return "x"; //beznadějně plno
  }
  if (m >= ku + km) {
    return "f"; //muži zabrali všechna univerzální i mužská místa
  }
  if (f >= ku + kf) {
    return "m"; //LIKE WTF? (opak předchozího)
  }
  //else
  return "u"; //je volno a žádné pohlaví nevyžralo limit míst
};

export const casRozsahZAktivit = (aktivity: APIAktivita[]): OdDo => {
  let casOd = Math.min();
  let casDo = Math.max();

  for (const aktivita of aktivity) {
    casOd = Math.min(casOd, aktivita.cas.od);
    casDo = Math.max(casDo, aktivita.cas.do);
  }

  return { od: casOd, do: casDo };
};
