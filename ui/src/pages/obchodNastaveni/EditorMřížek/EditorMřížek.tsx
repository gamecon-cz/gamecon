import { FunctionComponent } from "preact";
import { StateUpdater, useCallback, useEffect, useState } from "preact/hooks";
import {
  DefiniceObchod,
  DefiniceObchodMřížka,
  DefiniceObchodMřížkaBuňka,
} from "../../../api/obchod/types";
import { EditorMřížky } from "../EditorMřížky";
import "./EditorMřížek.less";

type TEditorMřížekProps = {
  // TODO: nemá být DefiniceObchod, má být pouze mřížky
  mřížky: DefiniceObchodMřížka[];
  setMřížky: (x: DefiniceObchodMřížka[]) => void;
  uložMřížky: () => Promise<void>;
};

const useGenerujId = () => {
  const [lastId, setLastId] = useState(-1);

  const generate = useCallback(() => {
    setLastId((x) => x - 1);
    return lastId;
  }, [lastId]);

  return generate;
};

const vytvořPrázdnouMřížku = (id: number) => ({
  id,
  text: "",
  buňky: new Array(12).fill(0).map(
    () =>
      ({
        typ: "předmět",
        barvaPozadí: "",
        text: "",
        cilId: 0,
      } as DefiniceObchodMřížkaBuňka)
  ),
});

export const EditorMřížek: FunctionComponent<TEditorMřížekProps> = (props) => {
  const { mřížky, setMřížky, uložMřížky } = props;

  const [mřížkaVybranáI, setMřížkaVybranáI] = useState(0);

  const mřížka = mřížky[mřížkaVybranáI];
  const setMřížka = useCallback(
    (mřížka: DefiniceObchodMřížka) => {
      setMřížky(mřížky.map((x, i) => (i !== mřížkaVybranáI ? x : mřížka)));
    },
    [setMřížky, mřížky, mřížkaVybranáI]
  );

  const generateId = useGenerujId();

  const přidatMřížku = useCallback(() => {
    setMřížkaVybranáI(mřížky.length);
    setMřížky(mřížky.concat(vytvořPrázdnouMřížku(generateId())));
  }, [setMřížky, mřížky, generateId]);

  // Vytvoř výchozí mřížku pokud neexistuje
  useEffect(()=>{
    if (!mřížky.some(x=>x.id === 1)) {
      setMřížkaVybranáI(0);
      setMřížky([vytvořPrázdnouMřížku(1),...mřížky]);
    }
  }, []);

  return (
    <>
      <div>
        <button onClick={přidatMřížku}>Přidat mřížku</button>
        <button onClick={uložMřížky} style={{ marginLeft: "24px" }}>
          Ulož všechny změny
        </button>
      </div>
      <div>
        Mřížka:
        <select
          value={mřížkaVybranáI}
          onChange={(e: any) => setMřížkaVybranáI(+e.target.value)}
        >
          {mřížky.map((x, i) => 
            {
              const text = !x.text || x.text === "" ? x.id : x.text
              return <option key={x.id} value={i}>{text}</option>;
            }
          )}
        </select>
      </div>
      {mřížka ? <EditorMřížky {...{ mřížka, setMřížka }} /> : undefined}
    </>
  );
};

EditorMřížek.displayName = "EditorMřížek";
