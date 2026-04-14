import { ApiTag, AktivitaStav } from "../../../api/program";
import { Pohlavi } from "../../../api/přihlášenýUživatel";
import { GAMECON_KONSTANTY } from "../../../env";
import { datumPřidejDen, volnoTypZObsazenost } from "../../../utils";
// Pozor musí být defaultní import!
import FlexSearch from "flexsearch";
import { Aktivita } from "../slices/programDataSlice";

export type FiltrProgramTabulkaVýběr =
  | {
    typ: "můj";
  }
  | {
    typ: "den";
    datum: Date;
  };

export type MapováníTagů = {
  /** Klíč je id (ApiTag.id) hodnota je kategorie tagu (ApiTag.nazevKategorie) */
  idDoKategorie: {
    [tagId: string]: string
  },
}

export const vytvořMapováníTagů = (tagy: ApiTag[]): MapováníTagů => {
  const idDoKategorie = Object.fromEntries(tagy.map(x => [x.id, x.nazevKategorie]));
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
  aktivita: Aktivita,
  pohlavi?: Pohlavi | undefined
): AktivitaStav => {
  if (
    aktivita.stavPrihlaseni !== null &&
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

const zaindexovanéIdAktivit = new Set<number>()
const zaindexujFullText = (aktivita: Aktivita) => {
  if (zaindexovanéIdAktivit.has(aktivita.id)) return;
  flexDocument.add(aktivita)
  zaindexovanéIdAktivit.add(aktivita.id)
}


export const filtrujAktivity = (aktivity: Aktivita[], filtr: FiltrAktivit, mapováníTagů: MapováníTagů) => {
  const {
    filtrLinie, filtrPřihlašovatelné, filtrTagy: filtrTagyId, ročník, výběr, filtrStavAktivit, filtrText
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
      .filter((aktivita) => aktivita?.stavPrihlaseni !== null || aktivita?.vedu);
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

  if (filtrTagyId) {
    const tagyIdPodleKategorie: { [kategorie: string]: number[] } = {};
    for (const tagId of filtrTagyId) {
      const kategorieTagu = mapováníTagů.idDoKategorie[tagId] ?? "";
      if (!kategorieTagu) {
        console.error(`nenalezena kategorie pro tag id: ${tagId}`);
      }
      const kategorie = tagyIdPodleKategorie[kategorieTagu] = tagyIdPodleKategorie[kategorieTagu] ?? [];
      kategorie.push(tagId);
    }

    const tagyIdPodleKategorieValues = Object.values(tagyIdPodleKategorie);
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        // aktivita splňuje podmínku alespoň jednoho tagu z každé kategorie
        tagyIdPodleKategorieValues.every(tagyIdZKategorie =>
          tagyIdZKategorie.some(tagIdZKategorie =>
            (aktivita.stitkyId ?? [])
              .some(tagId => tagId === tagIdZKategorie))
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
    for (const aktivita of aktivityFiltrované) {
      zaindexujFullText(aktivita);
    }

    const výsledek = flexDocument.search(prvníTextovýFiltr, {
      limit: 1000,
    });


    const idčka = new Set(výsledek.flatMap(x => x.result) as number[]);

    aktivityFiltrované = aktivityFiltrované.filter(aktivita=>idčka.has(aktivita.id));
  }

  const chybějícíAktivityPodleId = aktivityPodleId.filter(aktivitaPodleId => !aktivityFiltrované.some(aktivita=>aktivita.id === aktivitaPodleId.id));
  aktivityFiltrované = aktivityFiltrované.concat(chybějícíAktivityPodleId);

  return aktivityFiltrované;
};
