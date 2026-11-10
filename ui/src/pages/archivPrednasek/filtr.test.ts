import { describe, expect, it } from "vitest";
import { ApiArchivPrednasek } from "../../api/archivPrednasek";
import { normalizujText } from "../filtrStranky/text";
import {
  filtrArchivuZUrl, FiltrArchivu, filtrJenSeZnámýmiHodnotami, filtrujPřednášky, jeFiltrArchivuPrázdný, počtemPřednášek,
  počtyMožností, PRÁZDNÝ_FILTR_ARCHIVU, PřednáškaProFiltr, sPřednášejícím, urlSFiltremArchivu,
} from "./filtr";

const přednáška = (htmlId: string, vlastnosti: Partial<PřednáškaProFiltr>): PřednáškaProFiltr => ({
  htmlId,
  id: 0,
  rok: 2024,
  prednasejici: [],
  stitky: [],
  zaznam: false,
  text: "",
  ...vlastnosti,
});

const SIRIEN = 1;
const MACKAN = 2;
const GUFF = 3;

const přednášky: PřednáškaProFiltr[] = [
  přednáška("kobky", {
    rok: 2025, prednasejici: [SIRIEN], stitky: ["rpg", "dungeoncrawl"], zaznam: false,
    text: normalizujText("RPG kobky nejen pro prolézače Panelová diskuse o dungeonech"),
  }),
  přednáška("stredovek", {
    rok: 2022, prednasejici: [MACKAN], stitky: ["rpg", "historie"], zaznam: true,
    text: normalizujText("Jak na evropský raný středověk v RPG"),
  }),
  přednáška("mdrd", {
    rok: 2022, prednasejici: [MACKAN, GUFF], stitky: ["mdrd", "draci-doupe"], zaznam: true,
    text: normalizujText("Mistrovství DrD – Hodnocení družin"),
  }),
  přednáška("zivot-zaporaka", {
    rok: 2012, prednasejici: [SIRIEN], stitky: ["rpg", "postavy"], zaznam: false,
    text: normalizujText("Život záporáka"),
  }),
];

const idčka = (vybrané: PřednáškaProFiltr[]) => vybrané.map((vybraná) => vybraná.htmlId);

const filtr = (změna: Partial<FiltrArchivu>): FiltrArchivu => ({ ...PRÁZDNÝ_FILTR_ARCHIVU, ...změna });

describe("filtrujPřednášky", () => {
  it("prázdný filtr nechá všechny", () => {
    expect(idčka(filtrujPřednášky(přednášky, PRÁZDNÝ_FILTR_ARCHIVU))).toEqual(["kobky", "stredovek", "mdrd", "zivot-zaporaka"]);
    expect(jeFiltrArchivuPrázdný(PRÁZDNÝ_FILTR_ARCHIVU)).toBe(true);
    expect(jeFiltrArchivuPrázdný(filtr({ text: "  " }))).toBe(true);
  });

  it("uvnitř kategorie stačí kterákoli vybraná hodnota", () => {
    expect(idčka(filtrujPřednášky(přednášky, filtr({ roky: [2025, 2012] })))).toEqual(["kobky", "zivot-zaporaka"]);
    expect(idčka(filtrujPřednášky(přednášky, filtr({ přednášející: [GUFF, SIRIEN] })))).toEqual(["kobky", "mdrd", "zivot-zaporaka"]);
    expect(idčka(filtrujPřednášky(přednášky, filtr({ štítky: ["historie", "mdrd"] })))).toEqual(["stredovek", "mdrd"]);
  });

  it("mezi kategoriemi musí platit všechny", () => {
    expect(idčka(filtrujPřednášky(přednášky, filtr({ přednášející: [MACKAN], štítky: ["rpg"] })))).toEqual(["stredovek"]);
    expect(idčka(filtrujPřednášky(přednášky, filtr({ roky: [2022], jenSeZáznamem: true, štítky: ["draci-doupe"] })))).toEqual(["mdrd"]);
    expect(filtrujPřednášky(přednášky, filtr({ roky: [2012], jenSeZáznamem: true }))).toEqual([]);
  });

  it("přednáška s víc přednášejícími je pod každým z nich", () => {
    expect(idčka(filtrujPřednášky(přednášky, filtr({ přednášející: [GUFF] })))).toEqual(["mdrd"]);
    expect(idčka(filtrujPřednášky(přednášky, filtr({ přednášející: [MACKAN] })))).toEqual(["stredovek", "mdrd"]);
  });

  it("jen se záznamem", () => {
    expect(idčka(filtrujPřednášky(přednášky, filtr({ jenSeZáznamem: true })))).toEqual(["stredovek", "mdrd"]);
  });

  it("hledá bez diakritiky a velikosti písmen, všechna slova", () => {
    expect(idčka(filtrujPřednášky(přednášky, filtr({ text: "STREDOVEK" })))).toEqual(["stredovek"]);
    expect(idčka(filtrujPřednášky(přednášky, filtr({ text: "zápor  život" })))).toEqual(["zivot-zaporaka"]);
    expect(idčka(filtrujPřednášky(přednášky, filtr({ text: "dungeon" })))).toEqual(["kobky"]);
    expect(filtrujPřednášky(přednášky, filtr({ text: "středověk záporák" }))).toEqual([]);
  });
});

describe("počtyMožností", () => {
  it("bez výběru v kategorii počítá, kolik přednášek s možností zbude", () => {
    const počty = počtyMožností(přednášky, filtr({ jenSeZáznamem: true }), "roky", [2025, 2022, 2012]);
    expect(počty.get(2022)).toEqual({ počet: 2, navíc: false });
    expect(počty.get(2025)).toEqual({ počet: 0, navíc: false });
  });

  it("s výběrem v kategorii počítá, kolik přednášek přibude, ostatní kategorie platí dál", () => {
    const počty = počtyMožností(přednášky, filtr({ přednášející: [MACKAN], štítky: ["rpg"] }), "přednášející", [SIRIEN, MACKAN, GUFF]);
    // zobrazený je jen středověk; sirien s RPG přidá kobky a život záporáka, guff s RPG nic
    expect(počty.get(SIRIEN)).toEqual({ počet: 2, navíc: true });
    expect(počty.get(GUFF)).toEqual({ počet: 0, navíc: true });
    expect(počty.get(MACKAN)).toEqual({ počet: 0, navíc: true });
  });

  it("u štítků se nepočítá s vlastním výběrem štítků", () => {
    const počty = počtyMožností(přednášky, filtr({ štítky: ["mdrd"] }), "štítky", ["rpg", "historie"]);
    expect(počty.get("rpg")).toEqual({ počet: 3, navíc: true });
    expect(počty.get("historie")).toEqual({ počet: 1, navíc: true });
  });
});

describe("adresa", () => {
  const data: ApiArchivPrednasek = {
    prednasky: [],
    roky: [2025, 2022, 2012],
    prednasejici: [{ id: SIRIEN, jmeno: "sirien" }, { id: MACKAN, jmeno: "mackan" }],
    stitky: [{ klic: "rpg", nazev: "RPG", skupina: "Klíčová slova" }, { klic: "horor", nazev: "Horor", skupina: "žánr" }],
  };

  it("filtr se zapíše do adresy a přečte zpátky, čárky zůstanou čitelné", () => {
    const vybraný = filtr({ text: "středověk", roky: [2022, 2012], přednášející: [MACKAN], štítky: ["rpg", "horor"], jenSeZáznamem: true });
    const adresa = urlSFiltremArchivu("https://gamecon.cz/prednasky/archiv", vybraný);

    expect(adresa).toContain("rok=2022,2012");
    expect(adresa).toContain("stitky=rpg,horor");
    expect(adresa).toContain("zaznam=1");
    expect(filtrArchivuZUrl(adresa)).toEqual(vybraný);
  });

  it("prázdný filtr adresu vyčistí, jiné parametry a kotvu nechá", () => {
    const adresa = urlSFiltremArchivu("https://gamecon.cz/prednasky/archiv?rok=2022&utm_source=x#prednaska-5", PRÁZDNÝ_FILTR_ARCHIVU);

    expect(adresa).toBe("https://gamecon.cz/prednasky/archiv?utm_source=x#prednaska-5");
  });

  it("neznámé hodnoty z adresy se zahodí", () => {
    const zAdresy = filtrArchivuZUrl("https://gamecon.cz/prednasky/archiv?rok=2008,2022,x&prednasejici=99,1&stitky=rpg,neznamy&zaznam=ano");

    expect(filtrJenSeZnámýmiHodnotami(zAdresy, data)).toEqual(filtr({ roky: [2022], přednášející: [SIRIEN], štítky: ["rpg"] }));
  });
});

describe("drobnosti", () => {
  it("klik na přednášejícího ho přidá jen jednou", () => {
    expect(sPřednášejícím(filtr({ přednášející: [GUFF] }), SIRIEN).přednášející).toEqual([GUFF, SIRIEN]);
    const sGuffem = filtr({ přednášející: [GUFF] });
    expect(sPřednášejícím(sGuffem, GUFF)).toBe(sGuffem);
  });

  it("skloňuje počet přednášek", () => {
    expect(počtemPřednášek(1)).toBe("1 přednáška");
    expect(počtemPřednášek(3)).toBe("3 přednášky");
    expect(počtemPřednášek(0)).toBe("0 přednášek");
    expect(počtemPřednášek(234)).toBe("234 přednášek");
  });
});
