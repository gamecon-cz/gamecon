import { GAMECON_KONSTANTY } from "../../../env";
import { useProgramStore } from "../../../store/program";

export const ProgramLegenda = () => {
  const legendaText = GAMECON_KONSTANTY.LEGENDA;
  const uživatel = useProgramStore(s => s.přihlášenýUživatel.data);

  const organizator = uživatel.organizator ?? false;
  const koncovkaDlePohlaví = uživatel.koncovkaDlePohlavi ?? "";
  const přihlášen = uživatel.prihlasen ?? false;

  return (
    <div class="program_legenda">
      <div
        class="informaceSpustime"
        dangerouslySetInnerHTML={{
          __html: legendaText,
        }}
      ></div>
      <div class="program_legenda_inner">
        <span class="program_legenda_typ">Otevřené</span>
        <span class="program_legenda_typ vDalsiVlne">V další vlně</span>
        <span class="program_legenda_typ vBudoucnu">Připravujeme</span>
        {
          přihlášen ? <>
            <span class="program_legenda_typ nahradnik">Sleduji</span>
            <span class="program_legenda_typ prihlasen">
              Přihlášen{koncovkaDlePohlaví}
            </span>
          </> : undefined
        }
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
