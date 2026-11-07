import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("./fetch", () => ({ symfonyFetch: vi.fn() }));

import { fetchMřížky, fetchPředměty, fetchProdej } from "../obchod/endpoints";
import {
  addToCart,
  createProduct,
  deleteProduct,
  fetchMeals,
  fetchMerch,
  fetchProducts,
  fetchProductTags,
  fetchShirts,
  removeFromCart,
  saveAccommodation,
  saveCustomerMeals,
  setEntryFee,
  updateProduct,
} from "./endpoints";
import { symfonyFetch } from "./fetch";
import { ApiAccommodationWrite, ApiProductWrite } from "./types";

const odpoved = (tělo: unknown, status = 200): Response =>
  new Response(JSON.stringify(tělo), { status });

const odpovězPoslednímVolání = (tělo: unknown, status = 200): void => {
  vi.mocked(symfonyFetch).mockResolvedValue(odpoved(tělo, status));
};

beforeEach(() => {
  vi.mocked(symfonyFetch).mockReset();
});

describe("kolekce z API", () => {
  // API Platform u nás nemá zapnutý `hydra_prefix`, takže kolekce chodí jako `member`.
  const kolekce = [
    ["fetchMeals", () => fetchMeals()],
    ["fetchMerch", () => fetchMerch()],
    ["fetchShirts", () => fetchShirts()],
    ["fetchProducts", () => fetchProducts(false)],
    ["fetchProductTags", () => fetchProductTags()],
  ] as const;

  it.each(kolekce)("%s čte prvky z `member`", async (_název, volej) => {
    odpovězPoslednímVolání({ member: [{ id: 1 }, { id: 2 }], totalItems: 2 });

    expect(await volej()).toEqual([{ id: 1 }, { id: 2 }]);
  });

  it.each(kolekce)("%s nehledá prvky pod prefixem `hydra:`", async (_název, volej) => {
    odpovězPoslednímVolání({ "hydra:member": [{ id: 1 }] });

    expect(await volej()).toEqual([]);
  });

  it("fetchPředměty čte prvky z `member`", async () => {
    odpovězPoslednímVolání({ member: [{ id: 5, name: "Placka", price: 30 }] });

    const předměty = await fetchPředměty();

    expect(předměty?.map((předmět) => předmět.id)).toEqual([5]);
  });

  it("fetchPředměty nehledá prvky pod prefixem `hydra:`", async () => {
    odpovězPoslednímVolání({ "hydra:member": [{ id: 5, name: "Placka", price: 30 }] });

    expect(await fetchPředměty()).toEqual([]);
  });

  it("fetchMřížky čte prvky z `member`", async () => {
    odpovězPoslednímVolání({ member: [{ id: 7, text: "Pití", bunky: [] }] });

    const obchod = await fetchMřížky();

    expect(obchod?.mřížky.map((mřížka) => mřížka.id)).toEqual([7]);
  });

  it("fetchMřížky nehledá prvky pod prefixem `hydra:`", async () => {
    odpovězPoslednímVolání({ "hydra:member": [{ id: 7, text: "Pití", bunky: [] }] });

    expect((await fetchMřížky())?.mřížky).toEqual([]);
  });
});

describe("zpráva o odmítnutém požadavku", () => {
  const zapisy = [
    ["saveCustomerMeals", () => saveCustomerMeals(1, [2])],
    ["saveAccommodation", () => saveAccommodation({} as ApiAccommodationWrite)],
    ["createProduct", () => createProduct({} as ApiProductWrite)],
    ["updateProduct", () => updateProduct(1, {} as ApiProductWrite)],
    ["addToCart", () => addToCart(1)],
    ["removeFromCart", () => removeFromCart(1)],
    ["deleteProduct", () => deleteProduct(1)],
    ["setEntryFee", () => setEntryFee(100)],
    ["fetchProdej", () => fetchProdej([])],
  ] as const;

  beforeEach(() => {
    // fetchProdej logs the failure it rethrows.
    vi.spyOn(console, "error").mockImplementation(() => {});
  });

  afterEach(() => {
    vi.mocked(console.error).mockRestore();
  });

  it.each(zapisy)("%s ukáže `detail` od serveru", async (_název, volej) => {
    odpovězPoslednímVolání({ detail: "Noc už je plná." }, 422);

    await expect(volej()).rejects.toThrow("Noc už je plná.");
  });

  it.each(zapisy)("%s nehledá zprávu pod prefixem `hydra:`", async (_název, volej) => {
    odpovězPoslednímVolání({ "hydra:description": "Zpráva pod prefixem." }, 422);

    await expect(volej()).rejects.not.toThrow("Zpráva pod prefixem.");
  });

  it.each(zapisy)("%s bez čitelného těla řekne aspoň stav", async (_název, volej) => {
    vi.mocked(symfonyFetch).mockResolvedValue(new Response("není JSON", { status: 500 }));

    await expect(volej()).rejects.toThrow("500");
  });
});
