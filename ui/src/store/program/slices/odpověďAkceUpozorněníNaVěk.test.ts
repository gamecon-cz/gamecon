import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { proveďAkciAktivity } from "./programDataSlice";
import { fetchAktivitaAkce } from "../../../api/program";

// Po odhlášení se dá přihlásit znovu dřív, než dorazí nová data účastníka (načtou se po 2,5 s). Upozornění na věk
// proto musí přijít už v odpovědi na odhlášení (aktivitaAkce.php), jinak by se přihlásil bez něj.

const ROČNÍK = 2026;

// Skutečný store (zustand) potřebuje v testech React, tady stačí jeho setState s immerem.
const store = vi.hoisted(() => {
  const stav = {
    data: { podleRočníku: {} as Record<number, { aktivityPodleId: Record<number, Record<string, unknown>> }> },
    dataStatus: {} as Record<string, unknown>,
  };
  return {
    stav,
    useProgramStore: {
      setState: (změna: (s: typeof stav) => void) => { změna(stav); },
      getState: () => stav,
    },
  };
});
vi.mock("..", () => ({ useProgramStore: store.useProgramStore }));
vi.mock("../../../env", () => ({ GAMECON_KONSTANTY: { ROCNIK: 2026 } }));
vi.mock("./všeobecnéSlice", () => ({ nastavChyba: vi.fn() }));
vi.mock("../../../api/program", () => ({
  fetchAktivitaAkce: vi.fn(),
  fetchManifestFresh: vi.fn(),
  fetchStaticProgramData: vi.fn(),
  fetchUserData: vi.fn(),
}));

const upozornění = {
  nadpis: "Aktivita Larp je od 18 let",
  text: "V den aktivity ti ještě nebude 18. Ozvat se můžeš i ty na larp@gamecon.cz.",
  kontakt: "larp@gamecon.cz",
};

const aktivita = () => store.stav.data.podleRočníku[ROČNÍK].aktivityPodleId[7];

describe("odpověď na akci a upozornění na věk", () => {
  beforeEach(() => {
    // nová data účastníka po 2,5 s test nečeká
    vi.useFakeTimers();
    store.stav.data.podleRočníku[ROČNÍK] = {
      aktivityPodleId: { 7: { id: 7, stavPrihlaseni: "prihlasen", upozorneniNaVek: null } },
    };
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("po odhlášení převezme upozornění z odpovědi", async () => {
    vi.mocked(fetchAktivitaAkce).mockResolvedValue({
      úspěch: true,
      aktivitaUzivatel: { id: 7, stavPrihlaseni: undefined, slevaNasobic: 1, vedu: false, upozorneniNaVek: upozornění },
    } as Awaited<ReturnType<typeof fetchAktivitaAkce>>);

    await proveďAkciAktivity(7, "odhlasit");

    expect(aktivita().upozorneniNaVek).toEqual(upozornění);
  });

  it("po přihlášení upozornění zahodí", async () => {
    aktivita().upozorneniNaVek = upozornění;
    vi.mocked(fetchAktivitaAkce).mockResolvedValue({
      úspěch: true,
      aktivitaUzivatel: { id: 7, stavPrihlaseni: "prihlasen", slevaNasobic: 1, vedu: false, upozorneniNaVek: null },
    } as Awaited<ReturnType<typeof fetchAktivitaAkce>>);

    await proveďAkciAktivity(7, "prihlasit");

    expect(aktivita().upozorneniNaVek).toBeNull();
  });
});
