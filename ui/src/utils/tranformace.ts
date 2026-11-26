import { ApiAktivitaNepřihlášen, ApiTag, ApiObsazenost, OdDo } from "../api/program";
import { Pohlavi } from "../api/přihlášenýUživatel";
import { názevŠtítku } from "../i18n/nazvy";

/** Názvy štítků aktivity pro čtenáře v jazyce stránky */
export const tagyZId = (tagyId: number[] | undefined, tagy: ApiTag[]): string[] => {
  return tagyId
    ?.map(id => tagy.find(tag => tag.id === id))
    ?.filter((tag): tag is ApiTag => !!tag)
    ?.map(tag => názevŠtítku(tag))
    ?? [];
};

export const volnoTypZObsazenost = (obsazenost: ApiObsazenost) => {
  const { m, f, km, kf, ku, kt, t } = obsazenost;
  const c = m + f;
  const kc = ku + km + kf;

  if (kt) {
    return (t ?? 0) >= kt ? "x" : "t";
  }
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

/**
 * Pro toho, kdo se dívá, už na aktivitě místo není: přihlášenému se počítají jen místa pro jeho pohlaví,
 * nepřihlášenému jen místa pro kohokoli. Týmovka se plní po týmech. Podle toho program buňku obarví jako plnou.
 */
export const jePlnoZObsazenost = (obsazenost: ApiObsazenost, pohlavi: Pohlavi | undefined): boolean => {
  const volnoTyp = volnoTypZObsazenost(obsazenost);
  return volnoTyp !== "u" && volnoTyp !== "t" && volnoTyp !== pohlavi;
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
