/**
 * Upozornění s odpočtem do smazání rozpracovaného týmu.
 * Refaktor – používá společný `Alert` komponent.
 */
import { FunctionComponent } from "preact";
import { Alert } from "../NastaveniTymuView/Alert";
import { tSPrvky } from "../../i18n";

type UpozorneniOdpocetProps = {
  zbyvajiciCas: string;
  podtexty?: string;
};

export const UpozorneniOdpocet: FunctionComponent<UpozorneniOdpocetProps> = ({
  zbyvajiciCas,
  podtexty,
}) => {
  return (
    <Alert kind="warning" icon="⏱">
      <div class="gc-tm-alert__title">
        {tSPrvky("tym.priprava.smazan_za", { cas: <span class="gc-tm-alert__count">{zbyvajiciCas}</span> })}
      </div>
      {podtexty && <div class="gc-tm-alert__desc">{podtexty}</div>}
    </Alert>
  );
};
