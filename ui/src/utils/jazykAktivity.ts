import { JazykAktivity } from "../api/program";
import { GAMECON_KONSTANTY } from "../env";
import { Jazyk, KlíčPřekladu, t } from "../i18n";

/**
 * Jazyky, podle kterých jde program filtrovat. Aktivita vedená česky i anglicky
 * ("cs-en") vyhoví češtině i angličtině, jazykově nezávislá vyhoví každé volbě,
 * viz jazykyFiltruAktivity().
 */
export const JAZYKY_FILTRU = ["cs", "en", "nezavisla"] as const;

export type JazykFiltru = (typeof JAZYKY_FILTRU)[number];

/**
 * Filtr jazyků z URL (?jazykAktivity=…). Hodnotu může kdokoli přepsat, takže
 * bere jen pole a z něj jen známé jazyky; bez nich žádný filtr. Neznámý jazyk
 * by jinak shodil celý program (Filtry hledají jeho název).
 */
export const jazykyFiltruZUrl = (hodnota: unknown): JazykFiltru[] | undefined => {
  if (!Array.isArray(hodnota)) return undefined;
  const jazyky = JAZYKY_FILTRU.filter((jazyk) => hodnota.includes(jazyk));
  return jazyky.length ? jazyky : undefined;
};

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

/**
 * Volby filtru, kterým aktivita vyhoví. Jazykově nezávislou aktivitu (deskovka
 * bez textu) zvládne každý, proto ji filtr „anglicky“ i „česky“ nechá; volba
 * „jazykově nezávislé“ ukáže jen nezávislé.
 */
export const jazykyFiltruAktivity = (jazyk: JazykAktivity): JazykFiltru[] =>
  jazyk === "cs-en" ? ["cs", "en"]
    : jazyk === "nezavisla" ? ["cs", "en", "nezavisla"]
      : [jazyk];

/**
 * Krátký štítek do buňky programu. Jazykově nezávislá aktivita ho nemá nikdy,
 * česká (naprostá většina) jen na anglické stránce, kde cizinec potřebuje
 * poznat, čemu nebude rozumět. Na české stránce by štítek u skoro každé
 * aktivity jen přeplnil tabulku.
 */
export const krátkýŠtítekJazyka = (
  jazyk: JazykAktivity,
  jazykStránky: Jazyk = GAMECON_KONSTANTY.JAZYK,
): string | undefined =>
  jazyk === "en" ? "EN"
    : jazyk === "cs-en" ? "CZ/EN"
      : jazyk === "cs" && jazykStránky === "en" ? "CZ"
        : undefined;
