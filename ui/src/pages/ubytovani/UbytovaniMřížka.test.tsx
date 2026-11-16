import { render } from "preact";
import { act } from "preact/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { fetchAccommodation, saveAccommodation } from "../../api/symfony/endpoints";
import { ApiAccommodation, ApiAccommodationCell, ApiAccommodationType } from "../../api/symfony/types";
import { formatCena } from "../../utils/castka";
import { UbytovaniMřížka } from "./UbytovaniMřížka";

vi.mock("../../api/symfony/endpoints", () => ({
  fetchAccommodation: vi.fn(),
  saveAccommodation: vi.fn(),
}));

const POPIS = "postel na 2L bez spolubydlícího, na dvoupokojové buňce se společným zázemím";
const DUVOD_SLEVY = "Ubytování zdarma";

const bunka = (variantId: number, zmeny: Partial<ApiAccommodationCell> = {}): ApiAccommodationCell => ({
  variantId,
  selected: false,
  remaining: 5,
  soldOut: false,
  reservedForOrganizers: null,
  locked: false,
  ...zmeny,
});

const typUbytovani = (zmeny: Partial<ApiAccommodationType> = {}): ApiAccommodationType => ({
  productId: 1,
  name: "Postel na 1L koleji",
  description: POPIS,
  price: "1000.00",
  discountedPrice: "1000.00",
  nights: { 0: bunka(11) },
  ...zmeny,
});

const ubytovani = (...typy: ApiAccommodationType[]): ApiAccommodation => ({
  days: [{ day: 0, name: "středa" }, { day: 1, name: "čtvrtek" }],
  types: typy,
  selectedVariantIds: [],
  minimumNights: 1,
  saleClosed: false,
  roommate: null,
  declined: false,
  restorableBreakfasts: [],
});

const kontejnery: HTMLElement[] = [];

beforeEach(() => {
  vi.mocked(saveAccommodation).mockReset();
});

afterEach(() => {
  kontejnery.splice(0).forEach((kontejner) => {
    render(null, kontejner);
    kontejner.remove();
  });
});

const vykresli = async (typ: ApiAccommodationType, customerId?: number) => {
  vi.mocked(fetchAccommodation).mockResolvedValue(ubytovani(typ));
  const kontejner = document.createElement("div");
  document.body.append(kontejner);
  kontejnery.push(kontejner);

  await act(() => {
    render(<UbytovaniMřížka customerId={customerId} />, kontejner);
  });
  await act(async () => {
    await Promise.resolve();
  });

  const buňkaTypu = kontejner.querySelector<HTMLElement>(".ubytovani-mrizka--typ");
  if (buňkaTypu === null) {
    throw new Error("Mřížka ubytování se nevykreslila");
  }

  return buňkaTypu;
};

const nápovědaCeny = (buňkaTypu: HTMLElement) =>
  buňkaTypu.querySelector(".ubytovani-mrizka--cena .hinted .hint");

describe("UbytovaniMřížka: popis typu ubytování", () => {
  it("v adminu je jen v nápovědě za názvem, ukáže se při najetí myší", async () => {
    const buňkaTypu = await vykresli(typUbytovani(), 53);

    expect(buňkaTypu.querySelector(".ubytovani-mrizka--nazev > .hinted > .hint")?.textContent).toBe(POPIS);
    expect(buňkaTypu.querySelector(".ubytovani-mrizka--popis")).toBeNull();
    expect(buňkaTypu.hasAttribute("title")).toBe(false);
  });

  it("v adminu bez popisu nemá otazník ani prázdnou bublinu", async () => {
    const buňkaTypu = await vykresli(typUbytovani({ description: "" }), 53);

    expect(buňkaTypu.querySelector(".ubytovani-mrizka--nazev .hinted")).toBeNull();
  });

  it("účastníkovi se ukazuje pod názvem, bez nápovědy", async () => {
    const buňkaTypu = await vykresli(typUbytovani(), undefined);

    expect(buňkaTypu.querySelector(".ubytovani-mrizka--popis")?.textContent).toBe(POPIS);
    expect(buňkaTypu.querySelector(".hinted")).toBeNull();
  });
});

describe("UbytovaniMřížka: důvod slevy", () => {
  const sleveny = typUbytovani({ discountedPrice: "0.00", discountReason: DUVOD_SLEVY });

  it("v adminu je u slevené ceny v nápovědě", async () => {
    const buňkaTypu = await vykresli(sleveny, 53);

    expect(nápovědaCeny(buňkaTypu)?.textContent).toBe(DUVOD_SLEVY);
  });

  it("nápověda pokrývá původní i novou cenu dohromady", async () => {
    const buňkaTypu = await vykresli(sleveny, 53);

    const cena = buňkaTypu.querySelector(".ubytovani-mrizka--cena .hinted");
    const puvodniCena = cena?.querySelector(".ubytovani-mrizka--cena-puvodni");
    expect(cena?.firstElementChild).toBe(puvodniCena);
    expect(puvodniCena?.textContent).toBe(formatCena("1000.00"));
    expect(cena?.textContent).toContain(formatCena("0.00"));
  });

  it("bez slevy se nápověda u ceny nevykreslí", async () => {
    const buňkaTypu = await vykresli(typUbytovani({ discountReason: DUVOD_SLEVY }), 53);

    expect(nápovědaCeny(buňkaTypu)).toBeNull();
    expect(buňkaTypu.querySelector(".ubytovani-mrizka--cena-puvodni")).toBeNull();
  });

  it("sleva bez důvodu nemá prázdnou bublinu", async () => {
    const buňkaTypu = await vykresli(typUbytovani({ discountedPrice: "0.00" }), 53);

    expect(nápovědaCeny(buňkaTypu)).toBeNull();
    expect(buňkaTypu.querySelector(".ubytovani-mrizka--cena-puvodni")).not.toBeNull();
  });

  it("účastník nápovědu u ceny nemá, původní cenu vidí", async () => {
    const buňkaTypu = await vykresli(sleveny, undefined);

    expect(nápovědaCeny(buňkaTypu)).toBeNull();
    expect(buňkaTypu.querySelector(".ubytovani-mrizka--cena-puvodni")).not.toBeNull();
  });
});

describe("UbytovaniMřížka: snídaně zrušené hotelovou nocí", () => {
  const vykresliSeSnidanemi = async (customerId?: number) => {
    const odpoved = { ...ubytovani(typUbytovani()), restorableBreakfasts: ["Snídaně čtvrtek"] };
    vi.mocked(fetchAccommodation).mockResolvedValue(odpoved);
    vi.mocked(saveAccommodation).mockResolvedValue({ ...odpoved, restorableBreakfasts: [] });
    const kontejner = document.createElement("div");
    document.body.append(kontejner);
    kontejnery.push(kontejner);
    await act(() => {
      render(<UbytovaniMřížka customerId={customerId} />, kontejner);
    });
    await act(async () => {
      await Promise.resolve();
    });

    return kontejner;
  };

  const vratit = (kontejner: HTMLElement) =>
    kontejner.querySelector<HTMLButtonElement>(".ubytovani-mrizka--snidane button");

  it.each([
    ["účastník", undefined],
    ["obsluha", 53],
  ])("%s je může objednat znovu a zápis jde za správného zákazníka", async (_kdo, customerId) => {
    const kontejner = await vykresliSeSnidanemi(customerId);
    const tlačítko = vratit(kontejner);
    expect(kontejner.querySelector(".ubytovani-mrizka--snidane")?.textContent).toContain("Snídaně čtvrtek");
    if (tlačítko === null) {
      throw new Error("Tlačítko pro vrácení snídaní se nevykreslilo");
    }

    await act(() => {
      tlačítko.click();
    });
    await act(async () => {
      await Promise.resolve();
    });

    expect(saveAccommodation).toHaveBeenCalledTimes(1);
    expect(vi.mocked(saveAccommodation).mock.calls[0][0].restoreBreakfasts).toBe(true);
    expect(vi.mocked(saveAccommodation).mock.calls[0][1]).toBe(customerId);
    expect(vratit(kontejner)).toBeNull();
  });

  it("bez zrušených snídaní se nabídka nevykreslí", async () => {
    await vykresli(typUbytovani(), 53);

    expect(document.querySelector(".ubytovani-mrizka--snidane")).toBeNull();
  });
});

describe("UbytovaniMřížka: výběr nocí", () => {
  const kolej = (zmeny: Partial<ApiAccommodationType> = {}) =>
    typUbytovani({ productId: 1, name: "Kolej", nights: { 0: bunka(11), 1: bunka(12) }, ...zmeny });
  const hotel = (zmeny: Partial<ApiAccommodationType> = {}) =>
    typUbytovani({ productId: 2, name: "Hotel", nights: { 0: bunka(21), 1: bunka(22) }, ...zmeny });

  const vykresliVyber = async (
    typy: ApiAccommodationType[],
    vybrane: number[],
    zmeny: Partial<ApiAccommodation> = {},
  ) => {
    const odpoved = { ...ubytovani(...typy), selectedVariantIds: vybrane, ...zmeny };
    vi.mocked(fetchAccommodation).mockResolvedValue(odpoved);
    vi.mocked(saveAccommodation).mockResolvedValue(odpoved);
    const kontejner = document.createElement("div");
    document.body.append(kontejner);
    kontejnery.push(kontejner);
    await act(() => {
      render(<UbytovaniMřížka customerId={53} />, kontejner);
    });
    await act(async () => {
      await Promise.resolve();
    });

    return kontejner;
  };

  const dlaždice = (kontejner: HTMLElement, radek: number, den: number) =>
    kontejner.querySelectorAll("tbody tr")[radek].querySelectorAll("td")[den + 1];

  const klikni = async (prvek: HTMLElement) => {
    await act(() => {
      prvek.click();
    });
  };

  const uložené = () => vi.mocked(saveAccommodation).mock.calls.map(([zápis]) => zápis.variantIds);

  it("klik na dlaždici mimo zaškrtávátko vybere noc", async () => {
    const kontejner = await vykresliVyber([kolej()], []);

    await klikni(dlaždice(kontejner, 0, 0));

    expect(uložené()).toEqual([[11]]);
  });

  it("klik na zaškrtávátko uloží noc jednou", async () => {
    const kontejner = await vykresliVyber([kolej()], []);

    const zaškrtávátko = dlaždice(kontejner, 0, 0).querySelector<HTMLElement>("input");
    if (zaškrtávátko === null) {
      throw new Error("Dlaždice nemá zaškrtávátko");
    }
    await klikni(zaškrtávátko);

    expect(uložené()).toEqual([[11]]);
  });

  it("klik na vybranou dlaždici noc odebere", async () => {
    const kontejner = await vykresliVyber([kolej({ nights: { 0: bunka(11, { selected: true }), 1: bunka(12, { selected: true }) } })], [11, 12]);

    await klikni(dlaždice(kontejner, 0, 0));

    expect(uložené()).toEqual([[12]]);
  });

  it("dokud se ukládá, další klik na dlaždici se ignoruje", async () => {
    const kontejner = await vykresliVyber([kolej()], []);
    vi.mocked(saveAccommodation).mockReturnValue(new Promise(() => undefined));

    await klikni(dlaždice(kontejner, 0, 0));
    await klikni(dlaždice(kontejner, 0, 1));

    expect(uložené()).toEqual([[11]]);
  });

  it("dva rychlé kliky před překreslením uloží jen první", async () => {
    const kontejner = await vykresliVyber([kolej()], []);
    vi.mocked(saveAccommodation).mockReturnValue(new Promise(() => undefined));

    await act(() => {
      dlaždice(kontejner, 0, 0).click();
      dlaždice(kontejner, 0, 1).click();
    });

    expect(uložené()).toEqual([[11]]);
  });

  it("zamčená dlaždice na klik nereaguje", async () => {
    const kontejner = await vykresliVyber([kolej({ nights: { 0: bunka(11, { locked: true }) } })], []);

    await klikni(dlaždice(kontejner, 0, 0));

    expect(uložené()).toEqual([]);
  });

  describe("jeden typ pokoje na celé GC", () => {
    // The hosts cannot arrange swaps of beds, so as in the old form a picked type moves every
    // night that already has one to it.
    const tri = (id: number, zmeny: Partial<ApiAccommodationType> = {}) =>
      typUbytovani({
        productId: id,
        name: `Typ ${id}`,
        nights: { 0: bunka(id * 10 + 1), 1: bunka(id * 10 + 2), 2: bunka(id * 10 + 3) },
        ...zmeny,
      });
    const tridniDny = [{ day: 0, name: "středa" }, { day: 1, name: "čtvrtek" }, { day: 2, name: "pátek" }];
    const vybranoV = (id: number, den: number) => bunka(id * 10 + den + 1, { selected: true });

    it("jiný typ na tutéž noc ten první nahradí a ostatní vybrané noci převede na nový typ", async () => {
      const kontejner = await vykresliVyber(
        [kolej({ nights: { 0: bunka(11, { selected: true }), 1: bunka(12, { selected: true }) } }), hotel()],
        [11, 12],
      );

      await klikni(dlaždice(kontejner, 1, 0));

      expect(uložené()).toEqual([[22, 21]]);
    });

    it("výběr typu na jedné noci převede na ten typ všechny už vybrané noci", async () => {
      const kontejner = await vykresliVyber(
        [tri(1, { nights: { 0: vybranoV(1, 0), 1: vybranoV(1, 1), 2: vybranoV(1, 2) } }), tri(2)],
        [11, 12, 13],
        { days: tridniDny },
      );

      await klikni(dlaždice(kontejner, 1, 1));

      expect(uložené()).toEqual([[21, 23, 22]]);
    });

    it("noc, kde nový typ není otevřený, se zruší, jako když byl vyprodaný ve starém formuláři", async () => {
      const kontejner = await vykresliVyber(
        [
          tri(1, { nights: { 0: vybranoV(1, 0), 1: vybranoV(1, 1), 2: vybranoV(1, 2) } }),
          tri(2, { nights: { 0: bunka(21), 1: bunka(22), 2: bunka(23, { soldOut: true, locked: true }) } }),
        ],
        [11, 12, 13],
        { days: tridniDny },
      );

      await klikni(dlaždice(kontejner, 1, 0));

      expect(uložené()).toEqual([[22, 21]]);
    });

    it("noc, na které nic vybrané není, zůstane prázdná", async () => {
      const kontejner = await vykresliVyber(
        [tri(1, { nights: { 0: vybranoV(1, 0), 1: bunka(12), 2: bunka(13) } }), tri(2)],
        [11],
        { days: tridniDny },
      );

      await klikni(dlaždice(kontejner, 1, 2));

      // Night 1 stays empty, so the set has a gap and waits to be fixed.
      expect(uložené()).toEqual([]);
      expect(dlaždice(kontejner, 0, 1).querySelector<HTMLInputElement>("input")?.checked).toBe(false);
      expect(kontejner.querySelector(".save-status")?.textContent).toContain("na sebe musí navazovat");
    });

    it("stejný typ na jinou noc nic nepřepíná", async () => {
      const kontejner = await vykresliVyber(
        [tri(1, { nights: { 0: vybranoV(1, 0), 1: bunka(12), 2: bunka(13) } }), tri(2)],
        [11],
        { days: tridniDny },
      );

      await klikni(dlaždice(kontejner, 0, 1));

      expect(uložené()).toEqual([[11, 12]]);
    });

    it("odškrtnutí jiné noci nic nepřepíná, ani u starého výběru s různými typy", async () => {
      const kontejner = await vykresliVyber(
        [tri(1, { nights: { 0: vybranoV(1, 0), 1: bunka(12), 2: bunka(13) } }), tri(2, { nights: { 0: bunka(21), 1: vybranoV(2, 1), 2: bunka(23) } })],
        [11, 22],
        { days: tridniDny },
      );

      await klikni(dlaždice(kontejner, 0, 0));

      expect(uložené()).toEqual([[22]]);
    });

    it("pro obsluhu s právem přeplnit se plná noc bere jako otevřená a typ se na ni přenese", async () => {
      const kontejner = await vykresliVyber(
        [
          tri(1, { nights: { 0: vybranoV(1, 0), 1: vybranoV(1, 1), 2: bunka(13) } }),
          tri(2, { nights: { 0: bunka(21), 1: bunka(22, { remaining: 0, soldOut: true, locked: false }), 2: bunka(23) } }),
        ],
        [11, 12],
        { days: tridniDny },
      );

      await klikni(dlaždice(kontejner, 1, 0));

      expect(uložené()).toEqual([[22, 21]]);
    });
  });

  describe("když jsou nejméně dvě noci", () => {
    const tridniDny = [{ day: 0, name: "středa" }, { day: 1, name: "čtvrtek" }, { day: 2, name: "pátek" }];
    const trojiceNoci = () => kolej({ nights: { 0: bunka(11), 1: bunka(12), 2: bunka(13) } });
    const dvaMinimum = { minimumNights: 2, days: tridniDny };

    const stav = (kontejner: HTMLElement) => kontejner.querySelector(".save-status")?.textContent ?? "";

    const pockej = async () => {
      await act(async () => {
        await Promise.resolve();
      });
    };

    it("první noc čeká na druhou, nic se neodešle a mřížka řekne proč", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], dvaMinimum);

      await klikni(dlaždice(kontejner, 0, 0));

      expect(uložené()).toEqual([]);
      expect(dlaždice(kontejner, 0, 0).querySelector<HTMLInputElement>("input")?.checked).toBe(true);
      expect(stav(kontejner)).toBe("● Neuloženo — Vyber ještě 1 navazující noc ubytování.");
      expect(kontejner.querySelector(".save-status")?.className).toContain("save-status--pending");
    });

    it("čekající noc je v mřížce vidět jinak než uložená", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], dvaMinimum);

      await klikni(dlaždice(kontejner, 0, 0));

      expect(dlaždice(kontejner, 0, 0).className).toContain("ubytovani-mrizka--neulozeno");
      expect(dlaždice(kontejner, 0, 1).className).not.toContain("ubytovani-mrizka--neulozeno");
    });

    it("výběr, který musí člověk opravit, je červený: dlaždice i řádek se stavem", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], dvaMinimum);

      await klikni(dlaždice(kontejner, 0, 0));

      expect(dlaždice(kontejner, 0, 0).className).toContain("ubytovani-mrizka--vyzaduje-akci");
      expect(dlaždice(kontejner, 0, 1).className).not.toContain("ubytovani-mrizka--vyzaduje-akci");
      expect(kontejner.querySelector(".save-status")?.className).toContain("save-status--pending");
    });

    it("výběr na cestě k serveru červený není, jen čeká", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], { ...dvaMinimum, minimumNights: 1 });
      vi.mocked(saveAccommodation).mockReturnValue(new Promise(() => undefined));

      await klikni(dlaždice(kontejner, 0, 0));

      expect(dlaždice(kontejner, 0, 0).className).toContain("ubytovani-mrizka--neulozeno");
      expect(dlaždice(kontejner, 0, 0).className).not.toContain("ubytovani-mrizka--vyzaduje-akci");
      expect(kontejner.querySelector(".save-status")?.className).toContain("save-status--saving");
    });

    it("druhá navazující noc celý výběr uloží a mřížka to potvrdí", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], dvaMinimum);

      await klikni(dlaždice(kontejner, 0, 0));
      await klikni(dlaždice(kontejner, 0, 1));
      await pockej();

      expect(uložené()).toEqual([[11, 12]]);
      expect(stav(kontejner)).toBe("✓ Uloženo");
      expect(kontejner.querySelector(".ubytovani-mrizka--neulozeno")).toBeNull();
    });

    it("nenavazující noci čekají, dokud mezera není zaplněná", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], dvaMinimum);

      await klikni(dlaždice(kontejner, 0, 0));
      await klikni(dlaždice(kontejner, 0, 2));

      expect(uložené()).toEqual([]);
      expect(stav(kontejner)).toBe("● Neuloženo — Vybrané noci ubytování na sebe musí navazovat.");

      await klikni(dlaždice(kontejner, 0, 1));
      await pockej();

      expect(uložené()).toEqual([[11, 13, 12]]);
    });

    it("když zákazník drží noc, kterou mřížka neukazuje, výběr nezdržuje a posoudí ho server", async () => {
      // E.g. a room type hidden by the sleeping-bag restriction: the grid cannot tell its day.
      const kontejner = await vykresliVyber([trojiceNoci()], [99], dvaMinimum);

      await klikni(dlaždice(kontejner, 0, 0));
      await pockej();

      expect(uložené()).toEqual([[99, 11]]);
    });

    it("odškrtnutí na jedinou noc čeká a odškrtnutí poslední noci uloží prázdný výběr", async () => {
      const kontejner = await vykresliVyber(
        [kolej({ nights: { 0: bunka(11, { selected: true }), 1: bunka(12, { selected: true }) } })],
        [11, 12],
        dvaMinimum,
      );

      await klikni(dlaždice(kontejner, 0, 0));

      expect(uložené()).toEqual([]);
      expect(stav(kontejner)).toContain("Vyber ještě 1 navazující noc");

      await klikni(dlaždice(kontejner, 0, 1));
      await pockej();

      expect(uložené()).toEqual([[]]);
    });

    it("vrácení výběru do uloženého stavu čekání zruší", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], dvaMinimum);

      await klikni(dlaždice(kontejner, 0, 0));
      await klikni(dlaždice(kontejner, 0, 0));

      expect(uložené()).toEqual([]);
      expect(stav(kontejner)).toBe("");
      expect(kontejner.querySelector(".ubytovani-mrizka--neulozeno")).toBeNull();
    });

    it("pokud mají právo na jednu noc, uloží se hned", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], { ...dvaMinimum, minimumNights: 1 });

      await klikni(dlaždice(kontejner, 0, 0));
      await pockej();

      expect(uložené()).toEqual([[11]]);
    });

    it("spolubydlící se uloží i při čekajícím výběru a výběr dál čeká", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], dvaMinimum);
      await klikni(dlaždice(kontejner, 0, 0));

      const pole = kontejner.querySelector<HTMLInputElement>(".ubytovani-mrizka--spolubydlici input");
      if (pole === null) {
        throw new Error("Pole spolubydlícího se nevykreslilo");
      }
      pole.value = "Někdo";
      await act(() => {
        pole.dispatchEvent(new Event("change", { bubbles: true }));
      });
      await pockej();

      expect(vi.mocked(saveAccommodation).mock.calls[0][0]).toMatchObject({ variantIds: [], roommate: "Někdo" });
      expect(stav(kontejner)).toContain("Neuloženo");
      expect(dlaždice(kontejner, 0, 0).querySelector<HTMLInputElement>("input")?.checked).toBe(true);
    });

    it("klik na noc ve chvíli, kdy běží jiný zápis, se zahodí celý, ani se neoznačí jako neuložený", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], { ...dvaMinimum, minimumNights: 1 });
      vi.mocked(saveAccommodation).mockReturnValue(new Promise(() => undefined));
      const pole = kontejner.querySelector<HTMLInputElement>(".ubytovani-mrizka--spolubydlici input");
      if (pole === null) {
        throw new Error("Pole spolubydlícího se nevykreslilo");
      }
      pole.value = "Někdo";

      // Both before the re-render that would disable the grid.
      await act(() => {
        pole.dispatchEvent(new Event("change", { bubbles: true }));
        dlaždice(kontejner, 0, 0).click();
      });

      expect(uložené()).toEqual([[]]);
      expect(dlaždice(kontejner, 0, 0).querySelector<HTMLInputElement>("input")?.checked).toBe(false);
      expect(kontejner.querySelector(".ubytovani-mrizka--neulozeno")).toBeNull();
    });

    it("po uložení spolubydlícího se čekající výběr nehlásí jako uložený, ani když ho nová mřížka neukazuje", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], dvaMinimum);
      await klikni(dlaždice(kontejner, 0, 0));
      // The answer no longer shows night 11, so the pick cannot be judged against it.
      vi.mocked(saveAccommodation).mockResolvedValue({
        ...ubytovani(kolej({ nights: { 1: bunka(12), 2: bunka(13) } })),
        ...dvaMinimum,
      });
      const pole = kontejner.querySelector<HTMLInputElement>(".ubytovani-mrizka--spolubydlici input");
      if (pole === null) {
        throw new Error("Pole spolubydlícího se nevykreslilo");
      }
      pole.value = "Někdo";

      await act(() => {
        pole.dispatchEvent(new Event("change", { bubbles: true }));
      });
      await pockej();

      expect(stav(kontejner)).toContain("Neuloženo");
    });

    it("zamítnutý zápis vrátí mřížku k tomu, co má server, a řekne to", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], { ...dvaMinimum, minimumNights: 1 });
      vi.mocked(saveAccommodation).mockRejectedValue(new Error("Noc je obsazená."));

      await klikni(dlaždice(kontejner, 0, 0));
      await pockej();

      expect(kontejner.querySelector(".ubytovani-mrizka--error")?.textContent).toBe("Noc je obsazená.");
      expect(stav(kontejner)).toBe("Uložení se nepodařilo");
      expect(dlaždice(kontejner, 0, 0).querySelector<HTMLInputElement>("input")?.checked).toBe(false);
    });

    it("zaškrtnutí „Ubytování nechci“ nejde, dokud čeká nějaká noc", async () => {
      const kontejner = await vykresliVyber([trojiceNoci()], [], dvaMinimum);

      await klikni(dlaždice(kontejner, 0, 0));

      expect(kontejner.querySelector<HTMLInputElement>(".ubytovani-mrizka--nechci input")?.disabled).toBe(true);
    });
  });

  describe("přeplnění plné noci", () => {
    const plna = (zmeny: Partial<ApiAccommodationCell> = {}) =>
      bunka(11, { remaining: 0, soldOut: true, locked: false, ...zmeny });

    it("plná noc se pro toho, kdo smí přeplnit, dá zaškrtnout a vypadá jinak než zamčená", async () => {
      const kontejner = await vykresliVyber([kolej({ nights: { 0: plna() } })], []);

      expect(dlaždice(kontejner, 0, 0).className).toContain("ubytovani-mrizka--k-preplneni");
      expect(dlaždice(kontejner, 0, 0).className).toContain("ubytovani-mrizka--klikaci");
      expect(dlaždice(kontejner, 0, 0).textContent).toContain("vyprodáno");

      await klikni(dlaždice(kontejner, 0, 0));

      expect(uložené()).toEqual([[11]]);
    });

    it("zamčená plná noc na klik nereaguje a není oranžová", async () => {
      const kontejner = await vykresliVyber([kolej({ nights: { 0: plna({ locked: true }) } })], []);

      expect(dlaždice(kontejner, 0, 0).className).not.toContain("ubytovani-mrizka--k-preplneni");

      await klikni(dlaždice(kontejner, 0, 0));

      expect(uložené()).toEqual([]);
    });

    it("přeplněná noc ukáže zbytek pod nulou a je oranžová", async () => {
      const kontejner = await vykresliVyber(
        [kolej({ nights: { 0: plna({ remaining: -2, soldOut: false, selected: true }) } })],
        [11],
      );

      expect(dlaždice(kontejner, 0, 0).textContent).toContain("zbývá −2");
      expect(dlaždice(kontejner, 0, 0).className).toContain("ubytovani-mrizka--preplneno");
    });

    it("přeplněná noc, kterou zákazník nemá, ukáže zbytek pod nulou, ne jen „vyprodáno“", async () => {
      const kontejner = await vykresliVyber([kolej({ nights: { 0: plna({ remaining: -1 }) } })], []);

      expect(dlaždice(kontejner, 0, 0).textContent).toContain("zbývá −1");
      expect(dlaždice(kontejner, 0, 0).className).toContain("ubytovani-mrizka--k-preplneni");
    });

    it("zamčená přeplněná noc ukáže číslo, ale oranžová není, protože se nedá přeplnit", async () => {
      const kontejner = await vykresliVyber([kolej({ nights: { 0: plna({ remaining: -1, locked: true }) } })], []);

      expect(dlaždice(kontejner, 0, 0).textContent).toContain("zbývá −1");
      expect(dlaždice(kontejner, 0, 0).className).not.toContain("ubytovani-mrizka--preplneno");
      expect(dlaždice(kontejner, 0, 0).className).not.toContain("ubytovani-mrizka--k-preplneni");
    });
  });
});
