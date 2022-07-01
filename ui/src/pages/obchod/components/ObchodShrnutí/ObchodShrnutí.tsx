import { FunctionComponent } from "preact";
import { ObjednávkaPředmět, Předmět } from "../../types";
import "./ObchodShrnutí.less";

type TObchodShrnutíProps = {
  předmětyObjednávka: ObjednávkaPředmět[];
  předmětPřidej: (předmět: Předmět) => void;
  předmětOdeber: (předmět: Předmět) => void;
  onDalšíPředmět?: () => void;
  onStorno?: () => void;
};

export const ObchodShrnutí: FunctionComponent<TObchodShrnutíProps> = (props) => {
  const {
    předmětyObjednávka,
    onDalšíPředmět,
    onStorno,
    předmětOdeber,
    předmětPřidej,
  } = props;

  const seznam = (
    <div class="shop-summary-list--container">
      {předmětyObjednávka.map((x) => {
        return (
          <div class="shop-summary-list--item" key={x.předmět.id}>
            <div class="shop-summary-list--item-text">{x.předmět.text}</div>
            <div class="shop-summary-list--item-buttons">
              <button
                class="shop-summary-list--item-buttons-remove"
                onClick={() => předmětOdeber(x.předmět)}
              >
                -
              </button>
              <input
                class="shop-summary-list--item-buttons-number"
                value={x.množství}
              ></input>
              <button
                class="shop-summary-list--item-buttons-add"
                onClick={() => předmětPřidej(x.předmět)}
              >
                +
              </button>
            </div>
          </div>
        );
      })}
    </div>
  );

  return (
    <>
      <div class="shop-summary--container">
        <button
          class="shop-summary--item shop-summary--item-add"
          onClick={onDalšíPředmět}
        >
          Přidat předmět
        </button>
        <button
          class="shop-summary--item shop-summary--item-storno"
          onClick={onStorno}
        >
          Storno!
        </button>
        <button class="shop-summary--item shop-summary--item-submit">
          Potvrdit
        </button>
        <div class="shop-summary--item shop-summary--item-list">{seznam}</div>
      </div>
    </>
  );
};

ObchodShrnutí.displayName = "ObchodShrnutí";
