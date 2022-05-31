import { LEGENDA_TEXT, ORGANIZATOR, KONCOVKA_DLE_POHLAVÍ } from "../../api";

export const ProgramLegenda = () => {
  const legendaText = LEGENDA_TEXT;
  const organizator = ORGANIZATOR;
  const koncovkaDlePohlaví = KONCOVKA_DLE_POHLAVÍ;

  return (
    <div class="program_legenda">
      <div class="informaceSpustime">{legendaText}</div>
      <div class="program_legenda_inner">
        <span class="program_legenda_typ">Otevřené</span>
        <span class="program_legenda_typ vDalsiVlne">V další vlně</span>
        <span class="program_legenda_typ vBudoucnu">Připravujeme</span>
        <span class="program_legenda_typ nahradnik">Sleduji</span>
        <span class="program_legenda_typ prihlasen">
          Přihlášen{koncovkaDlePohlaví}
        </span>
        <span class="program_legenda_typ plno">Plno</span>
        {organizator ? (
          <span class="program_legenda_typ organizator">organizuji</span>
        ) : (
          <></>
        )}
      </div>
    </div>
  );
};
