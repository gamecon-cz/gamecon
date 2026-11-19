import { ApiTag, AktivitaStav, jeNeúčast } from "../../../api/program";
import { Pohlavi } from "../../../api/přihlášenýUživatel";
import { GAMECON_KONSTANTY } from "../../../env";
import { jePlnoZObsazenost, volnoTypZObsazenost } from "../../../utils";
import { pražskéHodiny, pražskýDenVTýdnu, pražskýRok } from "../../../utils/czech-time";
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
  }
  | {
    typ: "všechny_dny";
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
  /** zbývá místo, i mimo registraci a v minulých ročnících (co se nezaplnilo), viz máVolnéMísto() */
  filtrVolné: boolean,
  filtrLinie: string[],
  filtrTagy: number[],
  filtrStavAktivit: AktivitaStav[],
  filtrText: string,
  filtrInterni: boolean,
}>;

export const aktivitaStatusZAktivity = (
  aktivita: Aktivita,
  pohlavi?: Pohlavi | undefined
): AktivitaStav => {
  if (jeNeúčast(aktivita.stavPrihlaseni)) {
    return "neucast";
  }
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
    return "sledujici";
  }
  if (aktivita.vdalsiVlne) {
    return "vDalsiVlne";
  }
  if (aktivita.vBudoucnu) {
    return "vBudoucnu";
  }

  if (aktivita.obsazenost && jePlnoZObsazenost(aktivita.obsazenost, pohlavi)) {
    return "plno";
  }
  return "volno";
};

/**
 * Na aktivitě zbývá místo. Kam se dá přihlásit, tam se přihlášenému počítají jen místa pro jeho pohlaví, jinde (mimo
 * registraci, proběhlá, minulý ročník) jakékoli volné místo bez ohledu na to, kdo se dívá, ať jde zjistit, co se
 * nezaplnilo. Stejně jako „Jen volná místa“ na stránce linie (web/moduly/aktivity.php).
 */
export const máVolnéMísto = (aktivita: Aktivita, pohlavi: Pohlavi | undefined): boolean => {
  if (!aktivita.obsazenost) return true;
  const místaProPohlaví = pohlavi && aktivita.prihlasovatelna && !aktivita.probehnuta;
  return místaProPohlaví
    ? !jePlnoZObsazenost(aktivita.obsazenost, pohlavi)
    : volnoTypZObsazenost(aktivita.obsazenost) !== "x";
};

export const denAktivity = (časAktivity: Date | number | Aktivita): Date => {
  let časAktivityDate: Date;
  if (časAktivity instanceof Date) {
    časAktivityDate = časAktivity;
  } else if (typeof časAktivity === "number") {
    časAktivityDate = new Date(časAktivity);
  } else {
    časAktivityDate = new Date(časAktivity.cas.od);
  }

  return (pražskéHodiny(časAktivityDate) + 1) >= GAMECON_KONSTANTY.PROGRAM_ZACATEK
    ? časAktivityDate
    : new Date(časAktivityDate.getTime() - 24 * 60 * 60 * 1_000);
};

export const denČasAktivityText = (aktivita: Aktivita): string => {
  const den = new Intl.DateTimeFormat('cs-CZ', { weekday: 'short' }).format(denAktivity(aktivita.cas.od));
  const časOd = new Intl.DateTimeFormat('cs-CZ', { hour: '2-digit', minute: '2-digit' }).format(aktivita.cas.od);
  const časDo = new Intl.DateTimeFormat('cs-CZ', { hour: '2-digit', minute: '2-digit' }).format(aktivita.cas.do);

  return `${den} ${časOd}-${časDo}`;
}

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

const zaindexovanéIdAktivit = new Set<number>();
const zaindexujFullText = (aktivita: Aktivita) => {
  if (zaindexovanéIdAktivit.has(aktivita.id)) return;
  flexDocument.add(aktivita);
  zaindexovanéIdAktivit.add(aktivita.id);
};


/**
 * @param pohlavi přihlášeného: legenda (Otevřené / Plno) mu počítá jen místa pro jeho pohlaví jako obarvení buňky,
 *   Volná místa jen tam, kam se dá přihlásit
 */
export const filtrujAktivity = (
  aktivity: Aktivita[], filtr: FiltrAktivit, mapováníTagů: MapováníTagů, pohlavi?: Pohlavi,
) => {
  const {
    filtrLinie, filtrPřihlašovatelné, filtrVolné, filtrTagy: filtrTagyId, ročník, výběr, filtrStavAktivit, filtrText,
    filtrInterni,
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
      (aktivita) => pražskýRok(new Date(aktivita.cas.od)) === ročník
    );

  if (výběr?.typ === "můj") {
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) => aktivita?.stavPrihlaseni !== null || aktivita?.vedu);
  } else if (výběr?.typ === "den") {
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        pražskýDenVTýdnu(denAktivity(new Date(aktivita.cas.od))) === pražskýDenVTýdnu(výběr.datum));
  }
  // Pro "všechny_dny" se nefiltruje dle dne

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

  // TODO: přihlašovatelnost aktivity dle pohlaví přidat tooltip na tlačítko
  if (filtrStavAktivit)
    aktivityFiltrované = aktivityFiltrované.filter((aktivita) =>
      filtrStavAktivit.some((x) => aktivitaStatusZAktivity(aktivita, pohlavi) === x)
    );

  if (filtrPřihlašovatelné)
    aktivityFiltrované = aktivityFiltrované.filter(
      (aktivita) => aktivita.prihlasovatelna && !aktivita.probehnuta
    );

  // na přihlašovatelnosti nezáleží, ať jde i po GC zjistit, co se nezaplnilo; s „Přihlašovatelné“ jen to, kam se dá jít
  if (filtrVolné)
    aktivityFiltrované = aktivityFiltrované.filter((aktivita) => máVolnéMísto(aktivita, pohlavi));

  if (!filtrInterni)
    aktivityFiltrované = aktivityFiltrované.filter(
      // Bez zapnutého filtru „Interní" schováme interní (nepublikované technické/
      // brigádnické) aktivity, ke kterým účastník nemá vztah – ty jsou jen
      // clutter. Aktivitu, na kterou už přihlášený je, nebo kterou vede (vedu),
      // ponecháme (jinak by po posazení účastníka zmizela z programu i z „můj
      // program", a stejně tak by z „můj program" zmizela org jeho neveřejná
      // technická/brigádnická aktivita – „můj program" ji přitom vč. vedu bere).
      (aktivita) => !aktivita.interni || !!aktivita.stavPrihlaseni || !!aktivita.vedu
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
