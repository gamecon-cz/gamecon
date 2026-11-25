/**
 * Náhled vedle editoru. Vykresluje ho server stejně jako web (Markdown Extra), dřív to dělal showdown.js
 * v prohlížeči a tabulky nebo poznámky pod čarou v náhledu vypadaly jinak než na webu.
 * Při psaní počká, až se chvíli nepíše, a starší rozpracovaný požadavek zruší, ať náhled nepřepíše starší text.
 */
export class Nahled {
  private casovac: ReturnType<typeof setTimeout> | undefined;
  private probihajici: AbortController | undefined;

  constructor(
    private readonly nacti: (text: string, signal: AbortSignal) => Promise<string>,
    private readonly vykresli: (html: string) => void,
    private readonly priChybe: (chyba: unknown) => void,
    private readonly zpozdeniMs = 400,
  ) {}

  naplanuj(text: string): void {
    clearTimeout(this.casovac);
    this.casovac = setTimeout(() => void this.obnov(text), this.zpozdeniMs);
  }

  private async obnov(text: string): Promise<void> {
    this.probihajici?.abort();
    const ovladac = new AbortController();
    this.probihajici = ovladac;
    try {
      const html = await this.nacti(text, ovladac.signal);
      if (this.probihajici === ovladac) {
        this.vykresli(html);
      }
    } catch (chyba) {
      if (!ovladac.signal.aborted) {
        this.priChybe(chyba);
      }
    }
  }
}
