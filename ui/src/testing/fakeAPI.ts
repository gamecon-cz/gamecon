import { ApiAktivita, ApiAktivitaUživatel } from "../api/program";
//todo: smazat
export const fetchTestovacíAktivity = async (rok: number): Promise<ApiAktivita[]> => {
  const res = await fetch("/testing/aktivityProgram.json");
  const json = await res.json() as { [rok: number]: ApiAktivita[] };
  return json[rok] ?? [];
};

export const fetchTestovacíAktivityPřihlášen = async (rok: number): Promise<ApiAktivitaUživatel[]> => {
  return (await fetchTestovacíAktivity(rok)).map(x => ({ __TS_STRUKTURALNI_KONTROLA__: true,id: x.id, mistnost: `místnost 5`, prihlasen: Math.random() < .3, vedu: Math.random() < .1, slevaNasobic: Math.random() < .1 ? undefined : Math.floor(Math.random() * 10) / 10 } as ApiAktivitaUživatel));
};
