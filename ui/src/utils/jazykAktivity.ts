import { JazykAktivity } from "../api/program";

/**
 * Jazyky, podle kterých jde program filtrovat. Aktivita vedená česky i anglicky
 * ("cs-en") vyhoví češtině i angličtině.
 */
export const JAZYKY_FILTRU = ["cs", "en", "nezavisla"] as const;

export type JazykFiltru = (typeof JAZYKY_FILTRU)[number];

export const NÁZVY_JAZYKŮ_FILTRU: Record<JazykFiltru, string> = {
  cs: "česky",
  en: "anglicky",
  nezavisla: "jazykově nezávislé",
};

export const NÁZVY_JAZYKŮ_AKTIVITY: Record<JazykAktivity, string> = {
  cs: "česky",
  en: "anglicky",
  "cs-en": "česky i anglicky",
  nezavisla: "jazykově nezávislá",
};

/** Data vygenerovaná před zavedením jazyka aktivity ho nemají, tehdy šlo vždy o češtinu. */
export const jazykAktivity = (aktivita: { jazyk?: JazykAktivity }): JazykAktivity =>
  aktivita.jazyk ?? "cs";

export const jazykyFiltruAktivity = (jazyk: JazykAktivity): JazykFiltru[] =>
  jazyk === "cs-en" ? ["cs", "en"] : [jazyk];

/**
 * Krátký štítek do buňky programu. Česká aktivita (naprostá většina) ani
 * jazykově nezávislá ho nemají, aby tabulka nebyla přeplněná.
 */
export const krátkýŠtítekJazyka = (jazyk: JazykAktivity): string | undefined =>
  jazyk === "en" ? "EN"
    : jazyk === "cs-en" ? "CZ/EN"
      : undefined;
