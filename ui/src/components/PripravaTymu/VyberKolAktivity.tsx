/**
 * Krok 1: Výběr termínu pro jednotlivá kola.
 * Radiobuttony - vždy jen jeden termín na kolo.
 * Pokud má kolo jen jednu možnost, je automaticky předvybrána.
 *
 * Prop interface zachován z původní verze; pouze JSX a styly přepsány
 * do `gc-tm-*` systému.
 */
import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { UpozorneniOdpocet } from "./UpozorneniOdpocet";
import { IconArrowRight } from "../NastaveniTymuView/Ikony";

export type KoloAktivity = {
  cisloKola: number;
  aktivity: {
    id: number;
    nazev: string;
    cas: string;
  }[];
};

type VyberKolAktivityProps = {
  kola: KoloAktivity[];
  vybrane: Record<number, number>; // cisloKola -> id vybrané aktivity
  onVyber: (cisloKola: number, idAktivity: number) => void;
  onPotvrdit: () => void;
  zbyvajiciCas: string;
  nacita?: boolean;
};

export const VyberKolAktivity: FunctionComponent<VyberKolAktivityProps> = ({
  kola,
  vybrane,
  onVyber,
  onPotvrdit,
  zbyvajiciCas,
  nacita,
}) => {
  // Automaticky vybrat jedinou aktivitu v kole
  useEffect(() => {
    kola.forEach((kolo) => {
      if (kolo.aktivity.length === 1 && vybrane[kolo.cisloKola] === undefined) {
        onVyber(kolo.cisloKola, kolo.aktivity[0].id);
      }
    });
  }, [kola]);

  const vsechnaVybrana = kola.length > 0 && kola.every((k) => vybrane[k.cisloKola] !== undefined);

  return (
    <div>
      <UpozorneniOdpocet
        zbyvajiciCas={zbyvajiciCas}
        podtexty="Zvol si aktivity pro všechna kola a přihlas se jako kapitán."
      />

      {kola.map((kolo) => (
        <div class="gc-tm-round" key={kolo.cisloKola}>
          <div class="gc-tm-round__label">KOLO {kolo.cisloKola}</div>

          {kolo.aktivity.length === 0 ? (
            <div class="gc-tm-empty-state">— žádné termíny —</div>
          ) : (
            <div class="gc-tm-round__options">
              {kolo.aktivity.map((aktivita) => {
                const isSelected = vybrane[kolo.cisloKola] === aktivita.id;
                const jedinaMoznost = kolo.aktivity.length === 1;
                return (
                  <label
                    key={aktivita.id}
                    class={`gc-tm-round-opt ${isSelected ? "is-selected" : ""}`}
                  >
                    <input
                      type="radio"
                      name={`kolo-${kolo.cisloKola}`}
                      checked={isSelected}
                      onChange={() => onVyber(kolo.cisloKola, aktivita.id)}
                      disabled={nacita}
                    />
                    <span class="gc-tm-round-opt__dot" />
                    <span class="gc-tm-round-opt__time" dangerouslySetInnerHTML={{ __html: aktivita.cas }} />
                    {jedinaMoznost && <span class="gc-tm-round-opt__meta">jediný termín</span>}
                  </label>
                );
              })}
            </div>
          )}
        </div>
      ))}

      <button
        class="gc-tm-btn gc-tm-btn--primary gc-tm-btn--lg gc-tm-btn--full"
        disabled={!vsechnaVybrana || nacita}
        onClick={onPotvrdit}
        style={{ marginTop: 18 }}
      >
        {nacita ? "Zpracovávám…" : "Potvrdit výběr"}
        {!nacita && <IconArrowRight />}
      </button>
    </div>
  );
};
