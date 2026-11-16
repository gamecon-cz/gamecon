import { useEffect, useRef, useState } from "preact/hooks";

/**
 * Where a change made by a click stands: waiting for something before it can be sent (`pending`,
 * with the reason), on its way, saved, or refused. `idle` shows nothing.
 */
export type SaveState =
  | { kind: "idle" }
  | { kind: "pending"; reason?: string }
  | { kind: "saving" }
  | { kind: "saved" }
  | { kind: "failed" };

/** How long "saved" stays on screen before it gives way to nothing. */
export const SAVED_VISIBLE_MS = 3000;

/**
 * The state behind `<SaveStatus />`, for a screen whose checkboxes save by themselves: the screen
 * says what happens (`markPending`, `markSaving`, `markSaved`, `markFailed`) and the confirmation
 * fades on its own, so every such screen tells the user the same things the same way.
 */
export function useSaveState(savedVisibleMs: number = SAVED_VISIBLE_MS) {
  const [state, setState] = useState<SaveState>({ kind: "idle" });
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  const stopTimer = () => {
    if (timer.current !== undefined) {
      clearTimeout(timer.current);
      timer.current = undefined;
    }
  };
  useEffect(() => stopTimer, []);

  const set = (next: SaveState) => {
    stopTimer();
    setState(next);
  };

  return {
    state,
    markPending: (reason?: string) => {
      set({ kind: "pending", reason });
    },
    markSaving: () => {
      set({ kind: "saving" });
    },
    markSaved: () => {
      set({ kind: "saved" });
      timer.current = setTimeout(() => {
        timer.current = undefined;
        setState({ kind: "idle" });
      }, savedVisibleMs);
    },
    markFailed: () => {
      set({ kind: "failed" });
    },
    markIdle: () => {
      set({ kind: "idle" });
    },
  };
}
