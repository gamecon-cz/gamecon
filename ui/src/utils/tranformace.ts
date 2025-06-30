import { ApiAktivitaNepřihlášen, ApiŠtítek, Obsazenost, OdDo } from "../api/program";

export const štítkyZId = (štítkyId: number[] | undefined, štítky: ApiŠtítek[]) => {
  return štítkyId
    ?.map(id => štítky.find(štítek => štítek.id === id)?.nazev)
    ?.filter(název=> název)
    ?? [];
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

export const casRozsahZAktivit = (aktivity: ApiAktivitaNepřihlášen[]): OdDo => {
  let casOd = Math.min();
  let casDo = Math.max();

  for (const aktivita of aktivity) {
    casOd = Math.min(casOd, aktivita.cas.od);
    casDo = Math.max(casDo, aktivita.cas.do);
  }

  return { od: casOd, do: casDo };
};
