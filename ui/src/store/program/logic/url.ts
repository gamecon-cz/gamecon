import { AktivitaStav } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import { formátujDenVTýdnu, pražskýRok, pražskýMěsíc, pražskýDenVMěsíci, pražskýDenVTýdnu } from "../../../utils";
import { denAktivity } from "./aktivity";

export type ProgramTabulkaVýběr =
  | {
    typ: "můj";
  }
  | {
    typ: "den";
    datum: Date;
  }
  | {
    typ: "všechny_dny";
  }
  ;

export type ProgramURLStav = {
  ročník: number,
  výběr: ProgramTabulkaVýběr,
  filtrPřihlašovatelné?: boolean,
  aktivitaNáhledId?: number,
  filtrLinie?: string[],
  filtrTagy?: number[],
  filtrStavAktivit?: AktivitaStav[],
  filtrText?: string,
  /** ADMIN */
  podleMístnosti?: boolean,
  bezÚčastníka?: boolean,
  zobrazInterni?: boolean,
  zobrazPrázdné?: boolean,
  autoRefresh?: boolean,
}

/** Klíč pro porovnání dat podle pražského kalendářního dne. */
const pražskýDenKlíč = (datum: Date): string =>
  `${pražskýRok(datum)}-${pražskýMěsíc(datum)}-${pražskýDenVMěsíci(datum)}`;

/**
 * Programový den (datum z PROGRAM_DNY), do kterého spadá daný okamžik, nebo
 * undefined, pokud je okamžik mimo dny programu. Hranici dne (kdy ranní
 * okamžik patří ještě do předchozího programového dne) určuje denAktivity –
 * sdílíme ji, aby se výchozí den nerozcházel se skutečným seskupením/filtrováním.
 */
const programovýDenProČas = (čas: number): Date | undefined => {
  const klíč = pražskýDenKlíč(denAktivity(new Date(čas)));
  const den = GAMECON_KONSTANTY.PROGRAM_DNY.find(
    (denMs) => pražskýDenKlíč(new Date(denMs)) === klíč,
  );
  return den === undefined ? undefined : new Date(den);
};

/**
 * Výchozí den programu (dle serverového "teď", takže respektuje ročník
 * zobrazený v daném prostředí – ostrá/beta/preview/lokál/archiv):
 *  1. probíhá-li právě některý den GameConu, vybere ten (přesně podle data);
 *  2. jinak vybere den programu se stejným dnem v týdnu jako dnešek – takže
 *     když je dnes čtvrtek, naskočí rovnou program na čtvrtek i mimo akci;
 *  3. když dnešní den v týdnu žádnému dni programu neodpovídá (po/út), padá
 *     na první den programu.
 */
const výchozíDenProgramu = (): Date => {
  const běžícíDen = programovýDenProČas(GAMECON_KONSTANTY.TED);
  if (běžícíDen) return běžícíDen;

  // Mimo akci bereme den v týdnu doslova z dnešního data – tady nedává smysl
  // posouvat ranní okamžiky do předchozího dne (to dělá denAktivity jen kvůli
  // seskupování nočních aktivit běžícího ročníku, viz bod 1 výše).
  const dnešníDenVTýdnu = pražskýDenVTýdnu(new Date(GAMECON_KONSTANTY.TED));
  const denDleDneVTýdnu = GAMECON_KONSTANTY.PROGRAM_DNY.find(
    (denMs) => pražskýDenVTýdnu(new Date(denMs)) === dnešníDenVTýdnu,
  );
  return denDleDneVTýdnu !== undefined
    ? new Date(denDleDneVTýdnu)
    : new Date(GAMECON_KONSTANTY.PROGRAM_OD);
};

export const URL_STATE_VÝCHOZÍ_MOŽNOST = Object.freeze({
  typ: "den",
  datum: výchozíDenProgramu(),
});

export const URL_STATE_VÝCHOZÍ_STAV: ProgramURLStav = Object.freeze({
  ročník: GAMECON_KONSTANTY.ROCNIK,
  výběr: URL_STATE_VÝCHOZÍ_MOŽNOST,
});

const NÁHLED_QUERY_KEY = "idAktivityNahled";
const LINIE_QUERY_KEY = "linie";
const TAGY_QUERY_KEY = "tagy";
const PŘIHLAŠOVATELNÉ_QUERY_KEY = "pouzePrihlasovatelne";
const ROCNIK_QUERY_KEY = "rocnik";
const STAVY_QUERY_KEY = "stav";
const TEXT_QUERY_KEY = "text";
const PODLE_MÍSTNOSTI_QUERY_KEY = "podleMistnosti";
const BEZ_ÚČASTNÍKA_QUERY_KEY = "bezUcastnika";
const ZOBRAZ_INTERNÍ_QUERY_KEY = "interni";
const ZOBRAZ_PRÁZDNÉ_QUERY_KEY = "zobrazPrazdne";
const AUTO_REFRESH_QUERY_KEY = "autoRefresh";

const párováníQueryDoStavu: {
  query: string,
  stavString: keyof ProgramURLStav,
}[] = [
  { stavString: "filtrPřihlašovatelné", query: PŘIHLAŠOVATELNÉ_QUERY_KEY },
  { stavString: "aktivitaNáhledId", query: NÁHLED_QUERY_KEY },
  { stavString: "filtrLinie", query: LINIE_QUERY_KEY },
  { stavString: "filtrTagy", query: TAGY_QUERY_KEY },
  { stavString: "filtrStavAktivit", query: STAVY_QUERY_KEY },
  { stavString: "filtrText", query: TEXT_QUERY_KEY },
  { stavString: "podleMístnosti", query: PODLE_MÍSTNOSTI_QUERY_KEY },
  { stavString: "bezÚčastníka", query: BEZ_ÚČASTNÍKA_QUERY_KEY },
  { stavString: "zobrazInterni", query: ZOBRAZ_INTERNÍ_QUERY_KEY },
  { stavString: "zobrazPrázdné", query: ZOBRAZ_PRÁZDNÉ_QUERY_KEY },
  { stavString: "autoRefresh", query: AUTO_REFRESH_QUERY_KEY },
];

const parsujUrlDoStavu = (
  urlObj: URL,
  urlStav: ProgramURLStav,
  klíčVUrlStav: keyof ProgramURLStav,
  klíčVQuery: string
) => {
  const hodnotaString = urlObj.searchParams.get(klíčVQuery);
  try {
    if (hodnotaString) {
      const hodnota = JSON.parse(decodeURIComponent(hodnotaString));
      (urlStav[klíčVUrlStav] as any) = hodnota;
    }
  } catch (e) { console.error(`nepodařilo se rozparsovat hodnotu ${hodnotaString ?? ""}`); }
};

const vytvořQueryHodnotuZeStavu = (
  search: string[],
  urlStav: ProgramURLStav,
  klíčVUrlStav: keyof ProgramURLStav,
  klíčVQuery: string
) => {
  const hodnota = urlStav[klíčVUrlStav];
  const výchozíStránky = GAMECON_KONSTANTY.PROGRAM_VYCHOZI_NASTAVENI?.[klíčVQuery];

  // Klíč s výchozí hodnotou stránky (např. podleMistnosti=true na stránce „po
  // místnostech“): serializujeme efektivní bool a zapíšeme ho jen když se od
  // defaultu liší – jinak by přepínač nešlo trvale vypnout (přežít refresh /
  // nasdílet URL), protože při chybějícím parametru se default aplikuje znovu.
  if (výchozíStránky !== undefined) {
    const efektivní = !!hodnota;
    if (efektivní !== výchozíStránky)
      search.push(`${klíčVQuery}=${encodeURIComponent(JSON.stringify(efektivní))}`);
    return;
  }

  if (hodnota)
    search.push(`${klíčVQuery}=${encodeURIComponent(JSON.stringify(hodnota))}`);
};

export const parsujUrl = (url: string) => {
  const basePath = new URL(GAMECON_KONSTANTY.BASE_PATH_PAGE).pathname;
  const urlObj = new URL(url, GAMECON_KONSTANTY.BASE_PATH_PAGE);

  // Pokud je v URL prázdný slug, použij výchozí slug stránky (např. stránka
  // „Program po místnostech“ posílá "vsechny-dny"), jinak padáme na obvyklý
  // výchozí den.
  const den = urlObj.pathname.slice(basePath.length)
    || GAMECON_KONSTANTY.PROGRAM_VYCHOZI_VYBER
    || "";

  // TODO: co tady dělá přihlášen: true ?? nemá být náhodou z předaných konstant ?
  const výběr = urlStavProgramTabulkaMožnostíDnyMůj({ přihlášen: true, jeAdmin: GAMECON_KONSTANTY.JE_ADMIN })
    .find(x => urlZTabulkaVýběr(x) === den)
    ?? URL_STATE_VÝCHOZÍ_MOŽNOST;

  // výchozí hodnoty
  const urlStav: ProgramURLStav = {
    výběr,
    ročník: GAMECON_KONSTANTY.ROCNIK,
  };

  for (const { query, stavString } of párováníQueryDoStavu.concat(
    [
      { stavString: "ročník", query: ROCNIK_QUERY_KEY }
    ]
  )) {
    parsujUrlDoStavu(urlObj, urlStav, stavString, query);
    // Když query param v URL chybí, použij výchozí nastavení stránky (např.
    // dedikovaná stránka „Program po místnostech“ posílá podleMistnosti=true),
    // aby stránka nabíhala se správným seskupením i bez query parametru.
    if (urlStav[stavString] === undefined) {
      const výchozíStránky = GAMECON_KONSTANTY.PROGRAM_VYCHOZI_NASTAVENI?.[query];
      if (výchozíStránky !== undefined) {
        (urlStav[stavString] as unknown) = výchozíStránky;
      }
    }
  }

  return urlStav;
};

// TODO: z nějakého důvodu se na každé kliknutí volá moc často
/** vytvoří url z aktuálního url-stavu nebo z předaného stavu */
export const generujUrl = (urlStav: ProgramURLStav): string | undefined => {
  const výběr =
    urlStavProgramTabulkaMožnostíDnyMůj({ přihlášen: true, jeAdmin: GAMECON_KONSTANTY.JE_ADMIN }).find(x => porovnejTabulkaVýběr(x, urlStav.výběr));

  if (!výběr) return undefined;

  let url = GAMECON_KONSTANTY.BASE_PATH_PAGE + urlZTabulkaVýběr(výběr);

  const search: string[] = [];

  for (const { query, stavString } of párováníQueryDoStavu.concat(
    // pokud je ročník aktuální
    urlStav.ročník !== GAMECON_KONSTANTY.ROCNIK ? [
      { stavString: "ročník", query: ROCNIK_QUERY_KEY }
    ] : []
  )) {
    vytvořQueryHodnotuZeStavu(search, urlStav, stavString, query);
  }

  if (search.length)
    url += "?" + search.join("&");

  return url;
};

export const urlStavProgramTabulkaMožnostíDnyMůj = (props?: { přihlášen?: boolean, ročník?: number, jeAdmin?: boolean }): ProgramTabulkaVýběr[] =>
  ((props?.jeAdmin ?? false) ? [{ typ: "všechny_dny" } as ProgramTabulkaVýběr] : [])
    .concat(
      GAMECON_KONSTANTY.PROGRAM_DNY
        .map((den) => ({
          typ: "den",
          datum: new Date(den),
        } as ProgramTabulkaVýběr))
    )
    .concat((props?.přihlášen ?? false) ? [{ typ: "můj" } as ProgramTabulkaVýběr] : []);

const urlZTabulkaVýběr = (výběr: ProgramTabulkaVýběr) =>
  výběr.typ === "můj"
    ? "muj"
    : výběr.typ === "všechny_dny"
    ? "vsechny-dny"
    : formátujDenVTýdnu(výběr.datum)
  ;

export const porovnejTabulkaVýběr = (v1: ProgramTabulkaVýběr, v2: ProgramTabulkaVýběr) =>
  urlZTabulkaVýběr(v1) === urlZTabulkaVýběr(v2);

/**
 * Název stránky pro daný výběr – zrcadlí serverové nazevStranky()
 * z web/moduly/program.php, aby se titulek v záložce po přepnutí dne
 * shodoval s tím, co by vyrenderovalo PHP po F5.
 */
export const nadpisProgramu = (výběr: ProgramTabulkaVýběr): string =>
  výběr.typ === "můj"
    ? "Můj program"
    : výběr.typ === "všechny_dny"
    ? "Program – všechny dny"
    : `Program ${formátujDenVTýdnu(výběr.datum, true)}`;
