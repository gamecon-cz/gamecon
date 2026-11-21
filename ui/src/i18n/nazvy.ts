import { GAMECON_KONSTANTY } from "../env";
import type { ApiAktivitaNepřihlášen, ApiTag } from "../api/program";
import type { Jazyk } from "./jazyk";

/*
 * Názvy z DB (linie, štítky a jejich kategorie) pro čtenáře v jazyce stránky.
 * Statické JSONy programu sdílí oba jazyky webu, anglický název proto chodí vedle
 * českého (ProgramStaticFileGenerator). Český zůstává klíčem (seskupení v tabulce,
 * filtry v URL), tady se jen vybírá, co se zobrazí.
 */

/**
 * Na anglické stránce anglický text, když ho data mají, jinak český. Chybí v JSONech
 * vygenerovaných před zavedením anglických názvů (starší ročníky), proto `undefined`.
 */
export const textVJazyce = (
  český: string,
  anglický: string | null | undefined,
  jazyk: Jazyk = GAMECON_KONSTANTY.JAZYK,
): string => (jazyk === "en" && anglický ? anglický : český);

export const názevŠtítku = (tag: Pick<ApiTag, "nazev" | "nazevEn">, jazyk?: Jazyk): string =>
  textVJazyce(tag.nazev, tag.nazevEn, jazyk);

export const názevKategorieŠtítku = (
  tag: Pick<ApiTag, "nazevKategorie" | "nazevKategorieEn">,
  jazyk?: Jazyk,
): string => textVJazyce(tag.nazevKategorie, tag.nazevKategorieEn, jazyk);

export const názevLinie = (
  aktivita: Pick<ApiAktivitaNepřihlášen, "linie" | "linieEn">,
  jazyk?: Jazyk,
): string => textVJazyce(aktivita.linie, aktivita.linieEn, jazyk);

/** Český název linie (klíč skupiny a filtru) → název pro čtenáře. */
export const názvyLinií = (
  aktivity: Pick<ApiAktivitaNepřihlášen, "linie" | "linieEn">[],
  jazyk?: Jazyk,
): Record<string, string> =>
  Object.fromEntries(aktivity.map((aktivita) => [aktivita.linie, názevLinie(aktivita, jazyk)]));

export type ŠtítkyKategorie<T> = {
  /** český název, klíč */
  kategorie: string,
  /** název kategorie pro čtenáře */
  názevKategorie: string,
  tagy: T[],
};

/**
 * Štítky po kategoriích v pořadí ze serveru (pořadí kategorie, český název). Na anglické
 * stránce seřadí štítky v kategorii podle anglického názvu, české pořadí by tam nedávalo smysl.
 */
export const štítkyPodleKategorie = <T extends ApiTag>(
  tagy: T[],
  jazyk: Jazyk = GAMECON_KONSTANTY.JAZYK,
): ŠtítkyKategorie<T>[] => {
  const podleKategorie = new Map<string, ŠtítkyKategorie<T>>();
  for (const tag of tagy) {
    const skupina = podleKategorie.get(tag.nazevKategorie)
      ?? { kategorie: tag.nazevKategorie, názevKategorie: názevKategorieŠtítku(tag, jazyk), tagy: [] };
    skupina.tagy.push(tag);
    podleKategorie.set(tag.nazevKategorie, skupina);
  }
  const skupiny = [...podleKategorie.values()];
  if (jazyk !== "en") return skupiny;

  const řazení = new Intl.Collator("en", { numeric: true, sensitivity: "base" });
  return skupiny.map((skupina) => ({
    ...skupina,
    tagy: [...skupina.tagy].sort((a, b) => řazení.compare(názevŠtítku(a, jazyk), názevŠtítku(b, jazyk))),
  }));
};
