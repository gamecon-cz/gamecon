/**
 * Seznam týmů – buď pro výběr (přidání se do veřejného týmu),
 * nebo jako prostá vizualizace všech týmů u aktivity.
 *
 * Re-použito v Empty stavu i jako "Všechny týmy" pod detailem.
 */
import { FunctionComponent } from "preact";
import { ApiTymVSeznamu } from "../../api/program";
import { t } from "../../i18n";

type Props = {
  tymy: ApiTymVSeznamu[];
  zobrazitPřipojení: boolean;
  onPřipojitSe: (idTýmu?: number, kód?: number) => void;
};

export const SeznamTymu: FunctionComponent<Props> = ({ tymy, zobrazitPřipojení, onPřipojitSe }) => {
  if (tymy.length === 0)
    return <div class="gc-tm-empty-state">{t("tym.zadne_tymy")}</div>;

  return (
    <div>
      {tymy.map((tym) => {
        const plny = tym.limit !== null && tym.pocetClenu >= (tym.limit ?? 0);
        return (
          <div key={tym.id} class="gc-tm-team-row">
            <span class="gc-tm-team-row__name">{tym.nazev || t("tym.tym_cislo", { id: tym.id })}</span>
            <span class="gc-tm-team-row__fill">
              {tym.pocetClenu}{tym.limit !== null ? `/${(tym.limit ?? "")}` : ""}
            </span>
            <span class={`gc-tm-team-row__vis gc-tm-team-row__vis--${tym.verejny ? "public" : "private"}`}>
              {t(tym.verejny ? "tym.verejny" : "tym.soukromy")}
            </span>
            <span class="gc-tm-team-row__spacer" />
            {zobrazitPřipojení && tym.verejny && (
              <button
                class="gc-tm-btn gc-tm-btn--ghost gc-tm-btn--sm"
                disabled={plny}
                onClick={() => onPřipojitSe(tym.id)}
              >
                {t(plny ? "tym.plny" : "tym.pripojit_se")}
              </button>
            )}
          </div>
        );
      })}
    </div>
  );
};
