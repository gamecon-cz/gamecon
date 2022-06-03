import { useEffect } from "preact/hooks";
import { usePath } from "../../api/uti";
import { GAMECON_KONSTANTY } from "../../env";
import { formátujDenVTýdnu } from "../../utils";

export type ProgramURLState = {
  výběr: ProgramTabulkaVýběr,
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
  výběr.typ === "můj"
    ? "muj_program"
    : formátujDenVTýdnu(výběr.datum);
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
  const výběrUrl =
    tabulkaMožnosti().find(x => porovnejTabulkaVýběr(x ,urlState.výběr))

  if (!výběrUrl) return undefined;

  return urlZTabulkaVýběr(výběrUrl);
}


const parseUrlState = (url: string): ProgramURLState | undefined => {
  const výběr: ProgramTabulkaVýběr | undefined =
    tabulkaMožnosti().find(x => urlZTabulkaVýběr(x) === url);

  if (!výběr) return undefined;

  return { výběr } as ProgramURLState;
}

// TODO: použít kontext ?
export const useProgramSemanticRoute = () => {
  const [path, setPath] = usePath();

  const parsedUrlState = parseUrlState(path);
  const urlState = parsedUrlState ?? DEFAULT_URLSTATE;

  const setUrlState = (urlState: ProgramURLState) => {
    const url = generateUrl(urlState);
    if (url)
      setPath(url);
    else
      console.error("invalid url state");
  };

  useEffect(() => {
    if (!parsedUrlState)
      setPath(generateUrl(DEFAULT_URLSTATE)!);
  }, [path, parsedUrlState])

  return { urlState, setUrlState, možnosti: tabulkaMožnosti() };
}

