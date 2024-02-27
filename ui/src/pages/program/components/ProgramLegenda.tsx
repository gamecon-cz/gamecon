import { AktivitaStav } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import {
  useUrlStavStavyFiltr,
  useUživatel,
} from "../../../store/program/selektory";
import { nastavFiltrStavů } from "../../../store/program/slices/urlSlice";

export const ProgramLegenda = () => {
  const legendaText = GAMECON_KONSTANTY.LEGENDA;
  const uživatel = useUživatel();

  const organizator = uživatel.organizator ?? false;
  const koncovkaDlePohlaví = uživatel.koncovkaDlePohlavi ?? "";
  const přihlášen = uživatel.prihlasen ?? false;

  const stavyFiltr = useUrlStavStavyFiltr();

  const filtrujeStav = (stav: AktivitaStav) =>
    stavyFiltr.some((x) => x === stav);

  const překlopStav = (stav: AktivitaStav) => {
    const filtruje = filtrujeStav(stav);
    if (filtruje) {
      nastavFiltrStavů(stavyFiltr.filter((x) => x !== stav).sort());
    } else {
      nastavFiltrStavů(stavyFiltr.concat(stav).sort());
    }
  };

  const zaškrtávátkoPro = (stav: AktivitaStav) => (
    <input
      type="checkbox"
      class="program_legenda_typ--checkbox"
      checked={filtrujeStav(stav)}
      onClick={() => {
        překlopStav(stav);
      }}
    />
  );

  return (
    <div class="program_legenda">
      <div
        class="informaceSpustime"
        dangerouslySetInnerHTML={{
          __html: legendaText,
        }}
      ></div>
      <div class="program_legenda_inner">
        <div class="program_legenda_typ">
          Filtruj podle:
        </div>
        <label class="program_legenda_typ otevrene">
          {zaškrtávátkoPro("volno")}
          Otevřené
        </label>
        <label class="program_legenda_typ vDalsiVlne">
          {zaškrtávátkoPro("vDalsiVlne")}V další vlně
        </label>
        <label class="program_legenda_typ vBudoucnu">
          {zaškrtávátkoPro("vBudoucnu")}
          Připravujeme
        </label>
        {přihlášen ? (
          <>
            <label class="program_legenda_typ nahradnik">
              {zaškrtávátkoPro("nahradnik")}
              Sleduji
            </label>
            <label class="program_legenda_typ prihlasen">
              {zaškrtávátkoPro("prihlasen")}
              Přihlášen{koncovkaDlePohlaví}
            </label>
          </>
        ) : undefined}
        <label class="program_legenda_typ plno">
          {zaškrtávátkoPro("plno")}
          Plno
        </label>
        {organizator ? (
          <label class="program_legenda_typ organizator">
            {zaškrtávátkoPro("organizator")}
            organizuji
          </label>
        ) : (
          <></>
        )}
      </div>
    </div>
  );
};
