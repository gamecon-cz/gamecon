import { FunctionComponent } from "preact";
import { ApiKostymAktivity } from "../../../api/program";
import { t, tSPrvky } from "../../../i18n";

type KostymAktivityProps = {
  kostym?: ApiKostymAktivity | null,
};

/**
 * Kostým z půjčovny u aktivity v detailu programu: rezervovaný kostým s odkazem na vlastní rezervace,
 * nebo odkaz do katalogu na kostýmy volné v čase aktivity.
 */
export const KostymAktivity: FunctionComponent<KostymAktivityProps> = ({ kostym }) => {
  if (!kostym) {
    return null;
  }

  return (
    <div class="programNahled_kostym">
      {kostym.nazev !== null
        ? <>{tSPrvky("program.nahled.kostym", { kostym: <a href={kostym.url}>{kostym.nazev}</a> })}</>
        : <a href={kostym.url}>{t("program.nahled.pujcit_kostym")}</a>}
    </div>
  );
};
