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
        {mřížka.buňky.map((buňka, i) => {
          const předmět =
            buňka.typ === "předmět"
              ? všechnyPředměty.find((y) => y.id === buňka.cilId)
              : undefined;

          const text = !buňka.text && předmět ? předmět.název : buňka.text;
          const cena = předmět?.cena ? předmět.cena + "Kč" : "";
          const soldOut = předmět !== undefined && předmět.zbývá !== null && předmět.zbývá <= 0;
          const kusů = předmět?.zbývá != null
            ? (soldOut ? "(vyprodáno)" : `(${předmět.zbývá})`)
            : "";

          return (
            <div
              onClick={() => !soldOut && onBuňkaClicked?.(buňka)}
              class={`shop-grid--item shop-grid--item-${i} ${soldOut ? "shop-grid--item-sold-out" : ""}`}
              style={buňka.barvaPozadí ? { backgroundColor: buňka.barvaPozadí } : ""}
            >
              <div style={{color:buňka.barvaText ?? "#000000"}} class="shop-grid--item-text">
                <div>{text}</div>
                <div>{cena} {kusů}</div>
              </div>
            </div>
          );
        })}
      </div>
    </>
  );
};

ObchodMřížka.displayName = "ObchodMřížka";
