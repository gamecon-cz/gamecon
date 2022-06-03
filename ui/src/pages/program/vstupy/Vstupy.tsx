import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { PŘIHLÁŠEN } from "../../../api";
import { GAMECON_KONSTANTY } from "../../../env";
import { DNY, doplňHáčkyDoDne } from "../../../utils";

const formátujDenVTýdnu = (datum: number) => {
  // vrací den v týdnu začínající nedělí
  //  proto potřebujeme den o jedno posunout zpět
  const obj = new Date(datum);
  const denVTýdnu = (obj.getDay() + 6) % 7;
  const denText = DNY[denVTýdnu];
  return denText;
};

const formátujDatum = (datum: number) => {
  const obj = new Date(datum);
  const denText = doplňHáčkyDoDne(formátujDenVTýdnu(datum));
  const den = obj.getDate();
  // Měsíce jsou oproti dnům idexované od 0. fakt se mě neptejte proč
  const měsíc = obj.getMonth() + 1;

  return `${denText} ${den}.${měsíc}`;
};

type ProgramUživatelskéVstupyProps = {
  aktivníMožnost: string;
  setAktivníMožnost: (path: string, replace?: boolean) => void;
};

export const ProgramUživatelskéVstupy: FunctionComponent<
  ProgramUživatelskéVstupyProps
> = (props) => {
  const { aktivníMožnost, setAktivníMožnost } = props;

  const rok = GAMECON_KONSTANTY.ROK;
  const dny = GAMECON_KONSTANTY.PROGRAM_DNY;
  const přihlášen = PŘIHLÁŠEN;

  const možnosti: {
    popis: string;
    hodnota: string;
  }[] = dny
    .map((datum) => ({
      popis: formátujDatum(datum),
      hodnota: formátujDenVTýdnu(datum),
    }))
    .concat(
      ...(přihlášen ? [{ popis: "můj program", hodnota: "muj_program" }] : [])
    );

  useEffect(() => {
    if (možnosti.some(({ hodnota: value }) => value === aktivníMožnost)) return;
    setAktivníMožnost(možnosti[0].hodnota, true);
  }, [aktivníMožnost]);

  return (
    <>
      <div class="program_hlavicka">
        <h1>Program {rok}</h1>
        <div class="program_dny">
          {možnosti.map(({ popis, hodnota }) => {
            return (
              <button
                class={
                  "program_den" +
                  (hodnota === aktivníMožnost ? " program_den-aktivni" : "")
                }
                onClick={() => setAktivníMožnost(hodnota)}
              >
                {popis}
              </button>
            );
          })}
        </div>
      </div>
    </>
  );
};
