import { FunctionComponent } from "preact";
import { createContext } from "preact";
import { useEffect, useState } from "preact/hooks";
import { fetchMřížky, fetchPředměty } from "../../api/obchod/endpoints";
import { DefiniceObchod, Předmět } from "../../api/obchod/types";
import { Obchod } from "./components/Obchod";
import "./App.less";

type TAppProps = {

}

// TODO: hodně společné logika pro Obchod a Obchod nastavení, sjednotit

export const PředmětyContext = createContext<Předmět[]>([]);

const usePředměty = () => {
  const [předměty, setPředměty] = useState<Předmět[] | null | undefined>();
  useEffect(() => {
    void (async () => {
      setPředměty(await fetchPředměty());
    })();
  }, []);
  return předměty;
};


export const App: FunctionComponent<TAppProps> = (props) => {
  const {} = props;

  const [definiceObchod, setDefiniceObchod] = useState<
    DefiniceObchod | null | undefined
  >();

  const předměty = usePředměty();

  useEffect(() => {
    void (async () => {
      setDefiniceObchod(await fetchMřížky());
    })();
  }, []);

  return definiceObchod === null || předměty === null ? (
    <div> nepodařilo se načíst nastavení mřížek !!! </div>
  ) : definiceObchod === undefined || předměty === undefined ? (
    <div>načítání ...</div>
  ) : (
    <>
      <PředmětyContext.Provider value={předměty}>
        <Obchod definice={definiceObchod} />
      </PředmětyContext.Provider>
    </>
  );
};

App.displayName = "App";
