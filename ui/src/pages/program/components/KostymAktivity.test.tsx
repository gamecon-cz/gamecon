import { describe, it, expect, beforeEach } from "vitest";
import { h, render } from "preact";
import { KostymAktivity } from "./KostymAktivity";

describe("KostymAktivity", () => {
  let kontejner: HTMLDivElement;

  beforeEach(() => {
    kontejner = document.createElement("div");
  });

  it("u rezervovaného kostýmu ukáže název s odkazem na vlastní rezervace", () => {
    render(h(KostymAktivity, { kostym: { nazev: "Elfí plášť", url: "https://gamecon.cz/kostymy/moje" } }), kontejner);

    expect(kontejner.textContent).toBe("Kostým: Elfí plášť");
    expect(kontejner.querySelector("a")?.getAttribute("href")).toBe("https://gamecon.cz/kostymy/moje");
  });

  it("bez rezervace nabídne katalog", () => {
    render(h(KostymAktivity, { kostym: { nazev: null, url: "https://gamecon.cz/kostymy?aktivita=5" } }), kontejner);

    expect(kontejner.textContent).toBe("Půjčit si kostým");
    expect(kontejner.querySelector("a")?.getAttribute("href")).toBe("https://gamecon.cz/kostymy?aktivita=5");
  });

  it("u aktivity bez půjčovny nic", () => {
    render(h(KostymAktivity, { kostym: null }), kontejner);

    expect(kontejner.innerHTML).toBe("");
  });
});
