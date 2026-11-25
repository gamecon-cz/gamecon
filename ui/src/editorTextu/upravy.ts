import { EditorSelection, type SelectionRange, type StateCommand } from "@codemirror/state";

/**
 * Úpravy textu z lišty editoru a klávesových zkratek. Jsou to příkazy nad stavem (bez DOM), takže jdou testovat bez
 * prohlížeče a každá je jeden krok historie (Ctrl+Z ji vrátí celou).
 */

const BILE_ZNAKY_NA_ZACATKU = /^\s+/;
const BILE_ZNAKY_NA_KONCI = /\s+$/;

/**
 * Výběr bez mezer a odřádkování na krajích: `**slovo **` by Markdown nepoznal jako tučné.
 */
const bezBilychZnakuNaKrajich = (text: string, rozsah: SelectionRange): { from: number; to: number } => {
  const vybrane = text.slice(rozsah.from, rozsah.to);
  const zacatek = BILE_ZNAKY_NA_ZACATKU.exec(vybrane)?.[0].length ?? 0;
  const konec = BILE_ZNAKY_NA_KONCI.exec(vybrane)?.[0].length ?? 0;
  if (zacatek === vybrane.length) {
    return { from: rozsah.from, to: rozsah.to };
  }

  return { from: rozsah.from + zacatek, to: rozsah.to - konec };
};

/**
 * Obalí výběr značkou (`**` tučně, `_` kurzíva), nebo ji odebere, když výběr už obaluje (zvenku i zevnitř výběru).
 * Bez výběru vloží dvě značky a kurzor mezi ně.
 */
export const prepniObaleni = (znacka: string): StateCommand => ({ state, dispatch }) => {
  const text = state.doc.toString();
  const delka = znacka.length;
  const transakce = state.changeByRange((rozsah) => {
    const { from, to } = bezBilychZnakuNaKrajich(text, rozsah);
    if (text.slice(from - delka, from) === znacka && text.slice(to, to + delka) === znacka) {
      return {
        changes: [{ from: from - delka, to: from }, { from: to, to: to + delka }],
        range: EditorSelection.range(from - delka, to - delka),
      };
    }
    const vybrane = text.slice(from, to);
    if (vybrane.length >= 2 * delka && vybrane.startsWith(znacka) && vybrane.endsWith(znacka)) {
      return {
        changes: [{ from, to: from + delka }, { from: to - delka, to }],
        range: EditorSelection.range(from, to - 2 * delka),
      };
    }

    return {
      changes: [{ from, insert: znacka }, { from: to, insert: znacka }],
      range: EditorSelection.range(from + delka, to + delka),
    };
  });
  dispatch(state.update(transakce, { scrollIntoView: true, userEvent: "input" }));

  return true;
};

export type PredponaRadku = {
  /** předpona, kterou řádek dostane; `poradi` je pořadí neprázdného řádku ve výběru od nuly */
  predpona: (poradi: number) => string;
  /** předpona, kterou už řádek má (podle ní se pozná, že se má odebrat) */
  vzor: RegExp;
  /** jiná předpona, kterou nová nahradí (odrážky místo čísel a naopak) */
  nahrazuje?: RegExp;
};

/**
 * Přidá předponu řádkům, kterých se výběr dotýká (nadpis, odrážky, číslovaný seznam, citace) a které ji ještě nemají,
 * nebo ji odebere, když ji mají všechny. Prázdné řádky přeskočí, ledaže je výběr jen na jednom prázdném řádku.
 */
export const prepniPredponuRadku = ({ predpona, vzor, nahrazuje }: PredponaRadku): StateCommand => ({ state, dispatch }) => {
  const cislaRadku = new Set<number>();
  for (const rozsah of state.selection.ranges) {
    // výběr celého řádku i s odřádkováním (trojklik) končí na začátku dalšího řádku, ten už do výběru nepatří
    const konec = !rozsah.empty && state.doc.lineAt(rozsah.to).from === rozsah.to ? rozsah.to - 1 : rozsah.to;
    const posledni = state.doc.lineAt(konec).number;
    for (let cislo = state.doc.lineAt(rozsah.from).number; cislo <= posledni; cislo++) {
      cislaRadku.add(cislo);
    }
  }
  const vsechny = [...cislaRadku].sort((a, b) => a - b).map((cislo) => state.doc.line(cislo));
  const neprazdne = vsechny.filter((radek) => radek.text.trim() !== "");
  const radky = neprazdne.length > 0 ? neprazdne : vsechny;
  const odebrat = neprazdne.length > 0 && neprazdne.every((radek) => vzor.test(radek.text));

  const changes = radky.flatMap((radek, poradi) => {
    if (odebrat) {
      return [{ from: radek.from, to: radek.from + (radek.text.match(vzor)?.[0].length ?? 0) }];
    }
    // řádek, který předponu už má, zůstane, jak je, jinak by ji měl dvakrát (`- - a`)
    if (vzor.test(radek.text)) {
      return [];
    }
    const nahrazena = nahrazuje ? radek.text.match(nahrazuje)?.[0].length ?? 0 : 0;

    return [{ from: radek.from, to: radek.from + nahrazena, insert: predpona(poradi) }];
  });
  const zmeny = state.changes(changes);
  dispatch(state.update({
    changes: zmeny,
    // kurzor na začátku řádku skončí za novou předponou, ať jde rovnou psát
    selection: state.selection.map(zmeny, 1),
    scrollIntoView: true,
    userEvent: "input",
  }));

  return true;
};

export const NADPIS: PredponaRadku = { predpona: () => "## ", vzor: /^#{1,6}[ \t]+/ };
export const ODRAZKY: PredponaRadku = { predpona: () => "- ", vzor: /^[-*+][ \t]+/, nahrazuje: /^\d+\.[ \t]+/ };
export const CISLOVANY_SEZNAM: PredponaRadku = {
  predpona: (poradi) => `${poradi + 1}. `,
  vzor: /^\d+\.[ \t]+/,
  nahrazuje: /^[-*+][ \t]+/,
};
export const CITACE: PredponaRadku = { predpona: () => "> ", vzor: /^>[ \t]?/ };

const ADRESA = "https://";

/**
 * Odkaz `[text](https://)`, s obrázkem `![popis](https://)`. Vybraný text se stane textem odkazu a vybere se adresa,
 * ať ji jde rovnou přepsat; bez výběru se vybere text odkazu.
 */
export const vlozOdkaz = (obrazek = false): StateCommand => ({ state, dispatch }) => {
  const zacatek = obrazek ? "![" : "[";
  const text = state.doc.toString();
  const transakce = state.changeByRange((rozsah) => {
    const { from, to } = bezBilychZnakuNaKrajich(text, rozsah);
    const vybrane = text.slice(from, to);
    const popis = vybrane !== "" ? vybrane : obrazek ? "popis" : "text odkazu";
    const vlozeno = `${zacatek}${popis}](${ADRESA})`;
    const zacatekPopisu = from + zacatek.length;
    const zacatekAdresy = zacatekPopisu + popis.length + 2;

    return {
      changes: { from, to, insert: vlozeno },
      range: vybrane !== ""
        ? EditorSelection.range(zacatekAdresy, zacatekAdresy + ADRESA.length)
        : EditorSelection.range(zacatekPopisu, zacatekPopisu + popis.length),
    };
  });
  dispatch(state.update(transakce, { scrollIntoView: true, userEvent: "input" }));

  return true;
};
