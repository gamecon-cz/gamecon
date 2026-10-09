import { render } from "preact";
import { act } from "preact/test-utils";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiProduct, ApiProductTag, ApiProductWrite } from "../../api/symfony/types";
import { EditorPředmětu } from "./app";

const tag = (id: number, code: string): ApiProductTag => ({
  "@id": `/symfony/api/product_tags/${id}`,
  code,
  name: code,
});

const predmet = tag(1, "predmet");
const mikina = tag(8, "mikina");
const jidlo = tag(4, "jidlo");
const snidane = tag(13, "snidane");

const produkt = (nazev: string, tagy: ApiProductTag[]): ApiProduct => ({
  "@id": "/symfony/api/products/7",
  id: 7,
  name: nazev,
  code: "produkt-7",
  currentPrice: "90.00",
  state: 1,
  availableUntil: null,
  accommodationDay: null,
  breakfastIncluded: false,
  description: "",
  reservedForOrganizers: null,
  capacity: null,
  tags: tagy,
  variants: [{
    code: "produkt-7",
    price: null,
    capacity: null,
    reservedForOrganizers: null,
    accommodationDay: null,
    position: 0,
  }],
  sold: false,
});

const SNIDANE = "Je to snídaně";
const KATEGORIE = "Kategorie";

const kontejnery: HTMLElement[] = [];

afterEach(() => {
  kontejnery.splice(0).forEach((kontejner) => {
    render(null, kontejner);
    kontejner.remove();
  });
});

const vykresliEditor = async (upravovanyProdukt: ApiProduct, tagSnidane: ApiProductTag | null = snidane) => {
  const uložit = vi.fn<(payload: ApiProductWrite, editingId: number | null) => Promise<void>>()
    .mockResolvedValue(undefined);
  const kontejner = document.createElement("div");
  document.body.append(kontejner);
  kontejnery.push(kontejner);

  await act(() => {
    render(
      <EditorPředmětu
        produkt={upravovanyProdukt}
        kategorieTagy={[predmet, jidlo]}
        snidaneTag={tagSnidane}
        uložit={uložit}
        zrušit={() => undefined}
      />,
      kontejner,
    );
  });

  return { kontejner, uložit };
};

const pole = <Prvek extends HTMLElement>(kontejner: HTMLElement, popisek: string): Prvek => {
  const prvek = [...kontejner.querySelectorAll("label")]
    .find((štítek) => štítek.querySelector("span")?.textContent === popisek)
    ?.querySelector<Prvek>("input, select");
  if (prvek === null || prvek === undefined) {
    throw new Error(`Pole „${popisek}“ v editoru není`);
  }

  return prvek;
};

const zaskrtni = async (kontejner: HTMLElement, popisek: string, zapnout: boolean) => {
  await act(() => {
    const zaskrtavatko = pole<HTMLInputElement>(kontejner, popisek);
    if (zaskrtavatko.checked !== zapnout) {
      zaskrtavatko.click();
    }
  });
};

const vyber = async (kontejner: HTMLElement, popisek: string, hodnota: string) => {
  await act(() => {
    const vyberovePole = pole<HTMLSelectElement>(kontejner, popisek);
    vyberovePole.value = hodnota;
    vyberovePole.dispatchEvent(new Event("change", { bubbles: true }));
  });
};

const uloz = async (kontejner: HTMLElement) => {
  await act(() => {
    const tlacitko = [...kontejner.querySelectorAll("button")].find((kandidat) => kandidat.textContent === "Uložit");
    expect(tlacitko, "tlačítko Uložit").toBeDefined();
    tlacitko?.click();
  });
};

describe("EditorPředmětu", () => {
  it("při uložení pošle zpět podštítky, které editor nenabízí", async () => {
    const { kontejner, uložit } = await vykresliEditor(produkt("Mikina", [predmet, mikina]));

    await uloz(kontejner);

    expect(uložit).toHaveBeenCalledTimes(1);
    expect(uložit.mock.calls[0][0].tags).toEqual([predmet["@id"], mikina["@id"]]);
    expect(uložit.mock.calls[0][1]).toBe(7);
  });

  it("snídaně, na které se nic nezměnilo, zůstane snídaní", async () => {
    const { kontejner, uložit } = await vykresliEditor(produkt("Snídaně pátek", [jidlo, snidane]));

    await uloz(kontejner);

    expect(uložit.mock.calls[0][0].tags).toEqual([jidlo["@id"], snidane["@id"]]);
  });

  it("zaškrtnutí „Je to snídaně“ přidá štítek snidane", async () => {
    const { kontejner, uložit } = await vykresliEditor(produkt("Ranní jídlo", [jidlo]));

    await zaskrtni(kontejner, SNIDANE, true);
    await uloz(kontejner);

    expect(uložit.mock.calls[0][0].tags).toEqual([jidlo["@id"], snidane["@id"]]);
  });

  it("odškrtnutí „Je to snídaně“ štítek snidane odebere", async () => {
    const { kontejner, uložit } = await vykresliEditor(produkt("Snídaně pátek", [jidlo, snidane]));

    expect(pole<HTMLInputElement>(kontejner, SNIDANE).checked).toBe(true);
    await zaskrtni(kontejner, SNIDANE, false);
    await uloz(kontejner);

    expect(uložit.mock.calls[0][0].tags).toEqual([jidlo["@id"]]);
  });

  it("přepnutí z jídla na jinou kategorii snídani vypne, štítek se neodešle", async () => {
    const { kontejner, uložit } = await vykresliEditor(produkt("Snídaně pátek", [jidlo, snidane]));

    await vyber(kontejner, KATEGORIE, "predmet");
    await uloz(kontejner);

    expect(pole<HTMLInputElement>(kontejner, SNIDANE).checked).toBe(false);
    expect(uložit.mock.calls[0][0].tags).toEqual([predmet["@id"]]);
  });

  it("mimo jídlo je „Je to snídaně“ nedostupné", async () => {
    const { kontejner } = await vykresliEditor(produkt("Mikina", [predmet, mikina]));

    expect(pole<HTMLInputElement>(kontejner, SNIDANE).disabled).toBe(true);
  });

  it("bez štítku snidane v seznamu je zaškrtávátko nedostupné a štítek produktu zůstane", async () => {
    const { kontejner, uložit } = await vykresliEditor(produkt("Snídaně pátek", [jidlo, snidane]), null);

    expect(pole<HTMLInputElement>(kontejner, SNIDANE).disabled).toBe(true);
    await uloz(kontejner);

    expect(uložit.mock.calls[0][0].tags).toEqual([jidlo["@id"], snidane["@id"]]);
  });

  it("štítek snidane na produktu mimo jídlo jde odškrtnout, jinak by ho API u každého uložení odmítlo", async () => {
    const { kontejner, uložit } = await vykresliEditor(produkt("Omylem označený předmět", [predmet, snidane]));

    expect(pole<HTMLInputElement>(kontejner, SNIDANE).disabled).toBe(false);
    await zaskrtni(kontejner, SNIDANE, false);
    await uloz(kontejner);

    expect(uložit.mock.calls[0][0].tags).toEqual([predmet["@id"]]);
  });
});
