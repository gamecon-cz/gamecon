import { FunctionComponent } from "preact";
import { DefiniceObchodMřížka, DefiniceObchodMřížkaBuňka } from "../../../../api/obchod/types";
import "./ObchodMřížka.less";

type TObchodMřížkaProps = {
  onBuňkaClicked?: (buňka: DefiniceObchodMřížkaBuňka) => void;
  mřížka: DefiniceObchodMřížka;
};

export const ObchodMřížka: FunctionComponent<TObchodMřížkaProps> = (props) => {
  const { onBuňkaClicked, mřížka: mřížka } = props;

  return (
    <>
      <div class="shop-grid--container">
        {mřížka.buňky.map((x, i) => {
          return (
            <div
              onClick={() => onBuňkaClicked?.(x)}
              class={`shop-grid--item shop-grid--item-${i}`}
              style={x.barvaPozadí ? {backgroundColor: x.barvaPozadí } : ""}
            >
              <div>{x.typ === "předmět" ? x.předmět.text : x.text}</div>
            </div>
          );
        })}
      </div>
    </>
  );
};

ObchodMřížka.displayName = "ObchodMřížka";
