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

          const varianta = buňka.typ === "předmět" && buňka.variantId !== undefined
            ? předmět?.varianty.find((x) => x.id === buňka.variantId)
            : undefined;

          const text = !buňka.text && předmět
            ? [předmět.název, varianta?.název].filter(Boolean).join(" ")
            : buňka.text;
          const cenaKs = varianta?.cena ?? předmět?.cena;
          const cena = cenaKs ? cenaKs + "Kč" : "";

          // U víc variant drží počty varianty, takže se sčítají; `zbývá` produktu je NULL
          // a samo o sobě by znamenalo „neomezeně".
          const máVarianty = (předmět?.varianty.length ?? 0) > 1;
          const zbýváCelkem = varianta
            ? varianta.zbývá
            : máVarianty
            ? předmět!.varianty.reduce<number | null>(
              (součet, velikost) =>
                součet === null || velikost.zbývá === null ? null : součet + velikost.zbývá,
              0,
            )
            : předmět?.zbývá ?? null;

          const vyprodáno = předmět !== undefined && zbýváCelkem !== null && zbýváCelkem <= 0;
          // Prodává se vždycky varianta, takže předmět bez variant prodat nejde. Archivní je
          // na starší mřížce taky nakonfigurovaný a taky ho prodej odmítne; bez těchhle dvou
          // by buňka šla kliknout a spadlo by to až na serveru.
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
