import { useContext } from "preact/hooks";
import { Aktivita, Obsazenost } from "../../../api";
import { obsazenostZVolnoTyp } from "../../../utils/tranformace";
import { ProgramURLState } from "../routing";

type ObsazenostProps = { obsazenost: Obsazenost };

const ObsazenostComp = (props: ObsazenostProps) => {
  const { obsazenost } = props;

  const { m, f, km, kf, ku } = obsazenost;
  const c = m + f;
  const kc = ku + km + kf;

  if (kc !== 0)
    switch (obsazenostZVolnoTyp(obsazenost)) {
      case "u":
      case "x":
        return (
          <div>
            {" "}
            ({c}/{kc})
          </div>
        );
      case "f":
        return (
          <div>
            <span class="f">
              ({f}/{kf}){" "}
            </span>
            <span class="m">
              ({m}/{km + ku})
            </span>
          </div>
        );
      case "m":
        return (
          <div>
            <span class="f">
              ({f}/{kf + ku}){" "}
            </span>
            <span class="m">
              ({m}/{km})
            </span>
          </div>
        );
    }
  return <></>;
};

type TabulkaBuňkaProps = {
  aktivita: Aktivita;
};

export const TabulkaBuňka = (props: TabulkaBuňkaProps) => {
  const { aktivita } = props;
  const { setUrlState, urlState } = useContext(ProgramURLState);

  const cenaVysledna = Math.round(
    aktivita.cenaZaklad * (aktivita.slevaNasobic ?? 1)
  );

  const cenaVyslednaString =
    aktivita.slevaNasobic === 0 || aktivita.cenaZaklad <= 0 ? (
      <>zdarma</>
    ) : (
      <>
        {(aktivita.slevaNasobic ?? 1) !== 1 ? "*" : ""}
        {cenaVysledna}&thinsp;Kč
      </>
    );

  const classes: string[] = [];
  if (aktivita.prihlaseno) {
    classes.push("prihlasen");
  }
  if (aktivita.vedu) {
    classes.push("organizator");
  }
  if (aktivita.nahradnik) {
    classes.push("nahradnik");
  }
  if (aktivita.vdalsiVlne) {
    classes.push("vDalsiVlne");
  }
  // TODO:
  // if (aktivita.) {
  //    classes.push("plno");
  // }
  if (aktivita.vBudoucnu) {
    classes.push("vBudoucnu");
  }

  return (
    <div class={classes.join(" ")}>
      <a
        class="title"
        title={aktivita.nazev}
        onClick={() =>
          setUrlState({ ...urlState, aktivitaNáhledId: aktivita.id })
        }
      >
        {aktivita.nazev.substring(0, 20)}
      </a>
      <div class="obsazenost">
        <ObsazenostComp obsazenost={aktivita.obsazenost} />
      </div>
      <div class="cena">{cenaVyslednaString}</div>
    </div>
  );
};
