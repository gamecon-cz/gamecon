import { ORGANIZATOR, KONCOVKA_DLE_POHLAVÍ } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";

export const ProgramLegenda = () => {
  const legendaText = GAMECON_KONSTANTY.LEGENDA;
  const organizator = ORGANIZATOR;
  const koncovkaDlePohlaví = KONCOVKA_DLE_POHLAVÍ;

  return (
    <div class="program_legenda">
      <div
        class="informaceSpustime"
        // TODO dangerously znamená dangerously. Vykreslovat text a né html!
        dangerouslySetInnerHTML={{
          __html: legendaText,
        }}
      ></div>
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
