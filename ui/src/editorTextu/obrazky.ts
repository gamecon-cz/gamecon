import { type EditorState, StateEffect, StateField, type TransactionSpec } from "@codemirror/state";
import { Decoration, EditorView, WidgetType } from "@codemirror/view";

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

export const pridejMisto = StateEffect.define<Misto>();
export const odeberMisto = StateEffect.define<number>();
const posunMisto = StateEffect.define<{ id: number; pozice: number }>();

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
 * Místa, kam přijdou nahrávané obrázky, v pořadí, v jakém se vložila, se značkami „Nahrávám…“. Nejsou součástí
 * textu, takže se neuloží, kdyby se text uložil dřív, než se obrázek nahraje, a posouvají se s textem, který se kolem
 * nich mezitím píše. Pozice posouvá pole samo: dekoraci, přes kterou se smaže text, by CodeMirror zahodil, místo se
 * tak jen přesune na začátek smazaného úseku a nahraný obrázek do textu přijde, i když se mezitím přepsal odstavec,
 * ve kterém čekal.
 */
export const mistaObrazku = StateField.define<readonly Misto[]>({
  create: () => [],
  update(mista, transakce) {
    let nova = transakce.docChanged && mista.length > 0
      ? mista.map((misto) => ({ ...misto, pozice: transakce.changes.mapPos(misto.pozice, -1) }))
      : mista;
    for (const efekt of transakce.effects) {
      if (efekt.is(pridejMisto)) {
        nova = [...nova, efekt.value];
      } else if (efekt.is(odeberMisto)) {
        nova = nova.filter((misto) => misto.id !== efekt.value);
      } else if (efekt.is(posunMisto)) {
        const { id, pozice } = efekt.value;
        nova = nova.map((misto) => misto.id === id ? { ...misto, pozice } : misto);
      }
    }

    return nova;
  },
  // značka vlevo od kurzoru (`side: -1`): co se po vložení píše dál, zůstane za obrázkem; značky na stejné pozici
  // zůstanou v pořadí míst, řazení je stabilní
  provide: (pole) => EditorView.decorations.from(pole, (mista) => Decoration.set(
    mista.map(({ id, pozice, popis }) =>
      Decoration.widget({ widget: new ZnackaNahravani(id, popis), side: -1 }).range(pozice)),
    true,
  )),
});

export const poziceMista = (state: EditorState, id: number): number | null =>
  state.field(mistaObrazku).find((misto) => misto.id === id)?.pozice ?? null;

/**
 * Vloží zápis nahraných obrázků na jejich místo a místo odebere (i když se nic nenahrálo). Je to obyčejný krok
 * historie, Ctrl+Z ho vrátí celý. Kurzor, který na místě čeká, se posune za obrázek, jako po vložení textu, a stejně
 * tak místa, která na stejné pozici čekají za ním: obrázky vložené rychle po sobě přijdou v pořadí, v jakém se
 * vložily.
 */
export const vlozNaMisto = (state: EditorState, id: number, text: string): TransactionSpec => {
  const mista = state.field(mistaObrazku);
  const poradi = mista.findIndex((misto) => misto.id === id);
  if (poradi === -1 || text === "") {
    return { effects: odeberMisto.of(id) };
  }
  const { pozice } = mista[poradi];
  const kurzor = state.selection.main;
  const posunuta = mista.slice(poradi + 1)
    .filter((misto) => misto.pozice === pozice)
    .map((misto) => posunMisto.of({ id: misto.id, pozice: pozice + text.length }));

  return {
    changes: { from: pozice, insert: text },
    effects: [odeberMisto.of(id), ...posunuta],
    selection: state.selection.ranges.length === 1 && kurzor.empty && kurzor.head === pozice
      ? { anchor: pozice + text.length }
      : undefined,
    userEvent: "input.paste",
  };
};
