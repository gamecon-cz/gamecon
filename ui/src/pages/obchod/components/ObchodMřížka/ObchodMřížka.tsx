import { FunctionComponent } from "preact";
import { useContext } from "preact/hooks";
import {
  DefiniceObchodMřížka,
  DefiniceObchodMřížkaBuňka,
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
          const předmět =
            x.typ === "předmět"
              ? všechnyPředměty.find((y) => y.id === x.cilId)
              : undefined;

          const text = !x.text && předmět ? předmět.název : x.text;
          const cena = předmět?.cena ? předmět.cena + "Kč" : "";
          const kusů = předmět?.zbývá ? `(${předmět.zbývá})` : "";

          return (
            <div
              onClick={() => onBuňkaClicked?.(x)}
              class={`shop-grid--item shop-grid--item-${i}`}
              style={x.barvaPozadí ? { backgroundColor: x.barvaPozadí } : ""}
            >
              <div class="shop-grid--item-text">
                <div>{text}</div>
                <div>{cena}{kusů}</div>
              </div>
            </div>
          );
        })}
      </div>
    </>
  );
};

ObchodMřížka.displayName = "ObchodMřížka";
