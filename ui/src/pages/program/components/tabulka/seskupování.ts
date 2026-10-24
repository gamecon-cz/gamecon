import { OdDo } from "../../../../api/program";
import { GAMECON_KONSTANTY } from "../../../../env";
import { denAktivity } from "../../../../store/program/logic/aktivity";
import { Aktivita } from "../../../../store/program/slices/programDataSlice";
import { formátujDenVTýdnu, zip } from "../../../../utils";
import { pražskéHodiny, pražskéMinuty } from "../../../../utils/czech-time";

/** Aktivity začínají a končí po čtvrthodinách, takže každá hodina má v tabulce čtyři sloupce. */
export const SLOUPCŮ_ZA_HODINU = 4;
const MINUT_ZA_SLOUPEC = 60 / SLOUPCŮ_ZA_HODINU;
const DÉLKA_SLOUPCE_MS = MINUT_ZA_SLOUPEC * 60 * 1_000;
const SLOUPCŮ_ZA_DEN = 24 * SLOUPCŮ_ZA_HODINU;

/**
 * Zarovná čas aktivity na sloupce tabulky – začátek dolů, konec nahoru, aspoň na jeden sloupec.
 * Editor aktivit jiné časy než po čtvrthodinách nenabízí, zarovnání jen hlídá, aby starší
 * aktivita s „necelým“ časem nerozbila tabulku. Pražský posun od UTC je v celých hodinách,
 * takže zaokrouhlený timestamp leží i na pražské čtvrthodině.
 */
const zarovnejNaSloupce = ({ od, do: časDo }: OdDo): OdDo => {
  const sloupecOd = Math.floor(od / DÉLKA_SLOUPCE_MS);
  const sloupecDo = Math.max(Math.ceil(časDo / DÉLKA_SLOUPCE_MS), sloupecOd + 1);

  return { od: sloupecOd * DÉLKA_SLOUPCE_MS, do: sloupecDo * DÉLKA_SLOUPCE_MS };
};

/** Index sloupce tabulky, ve kterém leží daný čas, počítaný od začátku programového dne */
const sloupecProgramu = (čas: number): number => {
  const datum = new Date(čas);
  const sloupecDne = pražskéHodiny(datum) * SLOUPCŮ_ZA_HODINU
    + Math.floor(pražskéMinuty(datum) / MINUT_ZA_SLOUPEC);

  return (sloupecDne - GAMECON_KONSTANTY.PROGRAM_ZACATEK * SLOUPCŮ_ZA_HODINU + SLOUPCŮ_ZA_DEN) % SLOUPCŮ_ZA_DEN;
};

/** Inline styl buňky přes daný počet sloupců, šířku podle něj počítá program-trida.css */
export const stylŠířkyBuňky = (sloupců: number): string =>
  `--hodin: ${sloupců / SLOUPCŮ_ZA_HODINU}`;

export type BuňkaŘádku = {
  aktivita: Aktivita,
  /** Počet prázdných sloupců před aktivitou */
  odsazení: number,
  /** Počet sloupců, které aktivita zabírá */
  šířka: number,
};

/**
 * Rozloží aktivity jednoho řádku tabulky do sloupců po čtvrthodinách.
 * Aktivity v řádku se nesmí překrývat, viz časyDoŘádkůBezPřekryvu.
 * @returns buňky aktivit a počet prázdných sloupců za poslední z nich
 */
export const rozložŘádekDoSloupců = (
  aktivity: Aktivita[],
  početSloupců: number,
): { buňky: BuňkaŘádku[], zbývá: number } => {
  let obsazenoDo = 0;
  const buňky = [...aktivity]
    .sort((a1, a2) => a1.cas.od - a2.cas.od)
    .map((aktivita) => {
      const čas = zarovnejNaSloupce(aktivita.cas);
      const sloupecOd = sloupecProgramu(čas.od);
      // aktivita končící až po konci programu (např. celodenní brigáda) se ořízne na konec tabulky
      const šířka = Math.min(
        Math.round((čas.do - čas.od) / DÉLKA_SLOUPCE_MS),
        Math.max(početSloupců - sloupecOd, 1),
      );
      const odsazení = Math.max(sloupecOd - obsazenoDo, 0);
      obsazenoDo = sloupecOd + šířka;

      return { aktivita, odsazení, šířka };
    });

  return { buňky, zbývá: Math.max(početSloupců - obsazenoDo, 0) };
};

/**
 * Pro array časů vrátí indexy řádků tak
 *   aby nedocházelo na jednotlivých řádcích k překryvům
 */
const časyDoŘádkůBezPřekryvu = (rozsahy: OdDo[]): number[] => {
  const rozsahySIndexem = rozsahy.map((x, i) => ({ ...x, i }));
  rozsahySIndexem.sort((a, b) => a.od - b.od);
  const řádky = Array(rozsahy.length);

  // určitě existuje rychlejší způsob
  let indexŘádku = 0;
  while (rozsahySIndexem.length) {
    let popIndex = 0;
    do {
      const { i, do: časDo } = rozsahySIndexem.splice(popIndex, 1)[0];
      řádky[i] = indexŘádku;
      popIndex = rozsahySIndexem.findIndex((x) => x.od >= časDo);
    } while (popIndex !== -1);
    indexŘádku++;
  }

  return řádky;
};

// todo: umožnit primární a sekundární seskupování v libovolné kombinaci. Takže denALinie by bylo třeba primární linie a sekundární den
export enum SeskupováníAktivit {
  linie = "linie",
  den = "den",
  mistnost = "mistnost",
  denALinie = "denALinie",
}

type SkupinyAktivit = { [klíč: string]: Aktivita[] };

export const PROGRAM_DNY_TEXT = GAMECON_KONSTANTY.PROGRAM_DNY.map((x) =>
  formátujDenVTýdnu(x, true)
);

const seskupAktivity = (aktivity: Aktivita[], seskupitPodle = SeskupováníAktivit.linie): SkupinyAktivit => {
  const skupinyAktivit: SkupinyAktivit = Object.create(null);

  const získejKlíč = (seskupitPodle === SeskupováníAktivit.mistnost)
    ? (aktivita: Aktivita) => aktivita.mistnosti?.length ? aktivita.mistnosti[0].nazev ?? "" : ""
    : (seskupitPodle === SeskupováníAktivit.den)
    ? (aktivita: Aktivita) => formátujDenVTýdnu(denAktivity(new Date(aktivita.cas.od)), true)
    : (aktivita: Aktivita) => aktivita.linie
    ;

  if (seskupitPodle === SeskupováníAktivit.den) {
    PROGRAM_DNY_TEXT.forEach(den => {
      skupinyAktivit[den] = [];
    });
  }

  for (let i = aktivity.length; i--;) {
    const aktivita = aktivity[i];
    const klíč = získejKlíč(aktivita);
    if (!skupinyAktivit[klíč]) skupinyAktivit[klíč] = [];
    skupinyAktivit[klíč].push(aktivita);
  }

  return skupinyAktivit;
};

export type PředpřivenáTabulkaAktivit = { [klíč: string]: { řádek: number, aktivita: Aktivita }[] }
export type PředpřivenáTabulkaAktivitHierarchie = { [denKlíč: string]: PředpřivenáTabulkaAktivit }

// todo: vždy bude vracet typ PředpřivenáTabulkaAktivitHierarchie. Pokud bude obsahovat pouze jeden klíč tak se zobrazovat nebude.
export const připravTabulkuAktivit = (aktivity: Aktivita[], seskupitPodle = SeskupováníAktivit.linie, prázdnéMístnosti: string[] = []): PředpřivenáTabulkaAktivit | PředpřivenáTabulkaAktivitHierarchie => {
  if (seskupitPodle === SeskupováníAktivit.denALinie || seskupitPodle === SeskupováníAktivit.mistnost) {
    return připravTabulkuAktivitDenALinie(aktivity, seskupitPodle, prázdnéMístnosti);
  }

  const seskupené = seskupAktivity(aktivity, seskupitPodle);

  const zpracujSkupinu = (skupina: Aktivita[]): PředpřivenáTabulkaAktivit["klíč"] =>
    zip(skupina, časyDoŘádkůBezPřekryvu(skupina.map(x => zarovnejNaSloupce(x.cas)))).map(([aktivita, řádek]) => ({ aktivita, řádek }));


  const tabulka: PředpřivenáTabulkaAktivit = Object.fromEntries(
    Object.entries(seskupené).map(([klíč, skupina]) =>
      ([klíč, zpracujSkupinu(skupina)])
    )
  );

  return tabulka;
};

const připravTabulkuAktivitDenALinie = (aktivity: Aktivita[], seskupitPodle = SeskupováníAktivit.linie, prázdnéMístnosti: string[] = []): PředpřivenáTabulkaAktivitHierarchie => {
  const aktivitySeskupDen: { [denKlíč: string]: Aktivita[] } = {};

  PROGRAM_DNY_TEXT.forEach(den => {
    aktivitySeskupDen[den] = [];
  });

  for (const aktivita of aktivity) {
    const denKlíč = formátujDenVTýdnu(denAktivity(new Date(aktivita.cas.od)), true);
    if (!aktivitySeskupDen[denKlíč]) aktivitySeskupDen[denKlíč] = [];
    aktivitySeskupDen[denKlíč].push(aktivita);
  }

  const zpracujSkupinu = (skupina: Aktivita[], seskupitPodle = SeskupováníAktivit.linie): PředpřivenáTabulkaAktivit["klíč"] =>
    zip(skupina, časyDoŘádkůBezPřekryvu(skupina.map(x => zarovnejNaSloupce(x.cas)))).map(([aktivita, řádek]) => ({ aktivita, řádek }));

  const výsledek: PředpřivenáTabulkaAktivitHierarchie = Object.fromEntries(
    Object.entries(aktivitySeskupDen).map(([denKlíč, aktivitySeskupDenyDen]) => {
      const skupiny: { [linieKlíč: string]: Aktivita[] } = {};

      // V zobrazení po místnostech předvyplníme všechny místnosti (i prázdné),
      // ať se každý den vypíše kompletní rozpis místností ve správném pořadí.
      if (seskupitPodle === SeskupováníAktivit.mistnost) {
        for (const místnost of prázdnéMístnosti)
          skupiny[místnost] = [];

        // Skupiny (místnosti) zakládáme předem v pořadí dle `poradi`. Řádky
        // tak vyjdou vždy stejně i bez předvyplněné kostry prázdných místností
        // (např. při aktivním filtru); jinak by pořadí místností záviselo na
        // pořadí polí `mistnosti` z API u první vícemístnostní aktivity, protože
        // ProgramTabulka řadí skupiny místností jen podle pořadí jejich vzniku.
        const poradiPodleNazvu: { [nazev: string]: number } = {};
        for (const aktivita of aktivitySeskupDenyDen)
          for (const místnost of aktivita.mistnosti ?? [])
            poradiPodleNazvu[místnost.nazev ?? ""] = místnost.poradi;
        Object.keys(poradiPodleNazvu)
          .sort((a, b) => poradiPodleNazvu[a] - poradiPodleNazvu[b])
          .forEach((nazev) => {
            if (!skupiny[nazev]) skupiny[nazev] = [];
          });
      }

      for (const aktivita of aktivitySeskupDenyDen) {
        if (seskupitPodle === SeskupováníAktivit.mistnost) {
          // Aktivita může zabírat víc místností najednou. Zařadíme ji do
          // KAŽDÉ z nich, aby byl rozpis obsazenosti každé místnosti úplný –
          // dřív se použila jen mistnosti[0] (hlavní lokace) a v ostatních
          // sálech aktivita chyběla, takže vypadaly volně.
          const klíče = aktivita.mistnosti?.length
            ? aktivita.mistnosti.map((místnost) => místnost.nazev ?? "")
            : [""];
          for (const klíč of klíče) {
            if (!skupiny[klíč]) skupiny[klíč] = [];
            skupiny[klíč].push(aktivita);
          }
        } else {
          const klíč = aktivita.linie;
          if (!skupiny[klíč]) skupiny[klíč] = [];
          skupiny[klíč].push(aktivita);
        }
      }

      const tabulkaProDen: PředpřivenáTabulkaAktivit = Object.fromEntries(
        Object.entries(skupiny).map(([linieKlíč, skupina]) =>
          ([linieKlíč, zpracujSkupinu(skupina)])
        )
      );

      return [denKlíč, tabulkaProDen];
    })
  );

  return výsledek;
};


