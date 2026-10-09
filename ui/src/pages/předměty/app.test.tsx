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
const jidlo = tag(4, "jidlo");
const snidane = tag(13, "snidane");

const snidanePatek: ApiProduct = {
  "@id": "/symfony/api/products/7",
  id: 7,
  name: "Snídaně pátek",
  code: "snidane-pa",
  currentPrice: "90.00",
  state: 1,
  availableUntil: null,
  accommodationDay: 2,
  breakfastIncluded: false,
  description: "",
  reservedForOrganizers: null,
  capacity: null,
  tags: [jidlo, snidane],
  variants: [{
    code: "snidane-pa",
    price: null,
    capacity: null,
    reservedForOrganizers: null,
    accommodationDay: 2,
    position: 0,
  }],
  sold: false,
};

const kontejnery: HTMLElement[] = [];

afterEach(() => {
  kontejnery.splice(0).forEach((kontejner) => {
    render(null, kontejner);
    kontejner.remove();
  });
});

describe("EditorPředmětu", () => {
  it("při uložení pošle zpět podštítky, které editor nenabízí", async () => {
    const uložit = vi.fn<(payload: ApiProductWrite, editingId: number | null) => Promise<void>>()
      .mockResolvedValue(undefined);
    const kontejner = document.createElement("div");
    document.body.append(kontejner);
    kontejnery.push(kontejner);

    await act(() => {
      render(
        <EditorPředmětu
          produkt={snidanePatek}
          kategorieTagy={[predmet, jidlo]}
          uložit={uložit}
          zrušit={() => undefined}
        />,
        kontejner,
      );
    });
    const tlacitkoUlozit = [...kontejner.querySelectorAll("button")].find((tlacitko) => tlacitko.textContent === "Uložit");
    expect(tlacitkoUlozit, "tlačítko Uložit").toBeDefined();
    await act(() => {
      tlacitkoUlozit?.click();
    });

    expect(uložit).toHaveBeenCalledTimes(1);
    expect(uložit.mock.calls[0][0].tags).toEqual([jidlo["@id"], snidane["@id"]]);
    expect(uložit.mock.calls[0][1]).toBe(7);
  });
});
