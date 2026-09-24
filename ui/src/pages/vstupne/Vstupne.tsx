import { FunctionComponent } from "preact";
import { useCallback, useEffect, useMemo, useRef, useState } from "preact/hooks";
import { fetchEntryFee, setEntryFee } from "../../api/symfony/endpoints";
import { ApiEntryFee } from "../../api/symfony/types";
import { GAMECON_KONSTANTY } from "../../env";

/** Smileys shown inside the amount field, from the most generous down. */
const SMILEYS: [number, string][] = [
  [1000, "6.png"],
  [600, "5.png"],
  [250, "4.png"],
  [60, "3.png"],
  [1, "2.png"],
  [0, "1.png"],
];

const smileyForAmount = (amount: number): string => {
  const [, file] = SMILEYS.find(([from]) => amount >= from) ?? SMILEYS[SMILEYS.length - 1];
  return `url(${GAMECON_KONSTANTY.BASE_PATH_PAGE}soubory/blackarrow/shop/vstupne-smajliky/${file})`;
};

const clamp = (value: number, min: number, max: number) => Math.min(Math.max(value, min), max);

/**
 * Saving is deferred so that dragging the slider does not fire a request per pixel.
 */
const SAVE_DELAY_MS = 500;

export const Vstupne: FunctionComponent = () => {
  const [state, setState] = useState<ApiEntryFee | null>(null);
  const [amount, setAmount] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const deferredSave = useRef<number | undefined>(undefined);
  const pendingAmount = useRef<number | null>(null);
  const lastRequest = useRef(0);

  useEffect(() => {
    let valid = true;
    fetchEntryFee()
      .then((entryFee) => {
        if (!valid) return;
        setState(entryFee);
        setAmount(Math.round(Number(entryFee.amount)));
      })
      .catch((error: unknown) => {
        if (!valid) return;
        setError(error instanceof Error ? error.message : "Vstupné se nepodařilo načíst");
      });
    return () => {
      valid = false;
    };
  }, []);

  const send = useCallback((newAmount: number) => {
    pendingAmount.current = null;
    const sequence = ++lastRequest.current;
    setEntryFee(newAmount)
      .then((entryFee) => {
        // An older response may overwrite the state only if no newer request has been sent
        // meanwhile, otherwise an already invalid amount would be displayed.
        if (sequence !== lastRequest.current) return;
        setState(entryFee);
        setError(null);
      })
      .catch((error: unknown) => {
        if (sequence !== lastRequest.current) return;
        setError(error instanceof Error ? error.message : "Vstupné se nepodařilo uložit");
      });
  }, []);

  const save = useCallback((newAmount: number) => {
    pendingAmount.current = newAmount;
    window.clearTimeout(deferredSave.current);
    deferredSave.current = window.setTimeout(() => send(newAmount), SAVE_DELAY_MS);
  }, [send]);

  // The registration form is submitted right next to this, so leaving the page routinely falls
  // within the delay; flush a half-typed amount on unmount instead of discarding it.
  useEffect(() => () => {
    window.clearTimeout(deferredSave.current);
    if (pendingAmount.current !== null) {
      void send(pendingAmount.current);
    }
  }, [send]);

  const changeAmount = useCallback((newAmount: number) => {
    setAmount(newAmount);
    save(newAmount);
  }, [save]);

  const ratio = useMemo(() => {
    if (!state) return 0;
    return clamp(amount / state.maximum, 0, 1) ** state.gammaCorrection;
  }, [amount, state]);

  if (error && !state) {
    return <p class="shopVstupne_chyba">{error}</p>;
  }

  if (!state) {
    return <p>Načítá se …</p>;
  }

  const percent = Math.round(ratio * 100);

  return (
    <>
      <div class="shopVstupne_castkaRadek">
        <div class="shopVstupne_castka">ČÁSTKA:</div>
        <input
          type="text"
          class="shopVstupne_stav"
          value={amount}
          style={{ backgroundImage: smileyForAmount(amount) }}
          onChange={(event) => {
            const entered = Number.parseInt(event.currentTarget.value, 10);
            changeAmount(clamp(Number.isNaN(entered) ? 0 : entered, state.minimum, state.maximumAmount));
          }}
        />
        <div class="shopVstupne_kc">Kč</div>
      </div>

      <div class="shopVstupne_kostkaObal">
        <div
          class="shopVstupne_kostkaPosuv"
          style={{
            background:
              `linear-gradient(to right, #E22630, #E22630 ${percent}%, #737373 ${percent}%)`,
          }}
        />
        {state.lastYearAveragePercent >= 0 && (
          <div class="shopVstupne_kostka" style={{ left: `${state.lastYearAveragePercent}%` }} />
        )}
      </div>

      <input
        type="range"
        class="shopVstupne_range"
        min={0}
        max={1}
        step="any"
        value={ratio}
        onInput={(event) => {
          const newRatio = clamp(Number(event.currentTarget.value), 0, 1);
          changeAmount(Math.round(newRatio ** (1 / state.gammaCorrection) * state.maximum));
        }}
      />

      <div class="shopVstupne_skala">
        <div class="shopVstupne_skalaHodnota">0&thinsp;Kč</div>
        <div class="shopVstupne_skalaDelic" />
        <div class="shopVstupne_skalaHodnota">60&thinsp;Kč</div>
        <div class="shopVstupne_skalaDelic" />
        <div class="shopVstupne_skalaHodnota">250&thinsp;Kč</div>
        <div class="shopVstupne_skalaDelic" />
        <div class="shopVstupne_skalaHodnota">600&thinsp;Kč</div>
        <div class="shopVstupne_skalaDelic" />
        <div class="shopVstupne_skalaHodnota">1000&thinsp;Kč</div>
      </div>

      {state.lastYearAveragePercent >= 0 && (
        <div class="shopVstupne_kostkaLegenda">
          průměrný příspěvek z roku {state.lastYear}
        </div>
      )}

      {error && <p class="shopVstupne_chyba">{error}</p>}
    </>
  );
};
