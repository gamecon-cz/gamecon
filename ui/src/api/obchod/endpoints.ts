import { GAMECON_KONSTANTY } from "../../env";
import { DefiniceObchod, DefiniceObchodMřížka, DefiniceObchodMřížkaBuňka, DefiniceObchodMřížkaBuňkaPředmět, DefiniceObchodMřížkaBuňkaStránka, DefiniceObchodMřížkaBuňkaTyp, ObjednávkaPředmět, Předmět } from "./types";

/**
 * Typ pro zápis a čtení pro api
 */
type MřížkaAPI = {
  id?: number,
  text?: string,
  bunky?: {
    id?: number,
    typ: number,
    text?: string,
    barva?: string,
    cil_id?: number,
  }[],
}[];

type PředmětyAPI = {
  nazev: string,
  zbyva: number | undefined,
  id: number,
  cena: number,
}[];

export const fetchMřížky = async (): Promise<DefiniceObchod | null> => {
  try {
    const res = await fetch(GAMECON_KONSTANTY.BASE_PATH_API + "obchod-mrizky-view");
    const mřížkyApi = await res.json() as MřížkaAPI;

    const obj: DefiniceObchod = {
      mřížky: mřížkyApi.map(mřížkaRaw => ({
        id: mřížkaRaw.id!,
        text: mřížkaRaw.text,
        buňky: mřížkaRaw.bunky?.map(
          (buňka) => ({
            typ: DefiniceObchodMřížkaBuňkaTyp[buňka.typ] as string,
            cilId: buňka.cil_id,
            text: buňka.text,
            barvaPozadí: buňka.barva,
            id: buňka.id,
          } as DefiniceObchodMřížkaBuňka)
        ) ?? [],
      }))
    }

    return obj;
  } catch (e) {
    console.error(e);
  }
  return null;
};

export const fetchNastavMřížky = async (obj: DefiniceObchod) => {
  try {
    const body = JSON.stringify(obj.mřížky.map(mřížka => ({
      id: mřížka.id,
      text: mřížka?.text,
      bunky: mřížka.buňky.map(buňka => ({
        id: buňka.id,
        barva: buňka.barvaPozadí,
        cil_id: (buňka as (
          DefiniceObchodMřížkaBuňkaPředmět | DefiniceObchodMřížkaBuňkaStránka
        ))?.cilId,
        text: buňka.text,
        typ: DefiniceObchodMřížkaBuňkaTyp[buňka.typ],
      } as (Required<MřížkaAPI[0]>["bunky"][0])))
    } as MřížkaAPI[0])) as MřížkaAPI);

    const res = await fetch(GAMECON_KONSTANTY.BASE_PATH_API + "obchod-mrizky-view", {
      method: "POST",
      body,
    });

    if (res.status >= 200 && res.status < 300)
      return true;
  } catch (e) {
    console.error(e);
  }
  return false;
};

export const fetchPředměty = async (): Promise<Předmět[] | null> => {
  try {
    const res = await fetch(GAMECON_KONSTANTY.BASE_PATH_API + "predmety");
    const předmětyApi = await res.json() as PředmětyAPI;
    const předměty = předmětyApi.map(předmět => ({
      název: předmět.nazev,
      cena: předmět.cena,
      id: předmět.id,
      zbývá: předmět.zbyva,
    } as Předmět));
    return předměty;
  } catch (e) {
    console.error(e);
  }
  return null;
}

// TODO: využívá formuláře pro poslání postu do adminu - nahradit pomocí admin api
const fakeFetchProdej = (objednávky: ObjednávkaPředmět[]) => {
  const formElement = document.querySelector("#prodej-mrizka-form") as HTMLFormElement;
  formElement.innerHTML = "";

  objednávky.forEach((objednávka, i) => {
    const idInput = document.createElement("input");
    idInput.setAttribute("value", objednávka.předmět.id.toString());
    idInput.setAttribute("name", `prodej-mrizka[${i}][id_predmetu]`);
    formElement.appendChild(idInput)

    const kusuInput = document.createElement("input");
    kusuInput.setAttribute("value", objednávka.množství.toString());
    kusuInput.setAttribute("name", `prodej-mrizka[${i}][kusu]`);
    formElement.appendChild(kusuInput)
  });

  formElement.submit();
}

/**
 * TODO: aktuálně refreshne stránku!!
 */
export const fetchProdej = async (objednávky: ObjednávkaPředmět[]): Promise<void> => {
  try {
    fakeFetchProdej(objednávky);
  } catch (e) {
    console.error(e);
  }
}



