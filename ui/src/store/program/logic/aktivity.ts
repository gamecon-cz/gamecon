import { APIŠtítek, AktivitaStav } from "../../../api/program";
import { Pohlavi } from "../../../api/přihlášenýUživatel";
import { volnoTypZObsazenost } from "../../../utils";
import { Aktivita } from "../slices/programDataSlice";
// Pozor musí být defaultní import!
import FlexSearch from "flexsearch";

export type FiltrProgramTabulkaVýběr =
  | {
    typ: "můj";
  }
  | {
    typ: "den";
    datum: Date;
  }
  ;

export type MapováníŠtítků = {
  /** Klíč je id (APIŠtítek.id) hodnota je kategorie štítku (APIŠtítek.nazevKategorie) */
  idDoKategorie: {
    [štítekId: string]: string
  },
  /** Klíč je název (APIŠtítek.nazev) hodnota je id (APIŠtítek.id) */
  štítekLowercaseDoId: {
    [štítekId: string]: string
  },
}

export const vytvořMapováníŠtítků = (štítky: APIŠtítek[]): MapováníŠtítků => {
  const idDoKategorie = Object.fromEntries(štítky.map(x => [x.id, x.nazevKategorie]));
  const štítekLowercaseDoId = Object.fromEntries(štítky.map(x => [x.nazev.toLowerCase(), x.id]));
  return {
    idDoKategorie,
    štítekLowercaseDoId,
  };
};

export type FiltrAktivit = Partial<{
  ročník: number,
  výběr: FiltrProgramTabulkaVýběr,
  filtrPřihlašovatelné: boolean,
  filtrLinie: string[],
  filtrTagy: string[],
  filtrStavAktivit: AktivitaStav[],
  filtrText: string,
}>;

export const aktivitaStatusZAktivity = (
  aktivita: Aktivita,
  pohlavi?: Pohlavi | undefined
): AktivitaStav => {
  if (
    aktivita.stavPrihlaseni != undefined &&
    aktivita.stavPrihlaseni !== "sledujici"
  ) {
    return "prihlasen";
  }
  if (aktivita.vedu) {
    return "organizator";
  }
  if (aktivita.stavPrihlaseni === "sledujici") {
    return "nahradnik";
  }
  if (aktivita.vdalsiVlne) {
    return "vDalsiVlne";
  }
  if (aktivita.vBudoucnu) {
    return "vBudoucnu";
  }

  if (aktivita.obsazenost) {
    const volnoTyp = volnoTypZObsazenost(aktivita.obsazenost);
    if (volnoTyp !== "u" && volnoTyp !== pohlavi) {
      return "plno";
    }
  }
  return "volno";
};

// TODO: přidat zbytek filtrů
export const filtrujAktivity = (aktivity: Aktivita[], filtr: FiltrAktivit, mapováníŠtítků: MapováníŠtítků) => {
  const {
    filtrLinie, filtrPřihlašovatelné, filtrTagy, ročník, výběr, filtrStavAktivit, filtrText
  } = filtr;

  let aktivityFiltrované = aktivity;

  if (ročník)
    aktivityFiltrované = aktivityFiltrované
      .filter(aktivita => new Date(aktivita.cas.od).getFullYear() === ročník);

  if (výběr !== undefined)
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        výběr.typ === "můj"
          ? aktivita?.stavPrihlaseni != undefined
          : new Date(aktivita.cas.od).getDay() === výběr.datum.getDay()
      );

  if (filtrLinie)
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        filtrLinie.some(x => x === aktivita.linie)
      );

  if (filtrTagy) {
    const filtrŠtítkyId = filtrTagy.map(štítek => mapováníŠtítků.štítekLowercaseDoId[štítek.toLowerCase()]);
    // TODO: pravděpodobně existuje lepší řešení (tohle mapování má asi hodně bodů kde může dojít k chybě) pravděpodobně je dobré získat idkategorie hned z názvu v URL (v url chceme nějak zachovat pseudočitelnou verzi textu ať jde z url uhádnout co se filtruje)
    if (filtrŠtítkyId.some(štítekId => !štítekId)) {
      console.error(`nenalezeny štítky ${filtrŠtítkyId.map((x, i) => [x, i] as const).filter(x => !x[0]).map(x => filtrTagy[x[1]]).join(",")}`);
    }

    const štítkyIdPodleKategorie: { [kategorie: string]: string[] } = {};
    for (const štítekId of filtrŠtítkyId) {
      const kategorieŠtítku = mapováníŠtítků.idDoKategorie[štítekId] ?? "";
      if (!kategorieŠtítku) {
        console.error(`nenalezena kategorie pro štítek id: ${štítekId}`);
      }
      const kategorie = štítkyIdPodleKategorie[kategorieŠtítku] = štítkyIdPodleKategorie[kategorieŠtítku] ?? [];
      kategorie.push(štítekId);
    }

    const štítkyIdPodleKategorieValues = Object.values(štítkyIdPodleKategorie);
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        štítkyIdPodleKategorieValues.every(štítkyIdZKategorie =>
          štítkyIdZKategorie.some(štítekIdZKategorie =>
            aktivita.stitkyId
              .some(štítekId => štítekId === štítekIdZKategorie))
        )
      );
  }

  // TODO: přihlašovatelnost aktivity dle pohlaví
  // TODO: přihlašovatelnost aktivity dle pohlaví přidat tooltip na tlačítko
  if (filtrStavAktivit)
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        filtrStavAktivit.some(x => aktivitaStatusZAktivity(aktivita) === x)
      );

  if (filtrPřihlašovatelné)
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        aktivita.prihlasovatelna && !aktivita.probehnuta
      );

  if (filtrText) {
    const flexDocument = new FlexSearch.Document<Aktivita, true>({
      language: "cs",
      tokenize: "forward",
      preset: "performance",
      document: {
        id: "id",
        store: true,
        index: [
          // zanořené vlasnosti se přidávají neco:vlastnost
          "nazev",
          "kratkyPopis",
          "popis",
          "vypraveci[]",
          //"stitky[]",
          "cenaZaklad",
          "casText",
          //"linie",
        ],
      }
    });

    for (const aktivita of aktivityFiltrované) {
      flexDocument.add(aktivita);
    }

    const výsledek = flexDocument.search(filtrText, {
      limit: 1000,
    });

    let idčka = výsledek.flatMap(x => x.result) as number[];
    idčka = Array.from(new Set(idčka));

    const filtr = idčka.map(id =>
      // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
      (flexDocument as any).get(id) as Aktivita
    );

    aktivityFiltrované = filtr;
  }

  return aktivityFiltrované;
};

