import { describe, expect, it } from "vitest";
import { nadpisProdeje } from "./nadpisProdeje";

describe("nadpisProdeje", () => {
  it("bez pracovního uživatele je prodej anonymní", () => {
    expect(nadpisProdeje(null)).toBe("Anonymní prodej");
  });

  it("s pracovním uživatelem jmenuje toho, komu se prodává, i s ID", () => {
    expect(nadpisProdeje({ id: 53, nazev: "Petr „Gandalf“ Novák" })).toBe(
      "Prodej uživateli Petr „Gandalf“ Novák (ID 53)",
    );
  });

  it("uživatele bez jména pozná aspoň podle ID", () => {
    expect(nadpisProdeje({ id: 53, nazev: "" })).toBe("Prodej uživateli ID 53");
  });
});
