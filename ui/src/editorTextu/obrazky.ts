import { type EditorState, StateEffect, StateField, type TransactionSpec } from "@codemirror/state";
import { Decoration, type DecorationSet, EditorView, WidgetType } from "@codemirror/view";

/**
 * Obrázky vložené do textu ze schránky, přetažením nebo tlačítkem: které soubory se nahrají a místo v textu, kam
 * přijdou, až se nahrají.
 */

/** To podstatné z `DataTransfer` (schránka, přetažení), ať jde testovat bez prohlížeče */
export type Prenos = {
  readonly files: ArrayLike<File>;
  getData(format: string): string;
};

export type Soubory = { obrazky: File[]; ostatni: File[] };

const rozdel = (soubory: ArrayLike<File>): Soubory => {
  const vsechny = Array.from(soubory);

  return {
    obrazky: vsechny.filter((soubor) => soubor.type.startsWith("image/")),
    ostatni: vsechny.filter((soubor) => !soubor.type.startsWith("image/")),
  };
};

/**
 * Soubory ze schránky, nebo null, když se má vložit text. Word, LibreOffice a Excel dávají ke zkopírovanému textu i jeho
 * obrázek, takže kde je text, má přednost: obrázek je jen snímek toho textu.
 */
export const souboryZeSchranky = (prenos: Prenos | null): Soubory | null => {
  if (!prenos || prenos.files.length === 0 || prenos.getData("text/plain").trim() !== "") {
    return null;
  }
  const soubory = rozdel(prenos.files);

  return soubory.obrazky.length > 0 ? soubory : null;
};

/**
 * Soubory přetažené do editoru (nebo vybrané tlačítkem Obrázek), nebo null, když se přetahuje text. Přetažený soubor
 * editor vždy obslouží sám: CodeMirror by jinak vložil do textu jeho obsah jako text.
 */
export const souboryZPretazeni = (prenos: { readonly files: ArrayLike<File> | null } | null): Soubory | null =>
  prenos?.files && prenos.files.length > 0 ? rozdel(prenos.files) : null;

/** Název souboru pro server: obrázek ze schránky se v prohlížečích jmenuje `image.png`, nic neříkající */
export const nazevProNahrani = (soubor: File, zeSchranky: boolean): string => {
  const pripona = soubor.type.split("/")[1]?.replace("jpeg", "jpg").replace(/\+.*$/, "") ?? "png";

  return zeSchranky || soubor.name === "" ? `obrazek.${pripona}` : soubor.name;
};

/** Místo v textu, kam přijdou obrázky, až se nahrají */
type Misto = { id: number; pozice: number; popis: string };

const idMista = (dekorace: Decoration): number | undefined => (dekorace.spec as { id?: number }).id;

export const pridejMisto = StateEffect.define<Misto>();
export const odeberMisto = StateEffect.define<number>();

class ZnackaNahravani extends WidgetType {
  constructor(readonly id: number, readonly popis: string) {
    super();
  }

  eq(jina: ZnackaNahravani): boolean {
    return jina.id === this.id && jina.popis === this.popis;
  }

  toDOM(): HTMLElement {
    const znacka = document.createElement("span");
    znacka.className = "et-nahravani";
    znacka.textContent = `Nahrávám ${this.popis}…`;

    return znacka;
  }
}

/**
 * Značky „Nahrávám…“ na místech, kam přijdou nahrávané obrázky. Nejsou součástí textu, takže se neuloží, kdyby
 * se text uložil dřív, než se obrázek nahraje, a posouvají se s textem, který se kolem nich mezitím píše.
 * Vlevo od kurzoru (`side: -1`): co se po vložení píše dál, zůstane za obrázkem.
 */
export const mistaObrazku = StateField.define<DecorationSet>({
  create: () => Decoration.none,
  update(mista, transakce) {
    let nova = mista.map(transakce.changes);
    for (const efekt of transakce.effects) {
      if (efekt.is(pridejMisto)) {
        const { id, pozice, popis } = efekt.value;
        nova = nova.update({
          add: [Decoration.widget({ widget: new ZnackaNahravani(id, popis), side: -1, id }).range(pozice)],
        });
      } else if (efekt.is(odeberMisto)) {
        nova = nova.update({ filter: (_od, _do, dekorace) => idMista(dekorace) !== efekt.value });
      }
    }

    return nova;
  },
  provide: (pole) => EditorView.decorations.from(pole),
});

export const poziceMista = (state: EditorState, id: number): number | null => {
  let pozice: number | null = null;
  state.field(mistaObrazku).between(0, state.doc.length, (od, _do, dekorace) => {
    if (idMista(dekorace) === id) {
      pozice = od;
      return false;
    }
    return undefined;
  });

  return pozice;
};

/**
 * Vloží zápis nahraných obrázků na jejich místo a místo odebere (i když se nic nenahrálo). Je to obyčejný krok
 * historie, Ctrl+Z ho vrátí celý. Kurzor, který na místě čeká, se posune za obrázek, jako po vložení textu.
 */
export const vlozNaMisto = (state: EditorState, id: number, text: string): TransactionSpec => {
  const pozice = poziceMista(state, id);
  if (pozice === null || text === "") {
    return { effects: odeberMisto.of(id) };
  }
  const kurzor = state.selection.main;

  return {
    changes: { from: pozice, insert: text },
    effects: odeberMisto.of(id),
    selection: state.selection.ranges.length === 1 && kurzor.empty && kurzor.head === pozice
      ? { anchor: pozice + text.length }
      : undefined,
    userEvent: "input.paste",
  };
};
