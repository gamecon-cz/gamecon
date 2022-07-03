import { FunctionComponent, options } from "preact";
import { useContext } from "preact/hooks";
import {
  DefiniceObchodMřížkaBuňka,
  DefiniceObchodMřížkaBuňkaTyp,
} from "../../../api/obchod/types";
import { getEnumNames, getEnumValues } from "../../../utils";
import { CíleContext } from "../app";

type TEditorBuňkyProps = {
  buňka: DefiniceObchodMřížkaBuňka;
  setBuňka: (buňka: DefiniceObchodMřížkaBuňka) => void;
};

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
            onChange={(e: any) => {
              setBuňka({ ...buňka, text: e.target.value });
            }}
          ></input>
        </div>
        <div>
          <select
            value={buňka.typ}
            onChange={(e: any) => {
              setBuňka({ ...buňka, typ: e.target.value });
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
            value={buňka.barvaPozadí || "#ffffff"}
            onChange={(e: any) =>
              setBuňka({ ...buňka, barvaPozadí: e.target.value })
            }
          ></input>
        </div>
        {buňka.typ === "předmět" || buňka.typ === "stránka" ? (
          <select
            style={{ width: "100%" }}
            value={buňka.cilId}
            onChange={(e: any) => setBuňka({ ...buňka, cilId: e.target.value })}
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
