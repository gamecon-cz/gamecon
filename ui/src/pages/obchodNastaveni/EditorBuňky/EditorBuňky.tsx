import { FunctionComponent } from "preact";
import { useContext } from "preact/hooks";
import {
  DefiniceObchodMřížkaBuňka,
  DefiniceObchodMřížkaBuňkaTyp,
} from "../../../api/obchod/types";
import { getEnumNames } from "../../../utils";
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
            style={{backgroundColor:"transparent", color: buňka.barvaText ?? "#000000"}}
            onChange={(e) => {
              setBuňka({ ...buňka, text: e.currentTarget.value });
            }}
          ></input>
        </div>
        <div>
          <select
            value={buňka.typ}
            onChange={(e) => {
              const typ = e.currentTarget.value as any;
              setBuňka({ ...buňka, typ });
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
            onChange={(e) => {
              setBuňka({ ...buňka, barvaPozadí: e.currentTarget.value });
            }}
          ></input>
        </div>
        <div>
          <input
            type="color"
            value={buňka.barvaText ?? "#000000"}
            onChange={(e) => {
              setBuňka({ ...buňka, barvaText: e.currentTarget.value });
            }}
          ></input>
        </div>
        {buňka.typ === "předmět" || buňka.typ === "stránka" ? (
          <select
            style={{ width: "100%" }}
            value={buňka.cilId}
            onChange={(e) => {
              const cilId = (e.currentTarget.value as any) as number;
              setBuňka({ ...buňka, cilId });
            }}
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
