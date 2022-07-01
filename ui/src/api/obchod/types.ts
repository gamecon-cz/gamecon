


type DefiniceObchodMřížkaBuňkaTyp ="předmět" | "stránka" | "zpět" | "shrnutí";


export type DefiniceObchodMřížkaBuňkaSpolečné = {
  text?: string,
  barvaPozadí?: string, 
}


export type DefiniceObchodMřížkaBuňkaPředmět = {
  typ: "předmět",
  předmět: Předmět,
}

export type DefiniceObchodMřížkaBuňkaStránka = {
  typ: "stránka",
  /** id od DefiniceObchodMřížka */
  id: number,
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

export type Předmět = {
  id: number,
  text: string,
  zbývá: number,
};

export type ObjednávkaPředmět = {
  množství: number, 
  předmět: Předmět,
};

