import { FunctionComponent } from "preact";
import { Obsazenost as ObsazenostTyp } from "../../../../api/program";
import { volnoTypZObsazenost } from "../../../../utils";

type TObsazenostProps = {
  obsazenost: ObsazenostTyp | undefined;
  prihlasovatelna: boolean;
  probehnuta: boolean;
  bezObalu?: boolean;
};

export const Obsazenost: FunctionComponent<TObsazenostProps> = (props) => {
  const { obsazenost, prihlasovatelna, probehnuta, bezObalu } = props;

  let aktivitaObsazenost: JSX.Element | undefined = undefined;
  if (obsazenost) {
    const { m, f, km, kf, ku } = obsazenost;
    const volnoTyp = volnoTypZObsazenost(obsazenost);
    const celkem = m + f;
    const kapacitaCelkem = km + kf + ku;

    if (kapacitaCelkem) {
      if (!prihlasovatelna && !probehnuta) {
        aktivitaObsazenost = (
          <span class="neprihlasovatelna">
            {`(${celkem}/${kapacitaCelkem})`}
          </span>
        );
      }

      switch (volnoTyp) {
        case "u":
        case "x":
          aktivitaObsazenost = <>{`(${celkem}/${kapacitaCelkem})`}</>;
          break;
        case "f":
        case "m":
          aktivitaObsazenost = (
            <>
              <span class="f">{`(${f}/${
                kf + (volnoTyp === "m" ? ku : 0)
              })`}</span>
              <span class="m">{`(${m}/${
                km + (volnoTyp === "f" ? ku : 0)
              })`}</span>
            </>
          );
          break;
        default:
          aktivitaObsazenost = <>{` (${f + m}/${ku})`}</>;
          break;
      }
      if (aktivitaObsazenost && !bezObalu) {
        aktivitaObsazenost = (
          <>
            <span class="program_obsazenost">{aktivitaObsazenost}</span>
            {` `}
          </>
        );
      }
    }
  }

  return <>{aktivitaObsazenost}</>;
};

Obsazenost.displayName = "Obsazenost";
