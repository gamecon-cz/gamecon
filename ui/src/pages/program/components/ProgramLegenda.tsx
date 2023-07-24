import { GAMECON_KONSTANTY } from "../../../env";
import { useUživatel } from "../../../store/program/selektory";

export const ProgramLegenda = () => {
  const legendaText = GAMECON_KONSTANTY.LEGENDA;
  const uživatel = useUživatel();

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
        <label class="program_legenda_typ otevrene">
          <input type="checkbox" class="program_legenda_typ--checkbox" />
          Otevřené
        </label>
        <label class="program_legenda_typ vDalsiVlne">
          <input type="checkbox" class="program_legenda_typ--checkbox" />V další
          vlně
        </label>
        <label class="program_legenda_typ vBudoucnu">
          <input type="checkbox" class="program_legenda_typ--checkbox" />
          Připravujeme
        </label>
        {přihlášen ? (
          <>
            <label class="program_legenda_typ nahradnik">
              <input type="checkbox" class="program_legenda_typ--checkbox" />
              Sleduji
            </label>
            <label class="program_legenda_typ prihlasen">
              <input type="checkbox" class="program_legenda_typ--checkbox" />
              Přihlášen{koncovkaDlePohlaví}
            </label>
          </>
        ) : undefined}
        <label class="program_legenda_typ plno">
          <input type="checkbox" class="program_legenda_typ--checkbox" />
          Plno
        </label>
        {organizator ? (
          <label class="program_legenda_typ organizator">
            <input type="checkbox" class="program_legenda_typ--checkbox" />
            organizuji
          </label>
        ) : (
          <></>
        )}
      </div>
    </div>
  );
};
