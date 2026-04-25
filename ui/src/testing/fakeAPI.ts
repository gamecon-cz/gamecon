import { ApiAktivita, ApiAktivitaUživatel } from "../api/program";
//todo: smazat
export const fetchTestovacíAktivity = async (rok: number): Promise<ApiAktivita[]> => {
  const res = await fetch("/testing/aktivityProgram.json");
  const json = await res.json() as { [rok: number]: ApiAktivita[] };
  return json[rok] ?? [];
};

export const fetchTestovacíAktivityPřihlášen = async (rok: number): Promise<ApiAktivitaUživatel[]> => {
  return (await fetchTestovacíAktivity(rok)).map(x => ({
    __TS_STRUKTURALNI_KONTROLA__: true,
    id:             x.id,
    stavPrihlaseni: Math.random() < .3 ? "prihlasen" : null,
    slevaNasobic:   Math.random() < .1 ? 0 : 1,
    mistnost:       Math.random() < .1 ? "místnost 5" : null,
    vedu:           Math.random() < .1,
    zamcenaDo:      null,
    zamcenaMnou:    false,
    // todo: tady to nekompiluje
  } as any));
};
