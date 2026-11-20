import { GAMECON_KONSTANTY } from "../env";
import { cs } from "./cs";
import { en } from "./en";
import { Jazyk } from "./jazyk";

export type { Jazyk } from "./jazyk";

export type KlíčPřekladu = keyof typeof cs;

export type Slovník = Record<KlíčPřekladu, string>;

const SLOVNÍKY: Record<Jazyk, Slovník> = { cs, en };

/**
 * Text rozhraní v jazyce stránky (GAMECON_KONSTANTY.JAZYK), protějšek PHP t().
 * Na pár stovek textů stačí slovník v bundlu, i18next by byl zbytečně velký.
 * Klíč, který ve slovníku není (za běhu, typ ho nepustí), vrátí sám sebe
 * jako PHP t(), aby kvůli jednomu textu nespadla celá stránka.
 */
export const t = (
  klíč: KlíčPřekladu,
  parametry: Record<string, string | number> = {},
  jazyk: Jazyk = GAMECON_KONSTANTY.JAZYK,
): string =>
  ((SLOVNÍKY[jazyk] ?? cs)[klíč] ?? String(klíč)).replace(
    /\{(\w+)\}/g,
    (celé: string, název: string) => (název in parametry ? String(parametry[název]) : celé),
  );
