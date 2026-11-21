import { useLayoutEffect, useRef, useState } from "preact/hooks";

/** výchozí maxMenuHeight v react-select */
const NEJMENŠÍ_VÝŠKA_MENU = 300;

/**
 * Menu výběru až ke spodku okna. Kdo vybírá štítky, stejně nekouká na aktivity pod ním.
 * Výška se počítá podle toho, kde zrovna výběr na stránce je, a znovu po každém vykreslení,
 * dokud je menu otevřené: s dalšími vybranými štítky výběr naroste o řádek a menu sjede níž.
 */
export const useMenuDoSpodkuOkna = () => {
  const obal = useRef<HTMLDivElement>(null);
  const [otevřené, setOtevřené] = useState(false);
  const [výška, setVýška] = useState(NEJMENŠÍ_VÝŠKA_MENU);

  useLayoutEffect(() => {
    const spodekVýběru = obal.current?.getBoundingClientRect().bottom;
    if (!otevřené || spodekVýběru === undefined) return;
    // odsazení menu od výběru a kousek místa pod ním
    const nováVýška = Math.max(NEJMENŠÍ_VÝŠKA_MENU, Math.floor(window.innerHeight - spodekVýběru - 24));
    // bez porovnání by se vykreslovalo dokola
    if (nováVýška !== výška) setVýška(nováVýška);
  });

  return {
    obal,
    výška,
    otevři: () => {
      setOtevřené(true);
    },
    zavři: () => {
      setOtevřené(false);
    },
  };
};
