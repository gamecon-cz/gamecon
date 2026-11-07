import { render } from "preact";
import { act } from "preact/test-utils";
import { afterEach, describe, expect, it, vi } from "vitest";
import { fetchAccommodation } from "../../api/symfony/endpoints";
import { ApiAccommodation, ApiAccommodationType } from "../../api/symfony/types";
import { formatCena } from "../../utils/castka";
import { UbytovaniMřížka } from "./UbytovaniMřížka";

vi.mock("../../api/symfony/endpoints", () => ({
  fetchAccommodation: vi.fn(),
  saveAccommodation: vi.fn(),
}));

const POPIS = "postel na 2L bez spolubydlícího, na dvoupokojové buňce se společným zázemím";
const DUVOD_SLEVY = "Ubytování zdarma";

const typUbytovani = (zmeny: Partial<ApiAccommodationType> = {}): ApiAccommodationType => ({
  productId: 1,
  name: "Postel na 1L koleji",
  description: POPIS,
  price: "1000.00",
  discountedPrice: "1000.00",
  nights: {
    0: {
      variantId: 11,
      selected: false,
      remaining: 5,
      soldOut: false,
      reservedForOrganizers: null,
      locked: false,
    },
  },
  ...zmeny,
});

const ubytovani = (typ: ApiAccommodationType): ApiAccommodation => ({
  days: [{ day: 0, name: "středa" }],
  types: [typ],
  selectedVariantIds: [],
  minimumNights: 1,
  saleClosed: false,
  roommate: null,
  declined: false,
  restorableBreakfasts: [],
});

const kontejnery: HTMLElement[] = [];

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
