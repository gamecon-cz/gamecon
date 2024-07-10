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

export enum SeskupováníAktivit {
  linie = "linie",
  den = "den",
}

type SkupinyAktivit = { [klíč: string]: Aktivita[] };

export const PROGRAM_DNY_TEXT = GAMECON_KONSTANTY.PROGRAM_DNY.map((x) =>
  formátujDenVTýdnu(x, true)
);

const seskupAktivity = (aktivity: Aktivita[], seskupitPodle = SeskupováníAktivit.linie): SkupinyAktivit => {
  const skupinyAktivit: SkupinyAktivit = Object.create(null);

  const získejKlíč = (seskupitPodle === SeskupováníAktivit.den)
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

type PředpřivenáTabulkaAktivit = { [klíč: string]: { řádek: number, aktivita: Aktivita }[] }

export const připravTabulkuAktivit = (aktivity: Aktivita[], seskupitPodle = SeskupováníAktivit.linie) => {
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


