import { SaveState } from "./useSaveState";
import "./SaveStatus.less";

const TEXTY: Record<Exclude<SaveState["kind"], "idle">, string> = {
  pending: "● Neuloženo",
  saving: "Ukládám…",
  saved: "✓ Uloženo",
  failed: "Uložení se nepodařilo",
};

/**
 * One line for a screen that saves on every click. It is always in the page, empty when there is
 * nothing to say, so the layout does not jump when a message comes and goes.
 */
export function SaveStatus({ state }: { state: SaveState }) {
  const text = state.kind === "idle" ? "" : TEXTY[state.kind];
  const duvod = state.kind === "pending" && state.reason ? ` — ${state.reason}` : "";

  return (
    <span class={`save-status save-status--${state.kind}`} role="status" aria-live="polite">
      {text}
      {duvod}
    </span>
  );
}
