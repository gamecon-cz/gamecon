import { FunctionComponent } from "preact";
import { ObjednávkaPředmět, Předmět } from "../../../../api/obchod/types";
import "./ObchodShrnutí.less";

type TObchodShrnutíProps = {
  předmětyObjednávka: ObjednávkaPředmět[];
  předmětPřidej: (předmětId: number) => void;
  předmětOdeber: (předmět: Předmět) => void;
  předmětNastavMnožství: (předmětId: number, množství: number) => void;
  onDalšíPředmět?: () => void;
  onStorno?: () => void;
  onPotvrdit?: () => void;
  chyba?: string | null;
};

export const ObchodShrnutí: FunctionComponent<TObchodShrnutíProps> = (props) => {
  const {
    předmětyObjednávka,
    onDalšíPředmět,
    onStorno,
    předmětOdeber,
    předmětPřidej,

  onPotvrdit,
  předmětNastavMnožství,
  chyba} = props;

  const seznam = (
    <div class="shop-summary-list--container">
      {předmětyObjednávka.map((orderItem) => {
        const atStockLimit = orderItem.předmět.zbývá !== null && orderItem.množství >= orderItem.předmět.zbývá;
        return (
          <div class="shop-summary-list--item" key={orderItem.předmět.id}>
            <div class="shop-summary-list--item-text">{orderItem.předmět.název}</div>
            <div class="shop-summary-list--item-buttons">
              <button
                class="shop-summary-list--item-buttons-remove"
                onClick={() => předmětOdeber(orderItem.předmět)}
              >
                -
              </button>
              <input
                class="shop-summary-list--item-buttons-number"
                type="number"
                min={0}
                max={orderItem.předmět.zbývá ?? undefined}
                value={orderItem.množství}
                onChange={(event) => {
                  const parsed = parseInt((event.target as HTMLInputElement).value, 10);
                  if (!isNaN(parsed)) {
                    předmětNastavMnožství(orderItem.předmět.id, parsed);
                  }
                }}
              ></input>
              <button
                class="shop-summary-list--item-buttons-add"
                onClick={() => předmětPřidej(orderItem.předmět.id)}
                disabled={atStockLimit}
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
        <button class="shop-summary--item shop-summary--item-submit"
        onClick={onPotvrdit}>
          Potvrdit
        </button>
        <div class="shop-summary--item shop-summary--item-list">{seznam}</div>
        {chyba && <div class="shop-summary--item shop-summary--error">{chyba}</div>}
      </div>
    </>
  );
};

ObchodShrnutí.displayName = "ObchodShrnutí";
