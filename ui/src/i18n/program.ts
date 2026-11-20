import { t, Jazyk } from ".";
import { GAMECON_KONSTANTY } from "../env";
import type { Pohlavi } from "../api/přihlášenýUživatel";

/*
 * Texty programu, které nejsou jen jeden klíč: rod, cena, zkratka dne.
 */

/** „Přihlášen“ / „Přihlášena“ v legendě programu; bez známého pohlaví mužský tvar jako dřív */
export const popisekPřihlášen = (pohlavi: Pohlavi | undefined, jazyk?: Jazyk): string =>
  t(pohlavi === "f" ? "program.legenda.prihlasen_f" : "program.legenda.prihlasen_m", {}, jazyk);

/**
 * Cena v náhledu aktivity: „zdarma“ / „free“ u nulové, jinak částka. Česky bez měny,
 * jak byla (měnu nese ikona), anglicky „CZK 1,500“ jako v přehledu financí, aby ji
 * cizinec nevzal za eura.
 */
export const cenaAktivity = (cena: number, jazyk: Jazyk = GAMECON_KONSTANTY.JAZYK): string =>
  cena === 0
    ? t("program.nahled.zdarma", {}, jazyk)
    : t("program.nahled.cena", {
      cena: jazyk === "en" ? cena.toLocaleString("en-GB", { maximumFractionDigits: 2 }) : cena,
    }, jazyk);

/**
 * Cena aktivity pro účastníka: základ krát jeho sleva (násobič 0 = zdarma, chybějící
 * = plná cena), a základní cena k porovnání, když se od ní liší násobič.
 */
export const cenyNáhledu = (
  aktivita: { cenaZaklad: number, slevaNasobic?: number | null },
  jazyk?: Jazyk,
): { cena: string, základní: string | undefined } => ({
  cena: cenaAktivity(aktivita.cenaZaklad * (aktivita.slevaNasobic ?? 1), jazyk),
  základní: aktivita.slevaNasobic !== 1 ? `*${cenaAktivity(aktivita.cenaZaklad, jazyk)}` : undefined,
});

/** „pá“ / „Fri“, zkratka dne v týdnu v jazyce stránky */
export const zkratkaDne = (datum: Date, jazyk: Jazyk = GAMECON_KONSTANTY.JAZYK): string =>
  datum.toLocaleDateString(jazyk === "en" ? "en-GB" : "cs-CZ", { weekday: "short" });
