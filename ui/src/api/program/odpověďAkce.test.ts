import { describe, it, expect, vi, afterEach } from "vitest";
import { ChybaApi, fetchAktivitaTým, odpověďAkce } from "./index";

// Admin API odpoví nepřihlášenému 401 a účastníkovi bez práv 403 s tělem {error}, bez `chyba` i `úspěch`.
// Program by takovou odpověď vzal za úspěch (hlídá jen chyba.hláška) a akce by tiše „prošla“; načtení týmu by zase
// vypadalo, že uživatel tým nemá.

type Odpověď = {úspěch: boolean, chyba?: {hláška: string}};

const json = (tělo: unknown, status: number) =>
  new Response(JSON.stringify(tělo), {status, headers: {"Content-Type": "application/json"}});

describe("odpověďAkce", () => {
  it("úspěšnou odpověď vrátí, jak přišla", async () => {
    expect(await odpověďAkce<Odpověď>(json({úspěch: true}, 200))).toEqual({úspěch: true});
  });

  it("chybu akce z těla nechá", async () => {
    const chyba = {úspěch: false, chyba: {hláška: "Aktivita je plná"}};
    expect(await odpověďAkce<Odpověď>(json(chyba, 200))).toEqual(chyba);
  });

  it("401 po vypršení přihlášení je neúspěch s hláškou", async () => {
    const odpověď = await odpověďAkce<Odpověď>(json({error: "401 Unauthorized"}, 401));
    expect(odpověď.úspěch).toBe(false);
    expect(odpověď.chyba?.hláška).toMatch(/přihlas se/);
  });

  it("403 je neúspěch s hláškou o oprávnění", async () => {
    const odpověď = await odpověďAkce<Odpověď>(json({error: "403 Forbidden"}, 403));
    expect(odpověď.úspěch).toBe(false);
    expect(odpověď.chyba?.hláška).toMatch(/oprávnění/);
  });

  it("chybu serveru bez JSON těla nečte a vrátí neúspěch", async () => {
    const odpověď = await odpověďAkce<Odpověď>(new Response("<html>500</html>", {status: 500}));
    expect(odpověď.úspěch).toBe(false);
    expect(odpověď.chyba?.hláška).toContain("500");
  });

  it("prázdné tělo se statusem 200 je neúspěch s hláškou, ne výjimka", async () => {
    const odpověď = await odpověďAkce<Odpověď>(new Response("", {status: 200}));
    expect(odpověď.úspěch).toBe(false);
    expect(odpověď.chyba?.hláška).toMatch(/obnov/);
  });
});

describe("fetchAktivitaTým", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  const odpovíS = (odpověď: Response) => vi.stubGlobal("fetch", vi.fn().mockResolvedValue(odpověď));

  it("vrátí tým z úspěšné odpovědi", async () => {
    const data = {tym: {id: 7, idTurnajeNeboAktivity: 11}, vsechnyTymy: [], idTurnajeNeboAktivity: 11};
    odpovíS(json(data, 200));
    expect(await fetchAktivitaTým(1)).toEqual(data);
  });

  it("401 nevezme za odpověď bez týmu, vyhodí chybu s hláškou o přihlášení", async () => {
    odpovíS(json({error: "401 Unauthorized"}, 401));
    const chyba = await fetchAktivitaTým(1).catch((e: unknown) => e);
    expect(chyba).toBeInstanceOf(ChybaApi);
    expect((chyba as ChybaApi).message).toMatch(/přihlas se/);
  });

  it("u neexistující aktivity vezme hlášku z těla", async () => {
    odpovíS(json({chyba: {hláška: "Aktivita nenalezena"}}, 404));
    await expect(fetchAktivitaTým(999999)).rejects.toThrow("Aktivita nenalezena");
  });
});
