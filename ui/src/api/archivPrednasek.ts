/*
 * Data pro filtr archivu přednášek (/prednasky/archiv), posílá je web/moduly/archivprednasek.php
 * v gameconPřednačtení. Karty přednášek vykresluje PHP.
 */

export type ApiArchivPrednaska = {
  /** id prvku karty */
  htmlId: string,
  id: number,
  rok: number,
  /** id přednášejících archivu */
  prednasejici: number[],
  /** klíče štítků (klíčová slova i štítky z programu) */
  stitky: string[],
  /** má záznam na YouTube */
  zaznam: boolean,
};

export type ApiArchivPrednasejici = {
  id: number,
  jmeno: string,
};

export type ApiArchivStitek = {
  /** název bez diakritiky a velikosti písmen, v adrese filtru */
  klic: string,
  nazev: string,
  /** „Klíčová slova“, nebo kategorie štítku programu */
  skupina: string,
};

export type ApiArchivPrednasek = {
  /** od nejnovějšího roku, v roce podle abecedy */
  prednasky: ApiArchivPrednaska[],
  /** roky s přednáškami od nejnovějšího */
  roky: number[],
  /** podle české abecedy */
  prednasejici: ApiArchivPrednasejici[],
  /** klíčová slova podle abecedy, pak štítky programu podle pořadí kategorií */
  stitky: ApiArchivStitek[],
};
