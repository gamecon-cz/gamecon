import { FunctionComponent } from "preact";
import { Předmět, Varianta } from "../../../../api/obchod/types";
import "./VýběrVarianty.less";

type TVýběrVariantyProps = {
  předmět: Předmět;
  onVybráno: (varianta: Varianta) => void;
  onZpět: () => void;
};

/**
 * Velikost trička nebo noc ubytování. Prodej variantu vyžaduje, jakmile jich má předmět
 * víc — bez výběru by ho odmítl.
 */
export const VýběrVarianty: FunctionComponent<TVýběrVariantyProps> = (props) => {
  const { předmět, onVybráno, onZpět } = props;

  return (
    <div class="variant-picker">
      <div class="variant-picker--header">
        <button type="button" class="variant-picker--back" onClick={onZpět}>&larr; zpět</button>
        <span class="variant-picker--title">{předmět.název}</span>
      </div>

      <div class="variant-picker--items">
        {předmět.varianty.map((varianta) => {
          const vyprodáno = varianta.zbývá !== null && varianta.zbývá <= 0;
          const kusů = varianta.zbývá !== null
            ? (vyprodáno ? "(vyprodáno)" : `(${varianta.zbývá})`)
            : "";

          return (
            <div
              key={varianta.id}
              onClick={() => !vyprodáno && onVybráno(varianta)}
              class={`variant-picker--item ${vyprodáno ? "variant-picker--item-sold-out" : ""}`}
            >
              <div class="variant-picker--item-name">{varianta.název}</div>
              <div class="variant-picker--item-meta">{varianta.cena}Kč {kusů}</div>
            </div>
          );
        })}
      </div>
    </div>
  );
};
