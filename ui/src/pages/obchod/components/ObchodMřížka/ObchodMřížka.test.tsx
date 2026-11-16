import { render } from "preact";
import { act } from "preact/test-utils";
import { afterEach, describe, expect, it, vi } from "vitest";
import { DefiniceObchodMřížkaBuňka, Předmět } from "../../../../api/obchod/types";
import { PředmětyContext } from "../../App";
import { ObchodMřížka } from "./ObchodMřížka";

const předmět = (změny: Partial<Předmět> = {}): Předmět => ({
  id: 10,
  název: "Tričko černé dámské",
  cena: 160,
  zbývá: null,
  varianty: [{ id: 100, název: null, cena: 160, zbývá: null }],
  archivní: false,
  ...změny,
});

const buňkaPředmětu = (text: string | undefined): DefiniceObchodMřížkaBuňka => ({
  typ: "předmět",
  cilId: 10,
  text,
});

const kontejnery: HTMLElement[] = [];

afterEach(() => {
  kontejnery.splice(0).forEach((kontejner) => {
    render(null, kontejner);
    kontejner.remove();
  });
});

const vykresli = async (prodávaný: Předmět, textBuňky: string | undefined, onBuňkaClicked = vi.fn()) => {
  const kontejner = document.createElement("div");
  document.body.append(kontejner);
  kontejnery.push(kontejner);

  await act(() => {
    render(
      <PředmětyContext.Provider value={[prodávaný]}>
        <ObchodMřížka mřížka={{ id: 1, buňky: [buňkaPředmětu(textBuňky)] }} onBuňkaClicked={onBuňkaClicked} />
      </PředmětyContext.Provider>,
      kontejner,
    );
  });

  const dlaždice = kontejner.querySelector<HTMLElement>(".shop-grid--item");
  if (dlaždice === null) {
    throw new Error("Mřížka se nevykreslila");
  }

  return {
    dlaždice,
    text: () => dlaždice.querySelector(".shop-grid--item-text")?.children[0]?.textContent,
    cenaAPoznámka: () => dlaždice.querySelector(".shop-grid--item-text")?.children[1]?.textContent?.trim(),
    zašedlá: () => dlaždice.classList.contains("shop-grid--item-sold-out"),
    onBuňkaClicked,
  };
};

describe("ObchodMřížka: dlaždice předmětu", () => {
  it("prodejný předmět ukáže text buňky a cenu", async () => {
    const dlaždice = await vykresli(předmět(), "Tričko dámské");

    expect(dlaždice.text()).toBe("Tričko dámské");
    expect(dlaždice.cenaAPoznámka()).toBe("160Kč");
    expect(dlaždice.zašedlá()).toBe(false);
  });

  it("archivní předmět je zašedlý a místo textu buňky nese svůj název s důvodem", async () => {
    const dlaždice = await vykresli(předmět({ archivní: true, zbývá: 5 }), "N/A");

    expect(dlaždice.text()).toBe("Tričko černé dámské");
    expect(dlaždice.cenaAPoznámka()).toBe("160Kč (archivní)");
    expect(dlaždice.zašedlá()).toBe(true);
  });

  it("předmět bez variant je zašedlý a nese svůj název s důvodem", async () => {
    const dlaždice = await vykresli(předmět({ varianty: [] }), "N/A");

    expect(dlaždice.text()).toBe("Tričko černé dámské");
    expect(dlaždice.cenaAPoznámka()).toBe("160Kč (nelze prodat)");
    expect(dlaždice.zašedlá()).toBe(true);
  });

  it("vyprodaný předmět si nechá text buňky, aby se dlaždice při vyprodání nepřejmenovala", async () => {
    const dlaždice = await vykresli(
      předmět({ varianty: [{ id: 100, název: null, cena: 160, zbývá: 0 }], zbývá: 0 }),
      "Tričko dámské",
    );

    expect(dlaždice.text()).toBe("Tričko dámské");
    expect(dlaždice.cenaAPoznámka()).toBe("160Kč (vyprodáno)");
    expect(dlaždice.zašedlá()).toBe(true);
  });

  it("zašedlá dlaždice se nedá prodat, prodejná ano", async () => {
    const archivní = await vykresli(předmět({ archivní: true }), "N/A");
    archivní.dlaždice.click();
    expect(archivní.onBuňkaClicked).not.toHaveBeenCalled();

    const prodejná = await vykresli(předmět(), "Tričko dámské");
    prodejná.dlaždice.click();
    expect(prodejná.onBuňkaClicked).toHaveBeenCalledTimes(1);
  });
});
