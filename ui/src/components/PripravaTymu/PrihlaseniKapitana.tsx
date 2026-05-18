/**
 * Krok 2: Přihlášení se jako kapitán.
 * Zobrazí potvrzení vybraných termínů a případné konflikty.
 *
 * Prop interface zachován z původní verze; pouze JSX a styly přepsány.
 */
import { FunctionComponent } from "preact";
import { UpozorneniOdpocet } from "./UpozorneniOdpocet";
import { Alert } from "../NastaveniTymuView/Alert";

type AktivitaSPrekryvem = {
  id: number;
  nazev: string;
  cas: string;
};

type PrihlaseniKapitanaProps = {
  vybranéAktivity: string;
  překrývajícíSeAktivity: AktivitaSPrekryvem[];
  onPrihlasit: () => void;
  onOdhlasitAktivitu: (aktivitaId: number) => void;
  zbyvajiciCas: string;
  nacita?: boolean;
};

export const PrihlaseniKapitana: FunctionComponent<PrihlaseniKapitanaProps> = ({
  vybranéAktivity,
  překrývajícíSeAktivity,
  onPrihlasit,
  onOdhlasitAktivitu,
  zbyvajiciCas,
  nacita,
}) => {
  const máPřekryv = překrývajícíSeAktivity.length > 0;
  const řádky = (vybranéAktivity ?? "").split("\n").map(s => s.trim()).filter(Boolean);

  return (
    <div>
      <UpozorneniOdpocet
        zbyvajiciCas={zbyvajiciCas}
        podtexty="Přihlas se jako kapitán nebo bude tým automaticky smazán."
      />

      {řádky.length > 0 && (
        <div class="gc-tm-section" style={{ marginTop: 0 }}>
          <div class="gc-tm-section-label">
            Vybrané termíny <span class="gc-tm-section-label__dash" />
          </div>
          <div class="gc-tm-chosen-list">
            {řádky.map((řádek, i) => (
              <div key={i} class="gc-tm-chosen-row">
                <span class="gc-tm-chosen-row__num">{i + 1}</span>
                <span class="gc-tm-chosen-row__text">{řádek}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {máPřekryv && (
        <div class="gc-tm-section">
          <Alert kind="danger" icon="!">
            <div class="gc-tm-alert__title">Konflikt termínů</div>
            <div class="gc-tm-alert__desc">Pro přihlášení jako kapitán se musíš odhlásit z těchto aktivit:</div>
          </Alert>
          <div>
            {překrývajícíSeAktivity.map(a => (
              <div key={a.id} class="gc-tm-team-row" style={{ background: "var(--red-bg)", borderColor: "var(--red-2)" }}>
                <div>
                  <div class="gc-tm-team-row__name">{a.nazev}</div>
                  <div
                    style={{ fontSize: 12, color: "var(--ink-3)", fontFamily: "var(--font-mono)" }}
                    dangerouslySetInnerHTML={{ __html: a.cas }}
                  />
                </div>
                <span class="gc-tm-team-row__spacer" />
                <button
                  class="gc-tm-btn gc-tm-btn--danger gc-tm-btn--sm"
                  onClick={() => onOdhlasitAktivitu(a.id)}
                  disabled={nacita}
                >
                  Odhlásit
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      <button
        class={`gc-tm-btn ${máPřekryv ? "" : "gc-tm-btn--success"} gc-tm-btn--lg gc-tm-btn--full`}
        onClick={onPrihlasit}
        disabled={nacita || máPřekryv}
        style={{ marginTop: 18 }}
      >
        {nacita ? "Přihlašuji…" : "✓ Přihlásit se jako kapitán"}
      </button>
    </div>
  );
};
