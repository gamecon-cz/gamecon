import { JazykAktivity } from "../api/program";
import { KlíčPřekladu, t } from "../i18n";

/**
 * Jazyky, podle kterých jde program filtrovat. Aktivita vedená česky i anglicky
 * ("cs-en") vyhoví češtině i angličtině.
 */
export const JAZYKY_FILTRU = ["cs", "en", "nezavisla"] as const;

export type JazykFiltru = (typeof JAZYKY_FILTRU)[number];

const KLÍČE_JAZYKŮ_FILTRU: Record<JazykFiltru, KlíčPřekladu> = {
  cs: "jazyk_filtru.cesky",
  en: "jazyk_filtru.anglicky",
  nezavisla: "jazyk_filtru.nezavisle",
};

/** Klíče sdílí PHP katalog, viz Gamecon\Aktivita\JazykAktivity::popisek() */
const KLÍČE_JAZYKŮ_AKTIVITY: Record<JazykAktivity, KlíčPřekladu> = {
  cs: "jazyk_aktivity.cesky",
  en: "jazyk_aktivity.anglicky",
  "cs-en": "jazyk_aktivity.cesky_i_anglicky",
  nezavisla: "jazyk_aktivity.nezavisla",
};

export const názevJazykaFiltru = (jazyk: JazykFiltru): string =>
  t(KLÍČE_JAZYKŮ_FILTRU[jazyk]);

export const názevJazykaAktivity = (jazyk: JazykAktivity): string =>
  t(KLÍČE_JAZYKŮ_AKTIVITY[jazyk]);

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
