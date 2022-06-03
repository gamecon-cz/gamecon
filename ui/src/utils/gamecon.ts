import { useRouter } from "preact-router";
import { GAMECON_KONSTANTY } from "../env";

/** Dny v týdnu bez diakritiky. Začíná pondeli. Pro háčky použít funkci doplňHáčkyDoDne */
export const DNY = [
  'pondeli',
  'utery',
  'streda',
  'ctvrtek',
  'patek',
  'sobota',
  'nedele',
]

export const doplňHáčkyDoDne = (den: string) => {
  if (den === 'pondeli') return 'pondělí';
  if (den === 'utery') return 'úterý';
  if (den === 'streda') return 'středa';
  if (den === 'ctvrtek') return 'čtvrtek';
  if (den === 'patek') return 'pátek';
  if (den === 'sobota') return 'sobota';
  if (den === 'nedele') return 'neděle';
  console.warn(`nepodařilo se oháčkovat den ${den}`);
  return den;
}

const {BASE_PATH_PAGE} = GAMECON_KONSTANTY;

export const usePath = () => {
  // komponenta musí být v kontextu Router aby fungovalo
  const [route, setRoute] = useRouter();

  const url = route.url;

  // přidám k url poslední / pokud by tam nebylo
  if ((url+"/").substring(0, BASE_PATH_PAGE.length) !== BASE_PATH_PAGE)
    throw new Error(`invalid base path BASE_PATH_PAGE= ${BASE_PATH_PAGE} current path= ${url}`);

  let resUlr = url.substring(BASE_PATH_PAGE.length);

  return [resUlr, (path: string, replace = false) => {
    const url = BASE_PATH_PAGE + path;
    setRoute(url, replace);
  }] as const;
}

