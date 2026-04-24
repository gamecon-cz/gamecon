/**
 * Kritické červené varování s odpočtem.
 * Zobrazuje zbývající čas do smazání týmu.
 */
import { FunctionComponent } from "preact";

type UpozorneniOdpocetProps = {
  zbyvajiciCas: string;
  podtexty?: string;
};

export const UpozorneniOdpocet: FunctionComponent<UpozorneniOdpocetProps> = ({
  zbyvajiciCas,
  podtexty,
}) => {
  return (
    <div
      style={{
        backgroundColor: "#fee",
        border: "2px solid #c33",
        padding: "12px",
        borderRadius: "4px",
        color: "#c33",
        fontWeight: "bold",
      }}
    >
      ⚠️ Tým bude automaticky smazán za <strong>{zbyvajiciCas}</strong>
      <br />
      <span style={{ fontSize: "0.9em", fontWeight: "normal" }}>
        {podtexty ?? ""}
      </span>
    </div>
  );
};
