// Types for admin/files/editor-sablony-logika.js, which the browser loads as a plain <script> without a bundler.

declare module '*/editor-sablony-logika.js' {
  interface Token {
    typ: string
    hodnota: string
    zacatek: number
    konec: number
  }

  interface DataSymbolu {
    promenne: { nazev: string; popis: string; ukazka: string; vPredmetu: boolean }[]
    systemove: { nazev: string; popis: string; ukazka: string }[]
    neverejne: string[]
  }

  const logika: {
    TYP: Record<string, string>
    rozloz(text: string): Token[]
    jePromenna(nazev: string): boolean
    jeSystemovaHodnota(nazev: string): boolean
    jakoZapsano(token: Token): string
    validuj(data: DataSymbolu, predmet: string, text: string): string[]
    najdiRozepsanySymbol(text: string, kurzor: number): { zacatek: number; predpona: string } | null
    rozsahKPrepsani(text: string, zacatekVyberu: number, konecVyberu: number): { zacatek: number; konec: number }
    filtrujSymboly<T extends { nazev: string; popis: string }>(symboly: T[], predpona: string): T[]
  }

  export default logika
}
