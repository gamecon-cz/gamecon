import { FunctionComponent } from "preact";
import { ObjednávkaPředmět, Předmět } from "../../../../api/obchod/types";
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
    <div class="obchod-shrnuti-seznam--container">
      {předmětyObjednávka.map((x) => {
        return (
          <div class="obchod-shrnuti-seznam--item" key={x.předmět.id}>
            <div class="obchod-shrnuti-seznam--item-text">{x.předmět.název}</div>
            <div class="obchod-shrnuti-seznam--item-buttons">
              <button
                class="obchod-shrnuti-seznam--item-buttons-remove"
                onClick={() => předmětOdeber(x.předmět)}
              >
                -
              </button>
              <input
                class="obchod-shrnuti-seznam--item-buttons-number"
                value={x.množství}
              ></input>
              <button
                class="obchod-shrnuti-seznam--item-buttons-add"
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
      <div class="obchod-shrnuti--container">
        <button
          class="obchod-shrnuti--item obchod-shrnuti--item-pridat"
          onClick={onDalšíPředmět}
        >
          Přidat předmět
        </button>
        <button
          class="obchod-shrnuti--item obchod-shrnuti--item-storno"
          onClick={onStorno}
        >
          Storno!
        </button>
        <button class="obchod-shrnuti--item obchod-shrnuti--item-potvrdit">
          Potvrdit
        </button>
        <div class="obchod-shrnuti--item obchod-shrnuti--item-seznam">{seznam}</div>
      </div>
    </>
  );
};

ObchodShrnutí.displayName = "ObchodShrnutí";
