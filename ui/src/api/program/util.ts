import { useRouter } from "preact-router";
import { GAMECON_KONSTANTY } from "../../env";

const { BASE_PATH_PAGE } = GAMECON_KONSTANTY;

export const usePath = () => {
  // komponenta musí být v kontextu Router aby fungovalo
  const [route, setRoute] = useRouter();

  const url = route.url;

  // přidám k url poslední / pokud by tam nebylo
  if ((url + "/").substring(0, BASE_PATH_PAGE.length) !== BASE_PATH_PAGE)
    throw new Error(`invalid base path BASE_PATH_PAGE= ${BASE_PATH_PAGE} current path= ${url}`);

  let resUlr = url.substring(BASE_PATH_PAGE.length);

  return [resUlr, (path: string, replace = false) => {
    const url = BASE_PATH_PAGE + path.substring(1);
    setRoute(url, replace);
  }] as const;
}

