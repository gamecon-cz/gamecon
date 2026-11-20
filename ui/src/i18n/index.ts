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

/**
 * t() pro větu s prvkem uprostřed (zvýrazněný odpočet): parametr z `prvky` nevloží
 * jako text, vrátí části věty, které Preact vykreslí za sebou. Pořadí slov se tak
 * v každém jazyce řídí slovníkem. Text parametru, který obsahuje „{název}“ prvku,
 * se za prvek nezamění, dosazuje se v jednom průchodu.
 */
export const tSPrvky = <T>(
  klíč: KlíčPřekladu,
  prvky: Record<string, T>,
  parametry: Record<string, string | number> = {},
  jazyk: Jazyk = GAMECON_KONSTANTY.JAZYK,
): (string | T)[] =>
  ((SLOVNÍKY[jazyk] ?? cs)[klíč] ?? String(klíč))
    .split(/(\{\w+\})/)
    .reduce<(string | T)[]>((části, část) => {
      const název = /^\{(\w+)\}$/.exec(část)?.[1];
      if (název !== undefined && název in prvky) return [...části, prvky[název]];

      const text = název !== undefined && název in parametry ? String(parametry[název]) : část;
      if (text === "") return části;
      const poslední = části[části.length - 1];
      // sousední text v jedné části, ať Preact nevyrábí zbytečné textové uzly
      return typeof poslední === "string"
        ? [...části.slice(0, -1), poslední + text]
        : [...části, text];
    }, []);
