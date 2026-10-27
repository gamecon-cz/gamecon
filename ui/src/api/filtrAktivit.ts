/*
 * Data pro filtry nad seznamem aktivit na stránce linie (např. /rpg), posílá je
 * web/moduly/aktivity.php v gameconPřednačtení. Karty samotné vykresluje PHP.
 */

export type ApiFiltrAktivitKarta = {
  /** id prvku karty, zároveň kotva aktivity */
  htmlId: string,
  /** id aktivity, podle něj se dohledá detail (aktivity_nahledText{id}) */
  id: number,
  tagy: number[],
  /** dny termínů bez diakritiky ("patek"), jak je ukazuje karta */
  dny: string[],
  /** id vypravěčů ze všech termínů karty */
  vypraveci: number[],
  /** aspoň na jeden termín se dá přihlásit a je na něm místo */
  volno: boolean,
};

export type ApiFiltrAktivitTag = {
  id: number,
  nazev: string,
  kategorie: string,
};

export type ApiFiltrAktivitDen = {
  klic: string,
  nazev: string,
};

export type ApiFiltrAktivitVypravec = {
  id: number,
  jmeno: string,
};

export type ApiFiltrAktivit = {
  karty: ApiFiltrAktivitKarta[],
  /** štítky z karet, seřazené podle pořadí kategorie a názvu */
  tagy: ApiFiltrAktivitTag[],
  /** dny z karet, popořadě */
  dny: ApiFiltrAktivitDen[],
  /** vypravěči z karet, podle abecedy */
  vypraveci: ApiFiltrAktivitVypravec[],
  /** nějaký termín je přihlašovatelný, jinak nemá filtr volných míst smysl */
  lzePrihlasovat: boolean,
};

/** přepínač ročníku nad aktivitami, jen když tu něco bylo i dřív */
export type ApiRocnikyAktivit = {
  /** zobrazený ročník */
  rocnik: number,
  letosni: number,
  /** ročníky s aktivitami od nejnovějšího, letošní vždy */
  rocniky: number[],
};
