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

          // U víc variant drží počty varianty, takže se sčítají; `zbývá` produktu je NULL
          // a samo o sobě by znamenalo „neomezeně".
          const máVarianty = (předmět?.varianty.length ?? 0) > 1;
          const zbýváCelkem = máVarianty
            ? předmět!.varianty.reduce<number | null>(
              (součet, varianta) =>
                součet === null || varianta.zbývá === null ? null : součet + varianta.zbývá,
              0,
            )
            : předmět?.zbývá ?? null;

          const vyprodáno = předmět !== undefined && zbýváCelkem !== null && zbýváCelkem <= 0;
          // Prodává se vždycky varianta, takže předmět bez variant prodat nejde — na
          // mřížkách je jich 28 z doby, kdy velikosti byly samostatné předměty. Archivní
          // je na starší mřížce taky nakonfigurovaný a taky ho prodej odmítne; bez těchhle
          // dvou by buňka šla kliknout a spadlo by to až na serveru.
          // Jen buňka s předmětem; „shrnutí", „zpět" a odkaz na jinou mřížku nic
          // neprodávají a zašednout nesmí.
          const nelzeProdat = buňka.typ === "předmět" && (
            předmět === undefined
            || předmět.archivní
            || předmět.varianty.length === 0
            || vyprodáno
          );
          const kusů = zbýváCelkem != null
            ? (vyprodáno ? "(vyprodáno)" : `(${zbýváCelkem})`)
            : "";

          return (
            <div
              onClick={() => !nelzeProdat && onBuňkaClicked?.(buňka)}
              class={`shop-grid--item shop-grid--item-${i} ${nelzeProdat ? "shop-grid--item-sold-out" : ""}`}
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
