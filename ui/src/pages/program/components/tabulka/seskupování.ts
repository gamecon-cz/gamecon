import { OdDo } from "../../../../api/program";
import { GAMECON_KONSTANTY } from "../../../../env";
import { denAktivity } from "../../../../store/program/logic/aktivity";
import { Aktivita } from "../../../../store/program/slices/programDataSlice";
import { formátujDenVTýdnu, zip } from "../../../../utils";

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
export const připravTabulkuAktivit = (aktivity: Aktivita[], seskupitPodle = SeskupováníAktivit.linie): PředpřivenáTabulkaAktivit | PředpřivenáTabulkaAktivitHierarchie => {
  if (seskupitPodle === SeskupováníAktivit.denALinie || seskupitPodle === SeskupováníAktivit.mistnost) {
    return připravTabulkuAktivitDenALinie(aktivity, seskupitPodle);
  }

  const seskupené = seskupAktivity(aktivity, seskupitPodle);

  const zpracujSkupinu = (skupina: Aktivita[]): PředpřivenáTabulkaAktivit["klíč"] =>
    zip(skupina, časyDoŘádkůBezPřekryvu(skupina.map(x => x.cas))).map(([aktivita, řádek]) => ({ aktivita, řádek }));


  const tabulka: PředpřivenáTabulkaAktivit = Object.fromEntries(
    Object.entries(seskupené).map(([klíč, skupina]) =>
      ([klíč, zpracujSkupinu(skupina)])
    )
  );

  return tabulka;
};

const připravTabulkuAktivitDenALinie = (aktivity: Aktivita[], seskupitPodle = SeskupováníAktivit.linie): PředpřivenáTabulkaAktivitHierarchie => {
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
    zip(skupina, časyDoŘádkůBezPřekryvu(skupina.map(x => x.cas))).map(([aktivita, řádek]) => ({ aktivita, řádek }));

  const výsledek: PředpřivenáTabulkaAktivitHierarchie = Object.fromEntries(
    Object.entries(aktivitySeskupDen).map(([denKlíč, aktivitySeskupDenyDen]) => {
      const skupiny: { [linieKlíč: string]: Aktivita[] } = {};

      if (seskupitPodle === SeskupováníAktivit.mistnost)
        aktivitySeskupDenyDen.sort((a,b)=> (a.mistnosti?.[0]?.poradi ?? Number.MAX_SAFE_INTEGER) - (b.mistnosti?.[0]?.poradi ?? Number.MAX_SAFE_INTEGER) )

      for (const aktivita of aktivitySeskupDenyDen) {
        const klíč = seskupitPodle === SeskupováníAktivit.mistnost ?
        (aktivita.mistnosti?.length ? aktivita.mistnosti[0].nazev ?? "" : "")
        : aktivita.linie;
        if (!skupiny[klíč]) skupiny[klíč] = [];
        skupiny[klíč].push(aktivita);
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


