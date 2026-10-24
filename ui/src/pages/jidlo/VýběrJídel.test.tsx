import { render } from "preact";
import { act } from "preact/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ApiMealMenu } from "../../api/symfony/types";
import { datumCesky, poDnech } from "./jidelnicek";

const { fetchMealMenu, chooseMealDish } = vi.hoisted(() => ({
  fetchMealMenu: vi.fn<(customerId?: number) => Promise<ApiMealMenu>>(),
  chooseMealDish: vi.fn<(orderItemId: number, dishId: number | null, customerId?: number) => Promise<ApiMealMenu>>(),
}));

vi.mock("../../api/symfony/endpoints", () => ({ fetchMealMenu, chooseMealDish }));

import { VýběrJídel } from "./VýběrJídel";

const menu = (zmena: Partial<ApiMealMenu> = {}, chosenDishId: number | null = null): ApiMealMenu => ({
  published: true,
  publishedFrom: "2026-07-02",
  locked: false,
  choiceDeadline: "2026-07-15",
  slots: [
    {
      variantId: 11,
      day: 2,
      dayName: "pátek",
      name: "Oběd pátek",
      dishes: [
        { id: 1, name: "Guláš", description: null, vegetarian: false, default: false },
        { id: 2, name: "Rizoto", description: "s parmazánem", vegetarian: true, default: true },
      ],
      portions: [{ orderItemId: 501, chosenDishId, effectiveDishId: chosenDishId ?? 2 }],
    },
    {
      variantId: 12,
      day: 2,
      dayName: "pátek",
      name: "Večeře pátek",
      dishes: [{ id: 3, name: "Pizza", description: null, vegetarian: false, default: true }],
      portions: [],
    },
  ],
  ...zmena,
});

const kontejnery: HTMLElement[] = [];

const vykresli = async (customerId?: number): Promise<HTMLElement> => {
  const kontejner = document.createElement("div");
  document.body.append(kontejner);
  kontejnery.push(kontejner);
  void act(() => {
    render(<VýběrJídel customerId={customerId} />, kontejner);
  });
  await act(async () => {
    await Promise.resolve();
  });

  return kontejner;
};

beforeEach(() => {
  fetchMealMenu.mockReset();
  chooseMealDish.mockReset();
});

afterEach(() => {
  kontejnery.splice(0).forEach((kontejner) => {
    render(null, kontejner);
    kontejner.remove();
  });
});

describe("výběr jídel", () => {
  it("nabízí jen porce, které má účastník koupené, a říká, co dostane bez výběru", async () => {
    fetchMealMenu.mockResolvedValue(menu());

    const kontejner = await vykresli();

    const selecty = kontejner.querySelectorAll("select");
    expect(selecty).toHaveLength(1);
    expect(selecty[0].options[0].textContent).toBe("nevybráno – dostaneš Rizoto (vegetariánské)");
    expect(kontejner.textContent).toContain("s parmazánem");
    expect(kontejner.textContent).not.toContain("Večeře");
  });

  it("uloží vybrané jídlo k porci", async () => {
    fetchMealMenu.mockResolvedValue(menu());
    chooseMealDish.mockResolvedValue(menu({}, 1));
    const kontejner = await vykresli();
    const select = kontejner.querySelector("select") as HTMLSelectElement;

    void act(() => {
      select.value = "1";
      select.dispatchEvent(new Event("change"));
    });
    await act(async () => {
      await Promise.resolve();
    });

    expect(chooseMealDish).toHaveBeenCalledWith(501, 1, undefined);
    expect((kontejner.querySelector("select") as HTMLSelectElement).value).toBe("1");
  });

  it("před zveřejněním jen oznámí, kdy jídelníček bude", async () => {
    fetchMealMenu.mockResolvedValue(menu({ published: false }));

    const kontejner = await vykresli();

    expect(kontejner.querySelector("select")).toBeNull();
    expect(kontejner.textContent).toContain("Jídelníček zveřejníme 2. 7.");
  });

  it("po termínu ukáže, co porce dostane, a nedá nic změnit", async () => {
    fetchMealMenu.mockResolvedValue(menu({ locked: true }));

    const kontejner = await vykresli();

    expect(kontejner.querySelector("select")).toBeNull();
    expect(kontejner.textContent).toContain("Rizoto (vegetariánské) (výchozí)");
  });

  it("bez koupených obědů a večeří nevykreslí nic", async () => {
    fetchMealMenu.mockResolvedValue(menu({ slots: [] }));

    const kontejner = await vykresli();

    expect(kontejner.innerHTML).toBe("");
  });
});

describe("pomocné funkce jídelníčku", () => {
  it("datum píše česky bez roku", () => {
    expect(datumCesky("2026-07-05")).toBe("5. 7.");
  });

  it("seskupí sloty po dnech v pořadí ze serveru", () => {
    const dny = poDnech(menu().slots);

    expect(dny.map((den) => [den.dayName, den.sloty.map((slot) => slot.name)])).toEqual([
      ["pátek", ["Oběd pátek", "Večeře pátek"]],
    ]);
  });
});
