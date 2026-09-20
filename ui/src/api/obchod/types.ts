/*
0-předmět, 1-stránka, 2-zpět, 3-shrnutí
*/

type DefiniceObchodMřížkaBuňkaTypStr = "předmět" | "stránka" | "zpět" | "shrnutí";

export enum DefiniceObchodMřížkaBuňkaTyp{
  "předmět" = 0,
  "stránka" = 1,
  "zpět" = 2,
  "shrnutí" = 3,
}

export type DefiniceObchodMřížkaBuňkaSpolečné = {
  text?: string,
  barvaPozadí?: string,
  barvaText?: string,
  id?: number,
}

export type DefiniceObchodMřížkaBuňkaPředmět = {
  typ: "předmět",
  cilId: number,
}

export type DefiniceObchodMřížkaBuňkaStránka = {
  typ: "stránka",
  /** id od DefiniceObchodMřížka */
  cilId: number,
}

export type DefiniceObchodMřížkaBuňkaZpět = {
  typ: "zpět",
}

export type DefiniceObchodMřížkaBuňkaShrnutí = {
  typ: "shrnutí",
}


export type DefiniceObchodMřížkaBuňka = DefiniceObchodMřížkaBuňkaSpolečné & (
  DefiniceObchodMřížkaBuňkaPředmět
  | DefiniceObchodMřížkaBuňkaStránka
  | DefiniceObchodMřížkaBuňkaZpět
  | DefiniceObchodMřížkaBuňkaShrnutí
);

export type DefiniceObchodMřížka = {
  id: number,
  buňky: DefiniceObchodMřížkaBuňka[],
  text?: string,
};

export type DefiniceObchod = {
  mřížky: DefiniceObchodMřížka[]
}

export type Varianta = {
  id: number,
  název: string,
  cena: number,
  zbývá: number | null,
};

export type Předmět = {
  id: number,
  název: string,
  cena: number,
  /** `null` znamená neomezeně, pokud má předmět jedinou variantu; u víc variant drží počty varianty samy. */
  zbývá: number | null,
  varianty: Varianta[],
  /** Z minulého ročníku — na starších mřížkách je nakonfigurovaný, ale prodat ho nejde. */
  archivní: boolean,
};

export type ObjednávkaPředmět = {
  množství: number,
  předmět: Předmět,
  /** U předmětu s víc variantami je povinná; jinak se dopočítá z té jediné. */
  varianta?: Varianta,
};

