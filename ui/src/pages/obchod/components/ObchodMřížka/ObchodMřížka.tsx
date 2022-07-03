import { FunctionComponent } from "preact";
import { useContext } from "preact/hooks";
import {
  DefiniceObchodMřížka,
  DefiniceObchodMřížkaBuňka,
  DefiniceObchodMřížkaBuňkaPředmět,
} from "../../../../api/obchod/types";
import { PředmětyContext } from "../../App";
import "./ObchodMřížka.less";

type TObchodMřížkaProps = {
  onBuňkaClicked?: (buňka: DefiniceObchodMřížkaBuňka) => void;
  mřížka: DefiniceObchodMřížka;
};

export const ObchodMřížka: FunctionComponent<TObchodMřížkaProps> = (props) => {
  const { onBuňkaClicked, mřížka: mřížka } = props;

  const všechnyPředměty = useContext(PředmětyContext);

  return (
    <>
      <div class="shop-grid--container">
        {mřížka.buňky.map((x, i) => {
          return (
            <div
              onClick={() => onBuňkaClicked?.(x)}
              class={`shop-grid--item shop-grid--item-${i}`}
              style={x.barvaPozadí ? { backgroundColor: x.barvaPozadí } : ""}
            >
              <div>
                {!x.text && x.typ === "předmět"
                  ? (
                      všechnyPředměty.find(
                        (y) => y.id === x.cilId
                      )
                    )?.název
                  : x.text}
              </div>
            </div>
          );
        })}
      </div>
    </>
  );
};

ObchodMřížka.displayName = "ObchodMřížka";
