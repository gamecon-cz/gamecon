import {
  AKTIVNÍ_MOŽNOST_PROGRAM,
  PŘIHLÁŠEN,
  ROK,
  UKÁZKOVÉ_DNY,
} from "../../../api";
import { DNY } from "../../../utils";

const formátujDatum = (datum: number) => {
  const obj = new Date(datum);
  // vrací den v týdnu začínající nedělí
  //  proto potřebujeme den o jedno posunout zpět
  const denVTýdnu = (obj.getDay() + 6) % 7;
  const denText = DNY[denVTýdnu];
  const den = obj.getDate();
  // Měsíce jsou oproti dnům idexované od 0. fakt se mě neptejte proč
  const měsíc = obj.getMonth() + 1;

  return `${denText} ${den}.${měsíc}`;
};

export const ProgramUživatelskéVstupy = () => {
  const rok = ROK;
  const dny = UKÁZKOVÉ_DNY;
  const přihlášen = PŘIHLÁŠEN;
  const aktivníMožnost = AKTIVNÍ_MOŽNOST_PROGRAM;

  const možnosti = dny
    .map((den) => formátujDatum(den))
    .concat(...(přihlášen ? ["můj program"] : []));

  return (
    <>
      <div class="program_hlavicka">
        <h1>Program {rok}</h1>
        <div class="program_dny">
          {možnosti.map((x) => {
            return (
              <button
                class={
                  "program_den" +
                  (x === aktivníMožnost ? " program_den-aktivni" : "")
                }
              >
                {x}
              </button>
            );
          })}
        </div>
      </div>
    </>
  );
};
