import { FunctionComponent } from "preact";
import { useCallback, useEffect, useMemo, useRef, useState } from "preact/hooks";
import { fetchEntryFee, setEntryFee } from "../../api/symfony/endpoints";
import { ApiEntryFee } from "../../api/symfony/types";
import { GAMECON_KONSTANTY } from "../../env";

/** Smileys shown inside the amount field, from the most generous down. */
const SMAJLÍKY: [number, string][] = [
  [1000, "6.png"],
  [600, "5.png"],
  [250, "4.png"],
  [60, "3.png"],
  [1, "2.png"],
  [0, "1.png"],
];

const smajlíkProČástku = (částka: number): string => {
  const [, soubor] = SMAJLÍKY.find(([od]) => částka >= od) ?? SMAJLÍKY[SMAJLÍKY.length - 1];
  return `url(${GAMECON_KONSTANTY.BASE_PATH_PAGE}soubory/blackarrow/shop/vstupne-smajliky/${soubor})`;
};

const omez = (číslo: number, min: number, max: number) => Math.min(Math.max(číslo, min), max);

/**
 * Odeslání se odkládá, aby tažení posuvníkem neposílalo request na každý pixel.
 */
const PRODLEVA_ULOŽENÍ_MS = 500;

export const Vstupne: FunctionComponent = () => {
  const [stav, setStav] = useState<ApiEntryFee | null>(null);
  const [částka, setČástka] = useState(0);
  const [chyba, setChyba] = useState<string | null>(null);
  const odloženéUložení = useRef<number | undefined>(undefined);
  const čekajícíČástka = useRef<number | null>(null);
  const posledníPožadavek = useRef(0);

  useEffect(() => {
    let platné = true;
    fetchEntryFee()
      .then((entryFee) => {
        if (!platné) return;
        setStav(entryFee);
        setČástka(Math.round(Number(entryFee.amount)));
      })
      .catch((error: unknown) => {
        if (!platné) return;
        setChyba(error instanceof Error ? error.message : "Vstupné se nepodařilo načíst");
      });
    return () => {
      platné = false;
    };
  }, []);

  const odešli = useCallback((nováČástka: number) => {
    čekajícíČástka.current = null;
    const pořadí = ++posledníPožadavek.current;
    setEntryFee(nováČástka)
      .then((entryFee) => {
        // Starší odpověď smí přepsat stav jen tehdy, když mezitím nepřišel novější požadavek —
        // jinak by se zobrazila už neplatná částka.
        if (pořadí !== posledníPožadavek.current) return;
        setStav(entryFee);
        setChyba(null);
      })
      .catch((error: unknown) => {
        if (pořadí !== posledníPožadavek.current) return;
        setChyba(error instanceof Error ? error.message : "Vstupné se nepodařilo uložit");
      });
  }, []);

  const ulož = useCallback((nováČástka: number) => {
    čekajícíČástka.current = nováČástka;
    window.clearTimeout(odloženéUložení.current);
    odloženéUložení.current = window.setTimeout(() => odešli(nováČástka), PRODLEVA_ULOŽENÍ_MS);
  }, [odešli]);

  // Formulář přihlášky se odesílá hned vedle, takže odchod ze stránky spadne běžně do prodlevy;
  // rozepsanou částku proto při odmountování ještě odešleme, místo abychom ji zahodili.
  useEffect(() => () => {
    window.clearTimeout(odloženéUložení.current);
    if (čekajícíČástka.current !== null) {
      void odešli(čekajícíČástka.current);
    }
  }, [odešli]);

  const změňČástku = useCallback((nováČástka: number) => {
    setČástka(nováČástka);
    ulož(nováČástka);
  }, [ulož]);

  const poměr = useMemo(() => {
    if (!stav) return 0;
    return omez(částka / stav.maximum, 0, 1) ** stav.gammaCorrection;
  }, [částka, stav]);

  if (chyba && !stav) {
    return <p class="shopVstupne_chyba">{chyba}</p>;
  }

  if (!stav) {
    return <p>Načítá se …</p>;
  }

  const procento = Math.round(poměr * 100);

  return (
    <>
      <div class="shopVstupne_castkaRadek">
        <div class="shopVstupne_castka">ČÁSTKA:</div>
        <input
          type="text"
          class="shopVstupne_stav"
          value={částka}
          style={{ backgroundImage: smajlíkProČástku(částka) }}
          onChange={(event) => {
            const zadané = Number.parseInt(event.currentTarget.value, 10);
            změňČástku(omez(Number.isNaN(zadané) ? 0 : zadané, stav.minimum, stav.maximumAmount));
          }}
        />
        <div class="shopVstupne_kc">Kč</div>
      </div>

      <div class="shopVstupne_kostkaObal">
        <div
          class="shopVstupne_kostkaPosuv"
          style={{
            background:
              `linear-gradient(to right, #E22630, #E22630 ${procento}%, #737373 ${procento}%)`,
          }}
        />
        {stav.lastYearAveragePercent >= 0 && (
          <div class="shopVstupne_kostka" style={{ left: `${stav.lastYearAveragePercent}%` }} />
        )}
      </div>

      <input
        type="range"
        class="shopVstupne_range"
        min={0}
        max={1}
        step="any"
        value={poměr}
        onInput={(event) => {
          const novýPoměr = omez(Number(event.currentTarget.value), 0, 1);
          změňČástku(Math.round(novýPoměr ** (1 / stav.gammaCorrection) * stav.maximum));
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

      {stav.lastYearAveragePercent >= 0 && (
        <div class="shopVstupne_kostkaLegenda">
          průměrný příspěvek z roku {stav.lastYear}
        </div>
      )}

      {chyba && <p class="shopVstupne_chyba">{chyba}</p>}
    </>
  );
};
