/**
 * Upozornění s odpočtem do smazání rozpracovaného týmu.
 * Refaktor – používá společný `Alert` komponent.
 */
import { FunctionComponent } from "preact";
import { Alert } from "../NastaveniTymuView/Alert";

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
        Tým bude automaticky smazán za <span class="gc-tm-alert__count">{zbyvajiciCas}</span>
      </div>
      {podtexty && <div class="gc-tm-alert__desc">{podtexty}</div>}
    </Alert>
  );
};
