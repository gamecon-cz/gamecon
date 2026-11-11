import { describe, it, expect, vi, beforeEach } from "vitest";
import { klikniNaPřihlašovátko } from "./kliknutíNaPřihlašovátko";
import { proveďAkciAktivity } from "../../../../store/program/slices/programDataSlice";
import { nastavModalNastaveníTýmu, nastavModalOdhlásit, otevřiUpozorněníNaVěk } from "../../../../store/program/slices/všeobecnéSlice";

// Odkaz v Přihlašovátku: přihlášení na aktivitu pro starší i založení / přidání se k týmu
// projde až potvrzením upozornění na věk. Store (zustand) v testech nejde načíst, proto atrapy akcí.
vi.mock("../../../../store/program/slices/programDataSlice", () => ({
  proveďAkciAktivity: vi.fn(async () => { /* přihlášení se v testu jen zaznamená */ }),
}));
vi.mock("../../../../store/program/slices/všeobecnéSlice", () => ({
  nastavModalNastaveníTýmu: vi.fn(),
  nastavModalOdhlásit: vi.fn(),
  otevřiUpozorněníNaVěk: vi.fn(),
}));

const upozornění = { nadpis: "Aktivita Larp je od 18 let", text: "V den aktivity ti ještě nebude 18." };

/** potvrdí upozornění, které otevřelo poslední kliknutí */
const potvrďUpozornění = () => {
  const poslední = vi.mocked(otevřiUpozorněníNaVěk).mock.lastCall;
  if (!poslední) throw new Error("upozornění se neotevřelo");
  const [, přihlaš] = poslední;
  přihlaš();
};

describe("kliknutí na přihlašovátko", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("bez upozornění přihlásí rovnou", () => {
    klikniNaPřihlašovátko(7, "prihlasit", false, undefined);

    expect(otevřiUpozorněníNaVěk).not.toHaveBeenCalled();
    expect(proveďAkciAktivity).toHaveBeenCalledWith(7, "prihlasit");
  });

  it("s upozorněním přihlásí až po potvrzení", () => {
    klikniNaPřihlašovátko(7, "prihlasit", false, upozornění);

    expect(otevřiUpozorněníNaVěk).toHaveBeenCalledWith(upozornění, expect.any(Function));
    expect(proveďAkciAktivity).not.toHaveBeenCalled();

    potvrďUpozornění();

    expect(proveďAkciAktivity).toHaveBeenCalledWith(7, "prihlasit");
  });

  it("týmová aktivita s upozorněním otevře nastavení týmu až po potvrzení", () => {
    klikniNaPřihlašovátko(7, "prihlasit", true, upozornění);

    expect(otevřiUpozorněníNaVěk).toHaveBeenCalledWith(upozornění, expect.any(Function));
    expect(nastavModalNastaveníTýmu).not.toHaveBeenCalled();

    potvrďUpozornění();

    expect(nastavModalNastaveníTýmu).toHaveBeenCalledWith(7);
    expect(proveďAkciAktivity).not.toHaveBeenCalled();
  });

  it("týmová aktivita bez upozornění otevře nastavení týmu rovnou", () => {
    klikniNaPřihlašovátko(7, "prihlasit", true, undefined);

    expect(otevřiUpozorněníNaVěk).not.toHaveBeenCalled();
    expect(nastavModalNastaveníTýmu).toHaveBeenCalledWith(7);
  });

  it("nastavení týmu, kde už je, ani sledování na věk neupozorní", () => {
    klikniNaPřihlašovátko(7, "tym", true, upozornění);
    klikniNaPřihlašovátko(8, "prihlasSledujiciho", false, upozornění);

    expect(otevřiUpozorněníNaVěk).not.toHaveBeenCalled();
    expect(nastavModalNastaveníTýmu).toHaveBeenCalledWith(7);
    expect(proveďAkciAktivity).toHaveBeenCalledWith(8, "prihlasSledujiciho");
  });

  it("odhlášení otevře potvrzení odhlášení, zamčená aktivita nic", () => {
    klikniNaPřihlašovátko(7, "odhlasit", false, undefined);
    klikniNaPřihlašovátko(8, "zamceno", false, undefined);

    expect(nastavModalOdhlásit).toHaveBeenCalledWith(7);
    expect(proveďAkciAktivity).not.toHaveBeenCalled();
    expect(nastavModalNastaveníTýmu).not.toHaveBeenCalled();
  });
});
