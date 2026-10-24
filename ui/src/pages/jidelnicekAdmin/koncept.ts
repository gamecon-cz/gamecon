import { ApiAdminMealMenuSlot, ApiMealDishWrite } from "../../api/symfony/types";

/** Rozpracované jídlo v editoru; uložené má `id`, nové ne. */
export type KonceptJidla = {
  /** Stabilní klíč řádku pro Preact, i u ještě neuloženého jídla. */
  klic: string;
  id?: number;
  name: string;
  description: string;
  vegetarian: boolean;
  default: boolean;
  /** Kolik porcí si jídlo vybralo; u nového 0. */
  chosenCount: number;
};

let posledniKlic = 0;
const novyKlic = (): string => `nove-${++posledniKlic}`;

export const konceptZeSlotu = (slot: ApiAdminMealMenuSlot): KonceptJidla[] =>
  slot.dishes.map((jidlo) => ({
    klic: `jidlo-${jidlo.id}`,
    id: jidlo.id,
    name: jidlo.name,
    description: jidlo.description ?? "",
    vegetarian: jidlo.vegetarian,
    default: jidlo.default,
    chosenCount: jidlo.chosenCount,
  }));

export const noveJidlo = (): KonceptJidla => ({
  klic: novyKlic(),
  name: "",
  description: "",
  vegetarian: false,
  default: false,
  chosenCount: 0,
});

/**
 * Úplně prázdný nový řádek se neposílá — vznikne kliknutím na „Přidat jídlo" a nikdo by
 * nečekal, že kvůli němu uložení selže. Řádek s popisem, ale bez názvu, už chyba je.
 */
export const doZapisu = (koncept: KonceptJidla[]): ApiMealDishWrite[] =>
  koncept
    .filter((jidlo) => jidlo.id !== undefined || jidlo.name.trim() !== "" || jidlo.description.trim() !== "")
    .map((jidlo) => ({
      ...(jidlo.id !== undefined ? { id: jidlo.id } : {}),
      name: jidlo.name.trim(),
      description: jidlo.description.trim() === "" ? null : jidlo.description.trim(),
      vegetarian: jidlo.vegetarian,
      default: jidlo.default,
    }));

export const jeZmeneno = (koncept: KonceptJidla[], slot: ApiAdminMealMenuSlot): boolean =>
  JSON.stringify(doZapisu(koncept)) !== JSON.stringify(doZapisu(konceptZeSlotu(slot)));

/**
 * Oběd a večeře mívají stejnou nabídku. Kopie vznikne jako nová jídla, výběry se nepřenášejí;
 * výchozí jídlo se převezme, jen když ho cíl ještě nemá — dvě výchozí by server odmítl.
 */
export const pridejKopii = (cil: KonceptJidla[], zdroj: KonceptJidla[]): KonceptJidla[] => {
  const cilMaVychozi = cil.some((jidlo) => jidlo.default);
  return [
    ...cil,
    ...zdroj
      .filter((jidlo) => jidlo.name.trim() !== "")
      .map((jidlo) => ({
        ...jidlo,
        klic: novyKlic(),
        id: undefined,
        chosenCount: 0,
        default: cilMaVychozi ? false : jidlo.default,
      })),
  ];
};

export const nastavVychozi = (koncept: KonceptJidla[], klic: string): KonceptJidla[] =>
  koncept.map((jidlo) => ({ ...jidlo, default: jidlo.klic === klic }));

export const posun = (koncept: KonceptJidla[], index: number, o: -1 | 1): KonceptJidla[] => {
  const cil = index + o;
  if (cil < 0 || cil >= koncept.length) return koncept;
  const novy = [...koncept];
  [novy[index], novy[cil]] = [novy[cil], novy[index]];
  return novy;
};

/** Výchozí jídlo bez označení je první — stejně to bere server. */
export const jeVychozi = (koncept: KonceptJidla[], jidlo: KonceptJidla): boolean =>
  koncept.some((kandidat) => kandidat.default)
    ? jidlo.default
    : koncept[0]?.klic === jidlo.klic;
