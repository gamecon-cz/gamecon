import { GAMECON_KONSTANTY } from "../../env";
import { symfonyFetch } from "../symfony/fetch";
import { ApiHydraCollection } from "../symfony/types";
import { DefiniceObchod, DefiniceObchodMřížka, DefiniceObchodMřížkaBuňka, DefiniceObchodMřížkaBuňkaPředmět, DefiniceObchodMřížkaBuňkaStránka, DefiniceObchodMřížkaBuňkaTyp, ObjednávkaPředmět, Předmět } from "./types";

/**
 * API types matching Symfony KFC DTOs
 */
type ApiKfcProduct = {
  id: number,
  name: string,
  price: number,
  remaining: number | null,
};

type ApiKfcGrid = {
  id: number,
  text: string | null,
  bunky: {
    id: number | null,
    typ: number,
    text: string | null,
    barva: string | null,
    barvaText: string | null,
    cilId: number | null,
  }[],
};

export const fetchMřížky = async (): Promise<DefiniceObchod | null> => {
  try {
    const response = await symfonyFetch("kfc/grids");
    if (!response.ok) throw new Error(`Failed to fetch grids: ${response.status}`);
    const data = await response.json() as ApiHydraCollection<ApiKfcGrid>;
    const grids = data["hydra:member"] ?? data["member"] ?? [];

    const obchod: DefiniceObchod = {
      mřížky: grids.map(grid => ({
        id: grid.id,
        text: grid.text ?? undefined,
        buňky: grid.bunky.map(
          (cell) => ({
            typ: DefiniceObchodMřížkaBuňkaTyp[cell.typ] as string,
            cilId: cell.cilId ?? undefined,
            text: cell.text ?? undefined,
            barvaPozadí: cell.barva ?? undefined,
            barvaText: cell.barvaText ?? undefined,
            id: cell.id ?? undefined,
          } as DefiniceObchodMřížkaBuňka)
        ),
      }))
    }

    return obchod;
  } catch (error) {
    console.error(error);
  }
  return null;
};

export const fetchNastavMřížky = async (obchod: DefiniceObchod) => {
  try {
    const grids = obchod.mřížky.map(grid => ({
      id: grid.id,
      text: grid.text,
      bunky: grid.buňky.map(cell => ({
        id: cell.id,
        barva: cell.barvaPozadí,
        barvaText: cell.barvaText,
        cilId: (cell as (
          DefiniceObchodMřížkaBuňkaPředmět | DefiniceObchodMřížkaBuňkaStránka
        ))?.cilId,
        text: cell.text,
        typ: DefiniceObchodMřížkaBuňkaTyp[cell.typ],
      }))
    }));

    const response = await symfonyFetch("kfc/grids", {
      method: "POST",
      body: JSON.stringify({ grids }),
    });

    if (response.status >= 200 && response.status < 300)
      return true;
  } catch (error) {
    console.error(error);
  }
  return false;
};

export const fetchPředměty = async (): Promise<Předmět[] | null> => {
  try {
    const response = await symfonyFetch("kfc/products");
    if (!response.ok) throw new Error(`Failed to fetch products: ${response.status}`);
    const data = await response.json() as ApiHydraCollection<ApiKfcProduct>;
    const products = data["hydra:member"] ?? data["member"] ?? [];

    return products.map(product => ({
      název: product.name,
      cena: product.price,
      id: product.id,
      zbývá: product.remaining,
    } as Předmět));
  } catch (error) {
    console.error(error);
  }
  return null;
}

/**
 * Submit KFC sale via Symfony API (replaces legacy form hack).
 * No page reload — returns sale confirmation.
 */
export const fetchProdej = async (objednávky: ObjednávkaPředmět[]): Promise<void> => {
  try {
    const items = objednávky.map(objednávka => ({
      productId: objednávka.předmět.id,
      quantity: objednávka.množství,
    }));

    const response = await symfonyFetch("kfc/sale", {
      method: "POST",
      body: JSON.stringify({ items }),
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw new Error(errorData.detail ?? `Sale failed: ${response.status}`);
    }
  } catch (error) {
    console.error(error);
    throw error;
  }
}
