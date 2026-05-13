/**
 * Seznam týmů – buď pro výběr (přidání se do veřejného týmu),
 * nebo jako prostá vizualizace všech týmů u aktivity.
 *
 * Re-použito v Empty stavu i jako "Všechny týmy" pod detailem.
 */
import { FunctionComponent } from "preact";
import { ApiTymVSeznamu } from "../../api/program";

type Props = {
  tymy: ApiTymVSeznamu[];
  zobrazitPřipojení: boolean;
  onPřipojitSe: (idTýmu?: number, kód?: number) => void;
};

export const SeznamTymu: FunctionComponent<Props> = ({ tymy, zobrazitPřipojení, onPřipojitSe }) => {
  if (tymy.length === 0)
    return <div class="gc-tm-empty-state">Žádné týmy</div>;

  return (
    <div>
      {tymy.map((tym) => {
        const plny = tym.limit !== null && tym.pocetClenu >= (tym.limit ?? 0);
        return (
          <div key={tym.id} class="gc-tm-team-row">
            <span class="gc-tm-team-row__name">{tym.nazev || `Tým ${tym.id}`}</span>
            <span class="gc-tm-team-row__fill">
              {tym.pocetClenu}{tym.limit !== null ? `/${(tym.limit ?? "")}` : ""}
            </span>
            <span class={`gc-tm-team-row__vis gc-tm-team-row__vis--${tym.verejny ? "public" : "private"}`}>
              {tym.verejny ? "veřejný" : "soukromý"}
            </span>
            <span class="gc-tm-team-row__spacer" />
            {zobrazitPřipojení && tym.verejny && (
              <button
                class="gc-tm-btn gc-tm-btn--ghost gc-tm-btn--sm"
                disabled={plny}
                onClick={() => onPřipojitSe(tym.id)}
              >
                {plny ? "Plný" : "Připojit se"}
              </button>
            )}
          </div>
        );
      })}
    </div>
  );
};
