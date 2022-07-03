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

/** Ruční optimalizace, nedoporučuju používat pokud neznáš dobře react! */
const useFixed = <T,>(value: T) => useRef(value).current;

const usePředmětyObjednávka = () => {
  const [předmětyObjednávka, setPředmětyObjednávka] = useState<
    ObjednávkaPředmět[]
  >([]);

  const předmětyVšechny = useContext(PředmětyContext);

  const předmětPřidej = useFixed((předmětId: number) => {
    setPředmětyObjednávka((předměty) =>
      předměty.some((x) => x.předmět.id === předmětId)
        ? předměty.map((x) =>
            x.předmět.id === předmětId ? { ...x, množství: x.množství + 1 } : x
          )
        : předměty.concat([{ množství: 1, předmět: předmětyVšechny.find(x=>x.id === předmětId)! }])
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

const výchoZíMřížka = 1;

const useMřižka = (definice: DefiniceObchod ) => {
  const [mřížkaId, _setMřížkaId] = useState(1);
  const [mřížkaIdHist, setMřížkaIdHist] = useState<number[]>([1]);
  const setId = (id: number) => {
    setMřížkaIdHist((x) => [id, ...x]);
    _setMřížkaId(id);
  };
  const setZpět = () => {
    const last = mřížkaIdHist[0] ?? 0;
    setMřížkaIdHist((x) => x.slice(1));
    setId(last);
  };

  const mřížka: DefiniceObchodMřížka | undefined = definice.mřížky.find(
    (x) => x.id === mřížkaId
  );

  const setShrnutí = useFixed(() => setId(-1));

  const setVýchozí = useCallback(() => setId(výchoZíMřížka), []);

  const setMřížka = useMemo(
    () => ({
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
  definice: DefiniceObchod 
};

export const Obchod: FunctionComponent<TObchodProps> = (props) => {
  const {definice} = props;

  const [visible, setVisible] = useState(GAMECON_KONSTANTY.IS_DEV_SERVER);

  const {
    předmětyObjednávka: předměty,
    předmětPřidej,
    předmětOdeber,
    předmětySmažVšechny,
  } = usePředmětyObjednávka();
  const { mřížka, setMřížka } = useMřižka(definice);

  useEffect(() => {
    window.preactMost.obchod.show = () => {
      setVisible(true);
    };
  }, []);

  const onBuňkaClicked = useCallback((buňka: DefiniceObchodMřížkaBuňka) => {
    switch (buňka.typ) {
      case "stránka":
        setMřížka.id(buňka.cilId);
        break;
      case "zpět":
        setMřížka.zpět();
        break;
      case "předmět":
        setMřížka.id(0);
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

  return (
    <>
      {visible ? (
        <Overlay>
          <div class="shop--container">
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
