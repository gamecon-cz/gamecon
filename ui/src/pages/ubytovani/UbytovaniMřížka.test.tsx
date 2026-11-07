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

describe("UbytovaniMřížka: výběr nocí", () => {
  const kolej = (zmeny: Partial<ApiAccommodationType> = {}) =>
    typUbytovani({ productId: 1, name: "Kolej", nights: { 0: bunka(11), 1: bunka(12) }, ...zmeny });
  const hotel = (zmeny: Partial<ApiAccommodationType> = {}) =>
    typUbytovani({ productId: 2, name: "Hotel", nights: { 0: bunka(21), 1: bunka(22) }, ...zmeny });

  const vykresliVyber = async (typy: ApiAccommodationType[], vybrane: number[]) => {
    const odpoved = { ...ubytovani(...typy), selectedVariantIds: vybrane };
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

  it("jiný typ ubytování na tutéž noc ten první nahradí", async () => {
    const kontejner = await vykresliVyber(
      [
        kolej({ nights: { 0: bunka(11, { selected: true }), 1: bunka(12, { selected: true }) } }),
        hotel(),
      ],
      [11, 12],
    );

    await klikni(dlaždice(kontejner, 1, 0));

    expect(uložené()).toEqual([[12, 21]]);
  });

  it("jiný typ na jinou noc nic nenahrazuje", async () => {
    const kontejner = await vykresliVyber(
      [kolej({ nights: { 0: bunka(11, { selected: true }), 1: bunka(12) } }), hotel()],
      [11],
    );

    await klikni(dlaždice(kontejner, 1, 1));

    expect(uložené()).toEqual([[11, 22]]);
  });
});
