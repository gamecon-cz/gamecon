import { FunctionComponent, render } from "preact";

type TObchodNastaveniProps = {};

/**
 * výběr DefiniceObchodMřížka (ID)
 *
 */

export const ObchodNastaveni: FunctionComponent<TObchodNastaveniProps> = (
  props
) => {
  const {} = props;

  return (
    <>
      Mřížka: <select style={{ minWidth: "100px" }}></select> Text:{" "}
      <input></input>
      <div>
        <div>Buňky:</div>
      </div>
    </>
  );
};

ObchodNastaveni.displayName = "ObchodNastaveni";
