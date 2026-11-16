import { render } from "preact";
import { act } from "preact/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { SaveStatus } from "./SaveStatus";
import { SAVED_VISIBLE_MS, useSaveState } from "./useSaveState";

type Ovladani = ReturnType<typeof useSaveState>;

let ovladani: Ovladani;
let kontejner: HTMLElement;

const Obrazovka = () => {
  ovladani = useSaveState();

  return <SaveStatus state={ovladani.state} />;
};

const text = () => kontejner.querySelector(".save-status")?.textContent;

beforeEach(async () => {
  vi.useFakeTimers();
  kontejner = document.createElement("div");
  document.body.append(kontejner);
  await act(() => {
    render(<Obrazovka />, kontejner);
  });
});

afterEach(() => {
  render(null, kontejner);
  kontejner.remove();
  vi.useRealTimers();
});

describe("SaveStatus", () => {
  it("bez zprávy je řádek prázdný, ale v stránce, takže rozložení neposkočí", () => {
    expect(kontejner.querySelector(".save-status")).not.toBeNull();
    expect(text()).toBe("");
  });

  it("projde čekáním, ukládáním a uložením", async () => {
    await act(() => {
      ovladani.markPending("Vyber ještě 1 noc.");
    });
    expect(text()).toBe("● Neuloženo — Vyber ještě 1 noc.");

    await act(() => {
      ovladani.markSaving();
    });
    expect(text()).toBe("Ukládám…");

    await act(() => {
      ovladani.markSaved();
    });
    expect(text()).toBe("✓ Uloženo");
  });

  it("potvrzení po chvíli zmizí", async () => {
    await act(() => {
      ovladani.markSaved();
    });

    await act(() => {
      vi.advanceTimersByTime(SAVED_VISIBLE_MS - 1);
    });
    expect(text()).toBe("✓ Uloženo");

    await act(() => {
      vi.advanceTimersByTime(1);
    });
    expect(text()).toBe("");
  });

  it("nová zpráva zruší doznívající potvrzení, aby nepřemazalo tu novou", async () => {
    await act(() => {
      ovladani.markSaved();
    });
    await act(() => {
      ovladani.markSaving();
    });

    await act(() => {
      vi.advanceTimersByTime(SAVED_VISIBLE_MS * 2);
    });

    expect(text()).toBe("Ukládám…");
  });

  it("selhání zůstane, dokud se nic nezmění", async () => {
    await act(() => {
      ovladani.markFailed();
    });

    await act(() => {
      vi.advanceTimersByTime(SAVED_VISIBLE_MS * 2);
    });

    expect(text()).toBe("Uložení se nepodařilo");
  });
});
