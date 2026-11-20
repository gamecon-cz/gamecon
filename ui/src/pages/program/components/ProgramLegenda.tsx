import { AktivitaStav } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import { t } from "../../../i18n";
import { popisekPřihlášen } from "../../../i18n/program";
import {
  useUrlStavStavyFiltr,
  useÚčastník,
} from "../../../store/program/selektory";
import { nastavFiltrStavů } from "../../../store/program/slices/urlSlice";

export const ProgramLegenda = () => {
  const legendaText = GAMECON_KONSTANTY.LEGENDA;
  const účastník = useÚčastník();

  // todo: tady se má asi používat slovo vypravěč (a možná i logika potřebuje změnit na lidi co májí právo vyprávět)
  const organizator = účastník?.role?.organizator ?? false;
  const jeÚčastník = účastník ?? false;

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

  const vnitřek = (
    <div class="program_legenda_inner">
        <div class="program_legenda_typ">
          {t("program.legenda.filtruj_podle")}
        </div>
        <label class="program_legenda_typ otevrene">
          {zaškrtávátkoPro("volno")}
          {t("program.legenda.otevrene")}
        </label>
        <label class="program_legenda_typ vDalsiVlne">
          {zaškrtávátkoPro("vDalsiVlne")}
          {t("program.legenda.v_dalsi_vlne")}
        </label>
        <label class="program_legenda_typ vBudoucnu">
          {zaškrtávátkoPro("vBudoucnu")}
          {t("program.legenda.pripravujeme")}
        </label>
        {jeÚčastník ? (
          <>
            <label class="program_legenda_typ nahradnik">
              {zaškrtávátkoPro("nahradnik")}
              {t("program.legenda.sleduji")}
            </label>
            <label class="program_legenda_typ prihlasen">
              {zaškrtávátkoPro("prihlasen")}
              {popisekPřihlášen(účastník?.pohlavi)}
            </label>
            <label class="program_legenda_typ neucast">
              {zaškrtávátkoPro("neucast")}
              {t("program.legenda.neucast")}
            </label>
          </>
        ) : undefined}
        <label class="program_legenda_typ plno">
          {zaškrtávátkoPro("plno")}
          {t("program.legenda.plno")}
        </label>
        {organizator ? (
          <label class="program_legenda_typ organizator">
            {zaškrtávátkoPro("organizator")}
            {t("program.legenda.organizuji")}
          </label>
        ) : (
          <></>
        )}
    </div>
  );

  // todo: tohle chce opravit jak jsou napsané styly na webu i v adminu
  if (GAMECON_KONSTANTY.JE_ADMIN) {
    return vnitřek;
  }

  return (
    <div class="program_legenda">
      <div
        class="informaceSpustime"
        dangerouslySetInnerHTML={{
          __html: legendaText,
        }}
      ></div>
      {vnitřek}
    </div>
  );
};
