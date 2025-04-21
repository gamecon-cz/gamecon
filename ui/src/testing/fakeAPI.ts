import { ApiAktivita, ApiAktivitaPřihlášen } from "../api/program";

export const fetchTestovacíAktivity = async (rok: number): Promise<ApiAktivita[]> => {
  const res = await fetch("/testing/aktivityProgram.json");
  const json = await res.json() as { [rok: number]: ApiAktivita[] };
  return json[rok] ?? [];
};

export const fetchTestovacíAktivityPřihlášen = async (rok: number): Promise<ApiAktivitaPřihlášen[]> => {
  return (await fetchTestovacíAktivity(rok)).map(x => ({ id: x.id, mistnost: `místnost 5`, prihlasen: Math.random() < .3, vedu: Math.random() < .1, slevaNasobic: Math.random() < .1 ? undefined : Math.floor(Math.random() * 10) / 10 } as ApiAktivitaPřihlášen));
};
