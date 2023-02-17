import { FunctionComponent } from "preact";
import { useContext } from "preact/hooks";
import {
  DefiniceObchodMřížkaBuňka} from "../../../api/obchod/types";
import {
  DefiniceObchodMřížkaBuňkaTyp,
} from "../../../api/obchod/types";
import { getEnumNames } from "../../../utils/enum";
import { CíleContext } from "../app";

type TEditorBuňkyProps = {
  buňka: DefiniceObchodMřížkaBuňka;
  setBuňka: (buňka: DefiniceObchodMřížkaBuňka) => void;
}

const typy = getEnumNames(DefiniceObchodMřížkaBuňkaTyp);

export const EditorBuňky: FunctionComponent<TEditorBuňkyProps> = (props) => {
  const { buňka, setBuňka } = props;

  const cíle = useContext(CíleContext);

  return (
    <>
      <div style={{backgroundColor: buňka.barvaPozadí}}>
        <div>
          <input
            value={buňka.text}
            onChange={(e) => {
              setBuňka({ ...buňka, text: (e.target as HTMLInputElement).value });
            }}
          ></input>
        </div>
        <div>
          <select
            value={buňka.typ}
            onChange={(e) => {
              setBuňka({ ...buňka, typ: (e.target as HTMLSelectElement).value as any });
            }}
          >
            {typy.map((x) => (
              <option value={x}>{x}</option>
            ))}
          </select>
        </div>
        <div>
          <input
            type="color"
            value={buňka.barvaPozadí ?? "#ffffff"}
            onChange={(e) =>
            { setBuňka({ ...buňka, barvaPozadí: (e.target as HTMLInputElement).value }); }
            }
          ></input>
        </div>
        {buňka.typ === "předmět" || buňka.typ === "stránka" ? (
          <select
            style={{ width: "100%" }}
            value={buňka.cilId}
            onChange={(e) => { setBuňka({ ...buňka, cilId: +(e.target as HTMLSelectElement).value }); }}
          >
            {cíle[buňka.typ === "předmět" ? "předměty" : "mřížky"].map((x) => (
              <option value={x.id}>{x.text}</option>
            ))}
          </select>
        ) : undefined}
      </div>
    </>
  );
};

EditorBuňky.displayName = "EditorBuňky";
