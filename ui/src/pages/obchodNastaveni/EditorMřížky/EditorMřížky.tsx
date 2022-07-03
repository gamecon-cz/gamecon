import { FunctionComponent } from "preact";
import { useCallback } from "preact/hooks";
import {
  DefiniceObchodMřížka,
  DefiniceObchodMřížkaBuňka,
} from "../../../api/obchod/types";
import { EditorBuňky } from "../EditorBuňky";

type TEditorMřížkyProps = {
  mřížka: DefiniceObchodMřížka;
  setMřížka: (mřížka: DefiniceObchodMřížka) => void;
};

export const EditorMřížky: FunctionComponent<TEditorMřížkyProps> = (props) => {
  const { mřížka, setMřížka } = props;

  const setMřížkaText = useCallback((text: string) => {
    setMřížka({ ...mřížka, text });
  }, [setMřížka, mřížka]);

  const setBuňkaProIndex = useCallback(
    (index: number) => (buňka: DefiniceObchodMřížkaBuňka) => {
      setMřížka({
        ...mřížka,
        buňky: mřížka.buňky.map((x, i) => (i !== index ? x : buňka)),
      });
    },
    [setMřížka, mřížka]
  );

  return (
    <>
      <div>
        Text:{" "}
        <input
          value={mřížka.text ?? ""}
          onChange={(e: any) => setMřížkaText(e.target.value)}
        ></input>
      </div>
      <div style={{marginTop:"24px",display:"grid", gridTemplateColumns: "repeat(4,1fr)", gap:"8px"}}>
        {mřížka.buňky.map((buňka, i) => (
          <EditorBuňky key={i} {...{ buňka, setBuňka: setBuňkaProIndex(i) }} />
        ))}
      </div>
    </>
  );
};

EditorMřížky.displayName = "EditorMřížky";
