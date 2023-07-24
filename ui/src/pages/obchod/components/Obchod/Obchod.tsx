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
} from "../../../../api/obchod/types";
import { PředmětyContext } from "../../App";
import { fetchProdej } from "../../../../api/obchod/endpoints";

/** Ruční optimalizace, nedoporučuju používat pokud neznáš dobře react! */
const useFixed = <T,>(value: T) => useRef(value).current;

const usePředmětyObjednávka = () => {
  const [předmětyObjednávka, setPředmětyObjednávka] = useState<
    ObjednávkaPředmět[]
  >([]);

  const předmětyVšechny = useContext(PředmětyContext);

  // TODO: nějak eskalovat když předmět není nalezen
  const předmětPřidej = useFixed((předmětId: number) => {
    setPředmětyObjednávka((předměty) =>
      předměty.some((x) => x.předmět.id === předmětId)
        ? předměty.map((x) =>
          x.předmět.id === předmětId ? { ...x, množství: x.množství + 1 } : x
        )
        : předmětyVšechny.some((x) => x.id === předmětId)
          ? předměty.concat([
            {
              množství: 1,
              předmět: předmětyVšechny.find((x) => x.id === předmětId)!,
            },
          ])
          : předměty
    );
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
  const předmětySmažVšechny = useFixed(() => {
    setPředmětyObjednávka([]);
  });

  return {
    předmětyObjednávka,
    předmětPřidej,
    předmětOdeber,
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

  const setShrnutí = useFixed(() => { setId(-1); });

  const setVýchozí = useCallback(() => { setId(výchozíMřížka); }, []);

  const setMřížka = useMemo(
    () =>
      Object.assign((id: number) => { setId(id); }, {
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

  const [visible, setVisible] = useState(GAMECON_KONSTANTY.IS_DEV_SERVER);

  const {
    předmětyObjednávka: předměty,
    předmětPřidej,
    předmětOdeber,
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
      case "předmět":
        setMřížka.shrnutí();
        předmětPřidej(buňka.cilId);
        break;
    }
  }, []);

  const onDalšíPředmět = useCallback(() => {
    setMřížka.výchozí();
  }, []);

  const onStorno = useCallback(() => {
    předmětySmažVšechny();
    setMřížka.výchozí();
    setVisible(false);
  }, []);

  const onPotvrdit = useCallback(async () => {
    await fetchProdej(předměty);
    předmětySmažVšechny();
    setMřížka.výchozí();
    setVisible(false);
  }, [předměty]);

  return (
    <>
      {visible ? (
        <Overlay onClickOutside={() => { setVisible(false); }}>
          <div class="shop--container">
            <span class="shop--close" title='zavřít' aria-label='close' onClick={() => { setVisible(false); }}>&times;</span>
            {mřížka ? (
              <ObchodMřížka {...{ mřížka, onBuňkaClicked }} />
            ) : (
              <ObchodShrnutí
                {...{
                  předmětyObjednávka: předměty,
                  onDalšíPředmět,
                  onStorno,
                  předmětPřidej,
                  předmětOdeber,
                  onPotvrdit,
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
