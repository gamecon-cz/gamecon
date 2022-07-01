import { createContext } from "preact";
import { useEffect } from "preact/hooks";
import { usePath as useUrl } from "../../api/program/util";
import { GAMECON_KONSTANTY } from "../../env";
import { formátujDenVTýdnu, tryParseNumber } from "../../utils";

const NÁHLED_QUERY_STRING = "idAktivityNahled";

export type ProgramURLState = {
  výběr: ProgramTabulkaVýběr,
  aktivitaNáhledId?: number,
};

export type ProgramTabulkaVýběr =
  | {
    typ: "můj";
  }
  | {
    typ: "den";
    datum: Date;
  };

const DEFAULT_URLSTATE: ProgramURLState = {
  výběr: {
    typ: "den",
    datum: new Date(GAMECON_KONSTANTY.PROGRAM_OD),
  },
}

const urlZTabulkaVýběr = (výběr: ProgramTabulkaVýběr) =>
  "/" + (výběr.typ === "můj"
    ? "muj_program"
    : formátujDenVTýdnu(výběr.datum));
;

export const porovnejTabulkaVýběr = (v1: ProgramTabulkaVýběr, v2: ProgramTabulkaVýběr) =>
  urlZTabulkaVýběr(v1) === urlZTabulkaVýběr(v2);


// TODO: bude se dotahovat jestli přihlášen
const tabulkaMožnosti = (props?: { přihlášen?: boolean }): ProgramTabulkaVýběr[] =>
  GAMECON_KONSTANTY.PROGRAM_DNY
    .map((den) => ({
      typ: "den",
      datum: new Date(den),
    } as ProgramTabulkaVýběr))
    .concat(...((props?.přihlášen ?? false) ? [{ typ: "můj" } as ProgramTabulkaVýběr] : []));
;


const generateUrl = (urlState: ProgramURLState): string | undefined => {
  const výběr =
    tabulkaMožnosti().find(x => porovnejTabulkaVýběr(x, urlState.výběr))

  if (!výběr) return undefined;

  let url = urlZTabulkaVýběr(výběr);

  const search: string[] = [];

  if (urlState.aktivitaNáhledId)
    search.push(`${NÁHLED_QUERY_STRING}=${urlState.aktivitaNáhledId}`);

  if (search.length)
    url += "?" + search.join("&");

  return url;
}

// Tady je adresa irelevantní nebudeme s ní pracovat
const getURLObject = (url: string) => new URL(url, "http://gamecon.cz");

const parseUrlState = (url: string): ProgramURLState | undefined => {
  const urlObj = getURLObject(url);

  const výběr: ProgramTabulkaVýběr | undefined =
    tabulkaMožnosti().find(x => urlZTabulkaVýběr(x) === urlObj.pathname);

  if (!výběr) return undefined;

  const resObj: ProgramURLState = { výběr };

  const nahledIdStr = tryParseNumber(urlObj.searchParams.get(NÁHLED_QUERY_STRING));

  if (nahledIdStr !== undefined)
    resObj.aktivitaNáhledId = nahledIdStr;

  return resObj;
}

// TODO: použít kontext ?
export const useProgramSemanticRoute = () => {
  const [url, setUrl] = useUrl();

  const parsedUrlState = parseUrlState(url);
  const urlState = parsedUrlState ?? DEFAULT_URLSTATE;

  const setUrlState = (urlState: ProgramURLState) => {
    const url = generateUrl(urlState);
    if (url)
      setUrl(url);
    else
      console.error("invalid url state");
  };

  useEffect(() => {
    if (!parsedUrlState)
      setUrl(generateUrl(DEFAULT_URLSTATE)!);
  }, [url, parsedUrlState])

  return { urlState, setUrlState, možnosti: tabulkaMožnosti() };
}

type ProgramMutableURLState = {
  urlState: ProgramURLState;
  setUrlState: (urlState: ProgramURLState) => void;
  možnosti: ProgramTabulkaVýběr[];
}

export const ProgramURLState = createContext<ProgramMutableURLState>({
  urlState: DEFAULT_URLSTATE,
  setUrlState: () => undefined,
  možnosti: [],
});

