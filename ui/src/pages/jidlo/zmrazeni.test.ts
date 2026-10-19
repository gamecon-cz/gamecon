import { describe, expect, it } from "vitest";
import { objednavkyZmrazeny } from "./zmrazeni";

const jidlo = (locked: boolean) => ({ locked });

describe("objednavkyZmrazeny", () => {
  it("po termínu, kdy je zamčené každé jídlo, hlásí zmrazení", () => {
    expect(objednavkyZmrazeny([jidlo(true), jidlo(true)])).toBe(true);
  });

  it("dokud jde aspoň jedno jídlo změnit, zmrazené není", () => {
    expect(objednavkyZmrazeny([jidlo(true), jidlo(false)])).toBe(false);
  });

  it("bez jídel nehlásí nic, prázdnou nabídku matice vysvětluje sama", () => {
    expect(objednavkyZmrazeny([])).toBe(false);
  });
});
