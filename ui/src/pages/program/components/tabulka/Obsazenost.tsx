import { FunctionComponent } from "preact";
import { Obsazenost as ObsazenostTyp } from "../../../../api/program";
import { volnoTypZObsazenost } from "../../../../utils";

type TObsazenostProps = {
  obsazenost: ObsazenostTyp | undefined;
  prihlasovatelna: boolean;
  probehnuta: boolean;
};

export const Obsazenost: FunctionComponent<TObsazenostProps> = (props) => {
  const { obsazenost, prihlasovatelna, probehnuta } = props;
  if (!obsazenost) return null;

  const { m, f, km, kf, ku } = obsazenost;
  const kapacitaCelkem = km + kf + ku;
  const celkem = m + f;

  if (!kapacitaCelkem) return null;

  const volnoTyp = volnoTypZObsazenost(obsazenost);

  let aktivitaObsazenost: JSX.Element | undefined = undefined;

  if (!prihlasovatelna && !probehnuta) {
    // todo: aktivitaObsazenost je vždy přepsaná následným switche. Tady by měl být asi return (nutno otestovat)
    aktivitaObsazenost = (
      <span class="neprihlasovatelna">
        {`${celkem}/${kapacitaCelkem}`}
      </span>
    );
  }

  switch (volnoTyp) {
    case "u":
    case "x":
      aktivitaObsazenost = <>{`${celkem}/${kapacitaCelkem}`}</>;
      break;
    case "f":
    case "m":
      aktivitaObsazenost = (
        <>
          <span class="f">{`${f}/${
            kf + (volnoTyp === "m" ? ku : 0)
          }`}</span>
          <span class="m">{`${m}/${
            km + (volnoTyp === "f" ? ku : 0)
          }`}</span>
        </>
      );
      break;
    default:
      aktivitaObsazenost = <>{` ${f + m}/${ku}`}</>;
      break;
  }
  // todo(tym): nadrátovat konkrétní velikosti
  aktivitaObsazenost = (
    <>
      <span class="program_obsazenost_tym">4/5</span>
      <span class="program_obsazenost">{aktivitaObsazenost}</span>
      {` `}
    </>
  );

  return <>{aktivitaObsazenost}</>;
};

Obsazenost.displayName = "Obsazenost";
