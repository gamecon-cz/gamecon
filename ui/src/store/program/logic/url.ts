import { AktivitaStav } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import { formátujDenVTýdnu } from "../../../utils";

export type ProgramTabulkaVýběr =
  | {
    typ: "můj";
  }
  | {
    typ: "den";
    datum: Date;
  }
  ;

export type ProgramURLStav = {
  ročník: number,
  výběr: ProgramTabulkaVýběr,
  filtrPřihlašovatelné: boolean,
  aktivitaNáhledId?: number,
  filtrLinie?: string[],
  filtrTagy?: number[],
  filtrStavAktivit?: AktivitaStav[],
  filtrText?: string,
}

export const URL_STATE_VÝCHOZÍ_MOŽNOST = Object.freeze({
  typ: "den",
  datum: new Date(GAMECON_KONSTANTY.PROGRAM_OD),
});

export const URL_STATE_VÝCHOZÍ_STAV: ProgramURLStav = Object.freeze({
  ročník: GAMECON_KONSTANTY.ROCNIK,
  výběr: URL_STATE_VÝCHOZÍ_MOŽNOST,
  filtrPřihlašovatelné: false,
});

const NÁHLED_QUERY_KEY = "idAktivityNahled";
const LINIE_QUERY_KEY = "linie";
const TAGY_QUERY_KEY = "tagy";
const PŘIHLAŠOVATELNÉ_QUERY_KEY = "pouzePrihlasovatelne";
const ROCNIK_QUERY_KEY = "rocnik";
const STAVY_QUERY_KEY = "stav";
const TEXT_QUERY_KEY = "text";

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
  if (urlStav[klíčVUrlStav])
    search.push(`${klíčVQuery}=${encodeURIComponent(JSON.stringify(urlStav[klíčVUrlStav]))}`);
};

export const parsujUrl = (url: string) => {
  const basePath = new URL(GAMECON_KONSTANTY.BASE_PATH_PAGE).pathname;
  const urlObj = new URL(url, GAMECON_KONSTANTY.BASE_PATH_PAGE);

  const den = urlObj.pathname.slice(basePath.length);

  // TODO: co tady dělá přihlášen: true ?? nemá být náhodou z předaných konstant ?
  const výběr = urlStavProgramTabulkaMožnostíDnyMůj({ přihlášen: true })
    .find(x => urlZTabulkaVýběr(x) === den)
    ?? URL_STATE_VÝCHOZÍ_MOŽNOST;

  // výchozí hodnoty
  const urlStav: ProgramURLStav = {
    výběr,
    ročník: GAMECON_KONSTANTY.ROCNIK,
    filtrPřihlašovatelné: false,
  };

  for (const { query, stavString } of párováníQueryDoStavu.concat(
    [
      { stavString: "ročník", query: ROCNIK_QUERY_KEY }
    ]
  )) {
    parsujUrlDoStavu(urlObj, urlStav, stavString, query);
  }

  return urlStav;
};

// TODO: z nějakého důvodu se na každé kliknutí volá moc často
/** vytvoří url z aktuálního url-stavu nebo z předaného stavu */
export const generujUrl = (urlStav: ProgramURLStav): string | undefined => {
  const výběr =
    urlStavProgramTabulkaMožnostíDnyMůj({ přihlášen: true }).find(x => porovnejTabulkaVýběr(x, urlStav.výběr));

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

export const urlStavProgramTabulkaMožnostíDnyMůj = (props?: { přihlášen?: boolean, ročník?: number }): ProgramTabulkaVýběr[] =>
  GAMECON_KONSTANTY.PROGRAM_DNY
    .map((den) => ({
      typ: "den",
      datum: new Date(den),
    } as ProgramTabulkaVýběr))
    .concat(...((props?.přihlášen ?? false) ? [{ typ: "můj" } as ProgramTabulkaVýběr] : []));

const urlZTabulkaVýběr = (výběr: ProgramTabulkaVýběr) =>
  výběr.typ === "můj"
    ? "muj"
    : formátujDenVTýdnu(výběr.datum)
  ;

export const porovnejTabulkaVýběr = (v1: ProgramTabulkaVýběr, v2: ProgramTabulkaVýběr) =>
  urlZTabulkaVýběr(v1) === urlZTabulkaVýběr(v2);
