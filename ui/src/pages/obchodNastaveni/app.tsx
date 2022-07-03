import { createContext, FunctionComponent } from "preact";
import { StateUpdater, useCallback, useEffect, useState } from "preact/hooks";
import {
  fetchMřížky,
  fetchNastavMřížky,
  fetchPředměty,
} from "../../api/obchod/endpoints";
import {
  DefiniceObchod,
  DefiniceObchodMřížka,
  Předmět,
} from "../../api/obchod/types";
import { EditorMřížek } from "./EditorMřížek";

type TObchodNastaveniProps = {};

/** kam se můžu prokliknout přes buňku */
export type Cíle = {
  předměty: { id: number; text: string }[];
  mřížky: { id: number; text: string }[];
};

export const CíleContext = createContext<Cíle>({
  předměty: [],
  mřížky: [],
});

const usePředměty = () => {
  const [předměty, setPředměty] = useState<Předmět[] | null | undefined>();
  useEffect(() => {
    (async () => {
      setPředměty(await fetchPředměty());
    })();
  }, []);
  return předměty;
};

/**
 * výběr DefiniceObchodMřížka (ID)
 *
 */

export const ObchodNastaveni: FunctionComponent<TObchodNastaveniProps> = (
  props
) => {
  const {} = props;

  const [definiceObchod, setDefiniceObchod] = useState<
    DefiniceObchod | null | undefined
  >();

  const uložMřížky = useCallback(async () => {
    await fetchNastavMřížky(definiceObchod!);
    setDefiniceObchod(undefined);
    setDefiniceObchod(await fetchMřížky());
  }, [definiceObchod]);

  const předměty = usePředměty();

  useEffect(() => {
    (async () => {
      setDefiniceObchod(await fetchMřížky());
    })();
  }, []);

  const setMřížky = useCallback((mřížky: DefiniceObchodMřížka[]) => {
    setDefiniceObchod((definiceObchod) => ({ ...definiceObchod, mřížky }));
  }, []);

  const cíle: Cíle = {
    předměty: předměty?.map((x) => ({ id: x.id, text: x.název })) ?? [],
    mřížky:
      definiceObchod?.mřížky?.map((x) => ({
        id: x.id,
        text: x.text === "" || !x.text ? x.id.toString() : x.text,
      })) ?? [],
  };

  return definiceObchod === null || předměty === null ? (
    <div> nepodařilo se načíst nastavení mřížek !!! </div>
  ) : definiceObchod === undefined || předměty === undefined ? (
    <div>načítání ...</div>
  ) : (
    <>
      <CíleContext.Provider value={cíle}>
        <EditorMřížek
          {...{ mřížky: definiceObchod!.mřížky, setMřížky, uložMřížky }}
        />
      </CíleContext.Provider>
    </>
  );
};

ObchodNastaveni.displayName = "ObchodNastaveni";
