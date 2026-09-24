import { FunctionComponent, render } from "preact";
import {
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "preact/hooks";
import { Overlay } from "../../../../components/Overlay";
import { GAMECON_KONSTANTY } from "../../../../env";
import { ObchodMřížka } from "../ObchodMřížka";
import { ObchodShrnutí } from "../ObchodShrnutí";
import {
  DefiniceObchod,
  DefiniceObchodMřížka,
  DefiniceObchodMřížkaBuňka,
  ObjednávkaPředmět,
  Předmět,
  Varianta,
} from "../../../../api/obchod/types";
import { PředmětyContext } from "../../App";
import { VýběrVarianty } from "../VýběrVarianty/VýběrVarianty";
import { fetchProdej } from "../../../../api/obchod/endpoints";

/** Ruční optimalizace, nedoporučuju používat pokud neznáš dobře react! */
const useFixed = <T,>(value: T) => useRef(value).current;

const usePředmětyObjednávka = () => {
  const [předmětyObjednávka, setPředmětyObjednávka] = useState<
    ObjednávkaPředmět[]
  >([]);

  const předmětyVšechny = useContext(PředmětyContext);

  const předmětPřidej = useFixed((předmětId: number, varianta?: Varianta) => {
    setPředmětyObjednávka((předměty) => {
      const product = předmětyVšechny.find((product) => product.id === předmětId);
      if (!product) return předměty;

      // Varianta se vybírá jen u předmětu, kde je z čeho — jinak se vezme ta jediná, aby
      // se do objednávky dostalo to, co prodej odečte.
      const zvolená = varianta ?? (product.varianty.length === 1 ? product.varianty[0] : undefined);

      const existing = předměty.find(
        (item) => item.předmět.id === předmětId && item.varianta?.id === zvolená?.id
      );

      // Zásoba drží varianta, když ji má; `null` znamená neomezeně.
      const zbývá = zvolená ? zvolená.zbývá : product.zbývá;
      const currentQuantity = existing?.množství ?? 0;
      if (zbývá !== null && currentQuantity >= zbývá) {
        return předměty;
      }

      if (existing) {
        return předměty.map((item) =>
          item === existing ? { ...item, množství: item.množství + 1 } : item
        );
      }

      return předměty.concat([{ množství: 1, předmět: product, varianta: zvolená }]);
    });
  });
  const předmětOdeber = useFixed((předmět: Předmět) => {
    setPředmětyObjednávka((předměty) =>
      předměty
        .map((x) =>
          x.předmět.id === předmět.id ? { ...x, množství: x.množství - 1 } : x
        )
        .filter((x) => x.množství >= 1)
    );
  });
  const předmětNastavMnožství = useFixed((předmětId: number, množství: number) => {
    setPředmětyObjednávka((předměty) => {
      const product = předmětyVšechny.find((product) => product.id === předmětId);
      // Cap at stock limit
      let capped = Math.max(0, množství);
      if (product?.zbývá !== null && product?.zbývá !== undefined) {
        capped = Math.min(capped, product.zbývá);
      }
      return předměty
        .map((item) => item.předmět.id === předmětId ? { ...item, množství: capped } : item)
        .filter((item) => item.množství >= 1);
    });
  });

  const předmětySmažVšechny = useFixed(() => {
    setPředmětyObjednávka([]);
  });

  return {
    předmětyObjednávka,
    předmětPřidej,
    předmětOdeber,
    předmětNastavMnožství,
    předmětySmažVšechny,
  };
};

const výchozíMřížka = 1;

const useMřížka = (definice: DefiniceObchod) => {
  const [mřížkaId, setMřížkaId] = useState(1);
  const [mřížkaIdHist, setMřížkaIdHist] = useState<number[]>([1]);
  const setId = (id: number) => {
    setMřížkaIdHist((x) => [id, ...x]);
    setMřížkaId(id);
  };

  const setZpět = () => {
    let retval = true;
    setMřížkaIdHist((x) => {
      if (x.length == 1) {
        retval = false;
        return x;
      }
      const last = x[1] ?? 1;
      setId(last);
      return x.slice(1);
    });
    return retval;
  };

  const mřížka: DefiniceObchodMřížka | undefined = definice.mřížky.find(
    (x) => x.id === mřížkaId
  );

  const setShrnutí = useFixed(() => setId(-1));

  const setVýchozí = useCallback(() => setId(výchozíMřížka), []);

  const setMřížka = useMemo(
    () =>
      Object.assign((id: number) => setId(id), {
        id: setId,
        zpět: setZpět,
        výchozí: setVýchozí,
        shrnutí: setShrnutí,
      }),
    [setId, setZpět, setVýchozí, setShrnutí]
  );

  return { mřížka, setMřížka };
};

type TObchodProps = {
  definice: DefiniceObchod;
};

export const Obchod: FunctionComponent<TObchodProps> = (props) => {
  const { definice } = props;

  const [visible, setVisible] = useState(false);
  // Předmět, u kterého se právě vybírá varianta; `null` = výběr neběží.
  const [výběrVarianty, setVýběrVarianty] = useState<Předmět | null>(null);
  const předmětyVšechny = useContext(PředmětyContext);

  const {
    předmětyObjednávka: předměty,
    předmětPřidej,
    předmětOdeber,
    předmětNastavMnožství,
    předmětySmažVšechny,
  } = usePředmětyObjednávka();
  const { mřížka, setMřížka } = useMřížka(definice);

  const escFunction = useCallback((e: KeyboardEvent) => {
    if (e.key === "Escape") {
      setVisible(false);
    }
  }, []);

  useEffect(() => {
    window.preactMost.obchod.show = () => {
      setVisible(true);
    };
    document.addEventListener("keydown", escFunction, false);

    return () => {
      document.removeEventListener("keydown", escFunction, false);
    };
  }, []);

  const onBuňkaClicked = useCallback((buňka: DefiniceObchodMřížkaBuňka) => {
    switch (buňka.typ) {
      case "shrnutí":
        setMřížka.shrnutí();
        break;
      case "stránka":
        setMřížka(buňka.cilId);
        break;
      case "zpět":
        if (!setMřížka.zpět())
          setVisible(false);
        break;
      case "předmět": {
        const předmět = předmětyVšechny.find((x) => x.id === buňka.cilId);
        // U víc variant se nejdřív vybírá velikost nebo noc — prodej bez ní neprojde.
        if (předmět && předmět.varianty.length > 1) {
          setVýběrVarianty(předmět);
          break;
        }
        setMřížka.shrnutí();
        předmětPřidej(buňka.cilId);
        break;
      }
    }
  }, [předmětyVšechny]);

  const onDalšíPředmět = useCallback(() => {
    setMřížka.výchozí();
  }, []);

  const onStorno = useCallback(() => {
    předmětySmažVšechny();
    setMřížka.výchozí();
    setVisible(false);
  }, []);

  const [chyba, setChyba] = useState<string | null>(null);

  const onPotvrdit = useCallback(async () => {
    setChyba(null);
    try {
      await fetchProdej(předměty);
      předmětySmažVšechny();
      setMřížka.výchozí();
      setVisible(false);

      // Flash success on the "Prodej" button
      const prodejButton = document.querySelector<HTMLButtonElement>('[onclick*="preactMost.obchod.show"]');
      if (prodejButton) {
        const originalText = prodejButton.textContent;
        const originalBg = prodejButton.style.backgroundColor;
        prodejButton.textContent = "Prodáno \u2713";
        prodejButton.style.backgroundColor = "#4caf50";
        setTimeout(() => {
          prodejButton.textContent = originalText;
          prodejButton.style.backgroundColor = originalBg;
        }, 2000);
      }
    } catch (error: unknown) {
      setChyba(error instanceof Error ? error.message : "Nepodařilo se dokončit prodej");
    }
  }, [předměty]);

  return (
    <>
      {visible ? (
        <Overlay onClickOutside={() => setVisible(false)}>
          <div class="shop--container">
            <span class="shop--close" title='zavřít' aria-label='close' onClick={() => setVisible(false)}>&times;</span>
            {výběrVarianty ? (
              <VýběrVarianty
                předmět={výběrVarianty}
                onVybráno={(varianta) => {
                  setVýběrVarianty(null);
                  setMřížka.shrnutí();
                  předmětPřidej(výběrVarianty.id, varianta);
                }}
                onZpět={() => setVýběrVarianty(null)}
              />
            ) : mřížka ? (
              <ObchodMřížka {...{ mřížka, onBuňkaClicked }} />
            ) : (
              <ObchodShrnutí
                {...{
                  předmětyObjednávka: předměty,
                  onDalšíPředmět,
                  onStorno,
                  předmětPřidej,
                  předmětOdeber,
                  předmětNastavMnožství,
                  onPotvrdit,
                  chyba,
                }}
              />
            )}
          </div>
        </Overlay>
      ) : undefined}
    </>
  );
};

Obchod.displayName = "Obchod";
