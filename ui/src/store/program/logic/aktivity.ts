import { ApiŠtítek, AktivitaStav, ApiAktivita } from "../../../api/program";
import { Pohlavi } from "../../../api/přihlášenýUživatel";
import { GAMECON_KONSTANTY } from "../../../env";
import { datumPřidejDen, volnoTypZObsazenost } from "../../../utils";
// Pozor musí být defaultní import!
import FlexSearch from "flexsearch";

export type FiltrProgramTabulkaVýběr =
  | {
    typ: "můj";
  }
  | {
    typ: "den";
    datum: Date;
  };

export type MapováníŠtítků = {
  /** Klíč je id (ApiŠtítek.id) hodnota je kategorie štítku (ApiŠtítek.nazevKategorie) */
  idDoKategorie: {
    [štítekId: string]: string
  },
}

export const vytvořMapováníŠtítků = (štítky: ApiŠtítek[]): MapováníŠtítků => {
  const idDoKategorie = Object.fromEntries(štítky.map(x => [x.id, x.nazevKategorie]));
  return {
    idDoKategorie,
  };
};

export type FiltrAktivit = Partial<{
  ročník: number,
  výběr: FiltrProgramTabulkaVýběr,
  filtrPřihlašovatelné: boolean,
  filtrLinie: string[],
  filtrTagy: number[],
  filtrStavAktivit: AktivitaStav[],
  filtrText: string,
}>;

export const aktivitaStatusZAktivity = (
  aktivita: ApiAktivita,
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

export const denAktivity = (časAktivity: Date) => {
  return (časAktivity.getHours() +1) >= GAMECON_KONSTANTY.PROGRAM_ZACATEK
    ? časAktivity
    : datumPřidejDen(časAktivity, -1);
};

const ziskejIdZTextovéhoFiltru = (text: string): number | undefined => {
  const idFiltrText = RegExp(/id=([0-9]*)/).exec(text)?.[1];
  if (!idFiltrText) return;
  return +idFiltrText;
};

// TODO: přidat zbytek filtrů
export const filtrujAktivity = (aktivity: ApiAktivita[], filtr: FiltrAktivit, mapováníŠtítků: MapováníŠtítků) => {
  const {
    filtrLinie, filtrPřihlašovatelné, filtrTagy: filtrŠtítkyId, ročník, výběr, filtrStavAktivit, filtrText
  } = filtr;

  const textovéFiltry: string[] = [];
  const idFiltry: number[] = [];
  for (const textovýFiltr of (filtrText?.split("|") ?? [])) {
    const idFiltr = ziskejIdZTextovéhoFiltru(textovýFiltr);
    if (idFiltr === undefined) {
      textovéFiltry.push(textovýFiltr);
    } else {
      idFiltry.push(idFiltr);
    }
  }

  const aktivityPodleId = idFiltry
    .map(idAktivity=>aktivity.find(x=>x.id === idAktivity))
    .filter(x => x !== undefined)
    ?? []
    ;

  let aktivityFiltrované = aktivity;

  if (ročník)
    aktivityFiltrované = aktivityFiltrované.filter(
      (aktivita) => new Date(aktivita.cas.od).getFullYear() === ročník
    );

  if (výběr?.typ === "můj") {
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) => aktivita?.stavPrihlaseni != undefined || aktivita?.vedu);
  } else if (výběr?.typ === "den") {
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        denAktivity(new Date(aktivita.cas.od)).getDay() === výběr.datum.getDay());
  }

  if (textovéFiltry?.some(x=>x==="*"))
    return aktivityFiltrované;

  if (filtrLinie)
    aktivityFiltrované = aktivityFiltrované.filter((aktivita) =>
      filtrLinie.some((x) => x === aktivita.linie)
    );

  if (filtrŠtítkyId) {
    const štítkyIdPodleKategorie: { [kategorie: string]: number[] } = {};
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
            aktivita.stitkyId || []
              .some(štítekId => štítekId === štítekIdZKategorie))
        )
      );
  }

  // TODO: přihlašovatelnost aktivity dle pohlaví
  // TODO: přihlašovatelnost aktivity dle pohlaví přidat tooltip na tlačítko
  if (filtrStavAktivit)
    aktivityFiltrované = aktivityFiltrované.filter((aktivita) =>
      filtrStavAktivit.some((x) => aktivitaStatusZAktivity(aktivita) === x)
    );

  if (filtrPřihlašovatelné)
    aktivityFiltrované = aktivityFiltrované.filter(
      (aktivita) => aktivita.prihlasovatelna && !aktivita.probehnuta
    );

  // TODO: filtrovat podle všech podmínek oddělených | ne jen podle první
  const prvníTextovýFiltr = textovéFiltry?.[0];
  if (prvníTextovýFiltr) {
    const flexDocument = new FlexSearch.Document<ApiAktivita, true>({
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

    const výsledek = flexDocument.search(prvníTextovýFiltr, {
      limit: 1000,
    });

    let idčka = výsledek.flatMap(x => x.result) as number[];
    idčka = Array.from(new Set(idčka));

    const filtr = idčka.map(id =>
      // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
      (flexDocument as any).get(id) as ApiAktivita
    );

    aktivityFiltrované = filtr;
  }

  const chybějícíAktivityPodleId = aktivityPodleId.filter(aktivitaPodleId => !aktivityFiltrované.some(aktivita=>aktivita.id === aktivitaPodleId.id));
  aktivityFiltrované = aktivityFiltrované.concat(chybějícíAktivityPodleId);

  return aktivityFiltrované;
};
