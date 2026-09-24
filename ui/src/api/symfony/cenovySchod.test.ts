import { describe, expect, it } from "vitest";
import { cenaDalsihoKusu, popisSchodu } from "./cenovySchod";
import { ApiPriceStep } from "./types";

const krok = (
  fromQuantity: number,
  price: string,
  ruleCode: string | null = null,
  label: string | null = null,
): ApiPriceStep => ({
  fromQuantity,
  price,
  discountAmount: "0.00",
  ruleCode,
  ruleName: ruleCode,
  label,
});

describe("popisSchodu", () => {
  it("u jediného stupně nic nevysvětluje", () => {
    expect(popisSchodu([krok(1, "120.00")])).toBeNull();
  });

  it("popíše první kus zdarma", () => {
    expect(
      popisSchodu([krok(1, "0.00", "kostka_zdarma", "první zdarma"), krok(2, "30.00")]),
    ).toBe("0 Kč / 30 Kč (první zdarma)");
  });

  it("dva zdarma vezme z popisu od serveru", () => {
    expect(
      popisSchodu([krok(1, "0.00", "dve_tricka", "první dva zdarma"), krok(3, "400.00")]),
    ).toBe("0 Kč / 400 Kč (první dva zdarma)");
  });

  /**
   * Popisy jsou kumulativní, takže u řetězených nároků (2 + 1 zdarma) shrnuje celý nárok
   * až ten poslední. Dřív se věta skládala tady z prvního a tvrdila „první dva" u tří kusů.
   */
  it("u řetězených nároků bere poslední, souhrnný popis", () => {
    const schody = [
      krok(1, "0.00", "dve_tricka_zdarma", "první dva zdarma"),
      krok(3, "0.00", "jedno_tricko_zdarma", "první tři zdarma"),
      krok(4, "400.00"),
    ];

    expect(popisSchodu(schody)).toBe("0 Kč / 0 Kč / 400 Kč (první tři zdarma)");
  });

  it("u částečné slevy neříká zdarma", () => {
    expect(
      popisSchodu([krok(1, "200.00", "pul_ceny", "první se slevou"), krok(2, "400.00")]),
    ).toBe("200 Kč / 400 Kč (první se slevou)");
  });

  /**
   * Neomezená sleva na konci žebříku nese už celé souvětí, takže se tady nic nesklada.
   */
  it("vezme i dovětek o dalších kusech", () => {
    expect(
      popisSchodu([
        krok(1, "0.00", "zdarma", "první zdarma"),
        krok(2, "300.00", "ctvrtina", "první zdarma, další se slevou"),
      ]),
    ).toBe("0 Kč / 300 Kč (první zdarma, další se slevou)");
  });

  it("bez popisu ze serveru závorku nevymýšlí", () => {
    expect(popisSchodu([krok(1, "0.00", "zdarma"), krok(2, "30.00")])).toBeNull();
  });
});

describe("cenaDalsihoKusu", () => {
  it("bere první stupeň, protože server žebřík už posunul", () => {
    expect(
      cenaDalsihoKusu([krok(1, "0.00", "zdarma", "první zdarma"), krok(2, "30.00")]),
    ).toBe("0.00");
  });

  it("u prázdného žebříku vrátí null", () => {
    expect(cenaDalsihoKusu([])).toBeNull();
  });
});
