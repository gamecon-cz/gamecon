import { describe, expect, it } from "vitest";
import { cenaDalsihoKusu, popisSchodu } from "./cenovySchod";
import { ApiPriceStep } from "./types";

const krok = (
  fromQuantity: number,
  price: string,
  ruleCode: string | null = null,
): ApiPriceStep => ({
  fromQuantity,
  price,
  discountAmount: "0.00",
  ruleCode,
  ruleName: ruleCode,
});

describe("popisSchodu", () => {
  it("u jediného stupně nic nevysvětluje", () => {
    expect(popisSchodu([krok(1, "120.00")])).toBeNull();
  });

  it("popíše první kus zdarma", () => {
    expect(popisSchodu([krok(1, "0.00", "kostka_zdarma"), krok(2, "30.00")])).toBe(
      "0 Kč / 30 Kč (první zdarma)",
    );
  });

  it("dva zdarma vyčte z pořadí dalšího stupně", () => {
    expect(popisSchodu([krok(1, "0.00", "dve_tricka"), krok(3, "400.00")])).toBe(
      "0 Kč / 400 Kč (první dva zdarma)",
    );
  });

  /**
   * Dva řetězené nároky (2 + 1 zdarma) mají tři stupně. Dřív se prostřední zahodil
   * a popis tvrdil „první dva" u tří zvýhodněných kusů.
   */
  it("nezahodí prostřední stupeň u řetězených nároků", () => {
    const schody = [
      krok(1, "0.00", "dve_tricka_zdarma"),
      krok(3, "0.00", "jedno_tricko_zdarma"),
      krok(4, "400.00"),
    ];

    expect(popisSchodu(schody)).toBe("0 Kč / 0 Kč / 400 Kč (první 3 zdarma)");
  });

  it("u částečné slevy neříká zdarma", () => {
    expect(popisSchodu([krok(1, "200.00", "pul_ceny"), krok(2, "400.00")])).toBe(
      "200 Kč / 400 Kč (první se slevou)",
    );
  });
});

describe("cenaDalsihoKusu", () => {
  it("bere první stupeň, protože server žebřík už posunul", () => {
    expect(cenaDalsihoKusu([krok(1, "0.00", "zdarma"), krok(2, "30.00")])).toBe("0.00");
  });

  it("u prázdného žebříku vrátí null", () => {
    expect(cenaDalsihoKusu([])).toBeNull();
  });
});
