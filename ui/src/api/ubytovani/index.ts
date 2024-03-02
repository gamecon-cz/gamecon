import { GAMECON_KONSTANTY } from "../../env";

// TODO: nějak parsovat ?
export type APIPozitce =
  | "Účastník"
  | "Vypravěč"
  | "Organizátor"
  | "Brigádník"
  | "Dobrovolník senior"
  | "Partner"
  | "Herman"
  | "Vypravěč, Herman"
  | "Zázemí"
  | "Vypravěč, Dobrovolník senior"
  | "Zázemí, Dobrovolník senior"
  | "Vypravěč, Infopult"
  ;

export type APIUbytovanýUživatel =
  {
    id_uzivatele: "10174",
    login_uzivatele: "Login10174",
    jmeno_uzivatele: "",
    prijmeni_uzivatele: "",
    // typ: "Dvojlůžák ",
    // mezera_v_ubytovani: "",
    prvni_noc: "0",
    posledni_noc: "3",
    pokoj: "A335",
    // ubytovan_s: "",
    pozice: APIPozitce,
    // datum_narozeni: "1. 1. 0001",
    // mesto_uzivatele: "",
    // ulice_a_cp_uzivatele: "",
    // typ_dokladu: "",
    // cislo_dokladu: "",
    // statni_obcanstvi: null
  };


export const fetchUbytovaníUživatelé = async (): Promise<APIUbytovanýUživatel[]> => {
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}ubytovani`;
  return fetch(url, { method: "GET" }).then(async x => x.json());
};
  
