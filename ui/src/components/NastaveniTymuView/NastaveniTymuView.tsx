/**
 * Nastavení týmu modal – redesign v Gamecon vizuálním systému.
 *
 * Prop interface zachován 1:1 s předchozí verzí, takže parent (program/index.tsx)
 * se nemění. Logika potvrzovacích modálů a všech akcí je převzata z původní implementace.
 *
 * Změny:
 *  - Vlastní wrapper `.gc-tm-scrim > .gc-tm-modal` (původní `.modal_obal > .modal` se nahrazuje)
 *  - Inline styly nahrazeny class names z `NastaveniTymuView.less`
 *  - Header je vlastní komponenta s brush stroke + chip-style časy
 *  - "Smazat tým" se přesouvá do pravé části headeru jako danger-ghost button
 *  - "Zavřít" je v patičce, nikoli plovoucí vpravo
 *  - "Odhlásit!" je v pravém slotu headeru když tým existuje (analogicky původnímu chování)
 */
import { FunctionComponent } from "preact";
import { useState } from "preact/hooks";
import { GAMECON_KONSTANTY } from "../../env";
import { NastaveniTymuData } from "../../store/program/slices/všeobecnéSlice";
import { AkceTymuBezKontextu, ClenTymu } from "../../api/program";
import { PripravaTymu } from "../PripravaTymu";
import { TymDetail } from "./TymDetail";
import { SeznamTymu } from "./SeznamTymu";
import { Alert } from "./Alert";
import { IconBrushStroke, IconArrowRight } from "./Ikony";
import { useAktivity } from "../../store/program/selektory";
import { ziskejDenCasAktivity } from "../PripravaTymu/PripravaTymu";
import { t } from "../../i18n";

import "./NastaveniTymuView.less";

type NastaveniTymuViewProps = {
  nazevAktivity?: string;
  tymovaKapacita?: number;
  data: NastaveniTymuData | undefined;
  jeKapitán: boolean;
  načítá?: boolean;
  načítáAkci?: boolean;
  chyba?: string | undefined;
  onZavřít: () => void;
  onPřipojitSe: (idTýmu?: number, kód?: number) => void;
  onOdhlásit: () => void;
  onOdhlasitAktivitu: (aktivitaId: number) => void;
  onProveďAkci: (akceTymu: AkceTymuBezKontextu, dotáhniIpřiNeúspěchu?: boolean) => Promise<void>;
};

export const NastaveniTymuView: FunctionComponent<NastaveniTymuViewProps> = (props) => {
  const {
    nazevAktivity,
    tymovaKapacita,
    data,
    jeKapitán,
    načítá,
    načítáAkci,
    chyba,
    onZavřít,
    onPřipojitSe,
    onOdhlásit,
    onOdhlasitAktivitu,
    onProveďAkci,
  } = props;

  const vsechnyAktivity = useAktivity();
  const [kódPřipojeníDoTýmu, setKódPřipojeníDoTýmu] = useState("");
  const [potvrzení, setPotvrzení] = useState<{ text: string; akce: () => void } | null>(null);

  const sPotvrzením = (text: string, akce: () => void) => () => setPotvrzení({ text, akce });

  const modalMáTým = !!data?.tym?.id;
  const pocetClenu = data?.tym?.clenove?.length ?? 0;
  const týmJePřipravený = pocetClenu > 0;
  const týmNázev = data?.tym?.nazev ?? "";

  const aktivityTymuId = data?.tym?.aktivityTymuId ?? [];
  const aktivityTymu = aktivityTymuId
    .map(id => vsechnyAktivity.find(a => a.id === id))
    .filter((x): x is NonNullable<typeof x> => x !== undefined);

  // časy ve formátu chip-style pro hlavičku
  const casyChips: string[] = aktivityTymu.map(a => ziskejDenCasAktivity(a));

  const můžeZaložitTým =
    !tymovaKapacita || !data?.vsechnyTymy?.length || data.vsechnyTymy.length < tymovaKapacita;

  // ─── Akce ─────────────────────────────────────────────────────────────
  const onOdemkniSPotvrzenim = sPotvrzením(
    `Opravdu chcete odemknout tým ${týmNázev}?`,
    () => { void onProveďAkci({ typ: "odemkni" }); }
  );

  const onOdhlasitSPotvrzenim = sPotvrzením(
    nazevAktivity
      ? t("tym.otazka.odhlasit", { aktivita: nazevAktivity })
      : t("tym.otazka.odhlasit_bez_nazvu"),
    onOdhlásit
  );

  const onPotvrditVýběrAktivit = (idVybranychAktivit: number[]) => {
    void onProveďAkci({ typ: "potvrdVyberAktivit", idVybranychAktivit }, true);
  };

  const onPrihlasitKapitana = () => { void onProveďAkci({ typ: "prihlasKapitana" }); };

  const onSmazatTym = sPotvrzením(
    nazevAktivity
      ? t("tym.otazka.smazat", { aktivita: nazevAktivity })
      : t("tym.otazka.smazat_bez_nazvu"),
    () => { void onProveďAkci({ typ: "smazTym" }); }
  );

  const onZamkniTym = sPotvrzením(
    t("tym.otazka.zamknout", { tym: týmNázev }),
    () => { void onProveďAkci({ typ: "zamkni" }); }
  );

  const onPřegenrovatSPotvrzením = sPotvrzením(
    t("tym.otazka.pregenerovat"),
    () => { void onProveďAkci({ typ: "pregenerujKod" }); }
  );

  const onPředejKapitánaSPotvrzením = (clen: ClenTymu) => sPotvrzením(
    t("tym.otazka.predat_kapitana", { hrac: clen.jmeno }),
    () => { void onProveďAkci({ typ: "predejKapitana", idNovehoKapitana: clen.id }); }
  )();

  const onOdebratČlenaSPotvrzením = (clen: ClenTymu) => sPotvrzením(
    nazevAktivity
      ? t("tym.otazka.odebrat", { hrac: clen.jmeno, tym: týmNázev, aktivita: nazevAktivity })
      : t("tym.otazka.odebrat_bez_aktivity", { hrac: clen.jmeno, tym: týmNázev }),
    () => { void onProveďAkci({ typ: "odhlasClena", idClena: clen.id }); }
  )();

  const onOdhlasitAktivituSPotvrzením = (aktivitaId: number) => {
    const aktivita = vsechnyAktivity.find(a => a.id === aktivitaId);
    sPotvrzením(
      aktivita?.nazev
        ? t("tym.otazka.odhlasit_aktivitu", { aktivita: aktivita.nazev })
        : t("tym.otazka.odhlasit_aktivitu_bez_nazvu"),
      () => onOdhlasitAktivitu(aktivitaId)
    )();
  };

  const onZaložitTým = () => { void onProveďAkci({ typ: "zalozPrazdnyTym" }); };
  const onPřepniVerejnost = () => { void onProveďAkci({ typ: "nastavVerejnost", verejny: !data?.tym?.verejny }); };
  const onNastavLimit = (limit: number) => { void onProveďAkci({ typ: "nastavLimit", limit }); };
  const onNastavNazev = (nazev: string) => { void onProveďAkci({ typ: "nastavNazev", nazev }); };

  // ─── Potvrzovací modal (nested) ──────────────────────────────────────
  const modalPotvrzení = potvrzení && (
    <div class="gc-tm-scrim" onClick={(e) => { if (e.target === e.currentTarget) setPotvrzení(null); }}>
      <div class="gc-tm-modal gc-tm-root" style={{ width: "min(420px, 100%)" }}>
        <div class="gc-tm-body" style={{ padding: 24 }}>
          <div class="gc-tm-eyebrow">{t("tym.potvrzeni")}</div>
          <p style={{ fontSize: 15, lineHeight: 1.5, margin: "8px 0 18px" }}>{potvrzení.text}</p>
          <div style={{ display: "flex", gap: 10, justifyContent: "flex-end" }}>
            <button class="gc-tm-btn gc-tm-btn--ghost" onClick={() => setPotvrzení(null)}>{t("tym.zrusit")}</button>
            <button class="gc-tm-btn gc-tm-btn--primary"
                    onClick={() => { potvrzení.akce(); setPotvrzení(null); }}>
              {t("tym.potvrdit")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );

  // ─── Slot v pravé části headeru: smazat / odhlásit ──────────────────
  const dangerSlot = (
    <div class="gc-tm-danger-slot" style={{ display: "flex", gap: 8 }}>
      {modalMáTým && !týmJePřipravený && (
        <button class="gc-tm-btn gc-tm-btn--danger gc-tm-btn--sm"
                onClick={onSmazatTym}
                disabled={načítá || načítáAkci}>
          {t("tym.smazat")}
        </button>
      )}
      {GAMECON_KONSTANTY.JE_ADMIN && data?.tym?.zamceny && (
        <button class="gc-tm-btn gc-tm-btn--ghost gc-tm-btn--sm" onClick={onOdemkniSPotvrzenim}>
          Odemknout
        </button>
      )}
      {data?.tym && !data?.tym?.zamceny && (
        <button class="gc-tm-btn gc-tm-btn--danger gc-tm-btn--sm"
                onClick={onOdhlasitSPotvrzenim}
                disabled={data?.tym?.zamceny}>
          {t("tym.odhlasit")}
        </button>
      )}
    </div>
  );

  const headerCasy = casyChips.length > 0 ? casyChips : (nazevAktivity ? [] : []);

  return (
    <>
      <div class="gc-tm-scrim gc-tm-root" onClick={(e) => { if (e.target === e.currentTarget) onZavřít(); }}>
        <div class="gc-tm-modal">

          {/* Header */}
          <div class="gc-tm-header">
            <IconBrushStroke className="gc-tm-brush" />
            <div class="gc-tm-eyebrow">{t("aktivity.nastaveni_tymu")}</div>
            <h2 class="gc-tm-title">{nazevAktivity ?? t("tym.aktivita")}</h2>
            {headerCasy.length > 0 && (
              <div class="gc-tm-subtitle">
                {headerCasy.map((cas, i) => (
                  <span key={i} class="gc-tm-timeslot" dangerouslySetInnerHTML={{ __html: cas }} />
                ))}
              </div>
            )}
            <button class="gc-tm-close" onClick={onZavřít} aria-label={t("tym.zavrit")}>×</button>
            {dangerSlot}
          </div>

          {/* Body */}
          <div class="gc-tm-body">
            {(načítá || načítáAkci) && (
              <Alert kind="warning" icon="⏳">
                <div class="gc-tm-alert__title">{t("tym.nacitam")}</div>
              </Alert>
            )}

            {chyba && (
              <Alert kind="danger" icon="!">
                <div class="gc-tm-alert__title">{t("tym.chyba")}</div>
                <div class="gc-tm-alert__desc">{chyba}</div>
              </Alert>
            )}

            {/* Příprava nového týmu (výběr kol + přihlášení kapitána) */}
            {!načítá && data && data.tym && !týmJePřipravený && (
              <PripravaTymu
                casSmazaniRozpracovanyMs={data.tym?.casSmazaniRozpracovanyMs}
                onVybranéAktivity={onPotvrditVýběrAktivit}
                onPrihlasitKapitana={onPrihlasitKapitana}
                onOdhlasitAktivitu={onOdhlasitAktivituSPotvrzením}
                nacita={načítáAkci}
              />
            )}

            {/* Empty state */}
            {!načítá && !modalMáTým && !načítáAkci && (
              <>
                <div class="gc-tm-section">
                  <div class="gc-tm-section-label">
                    {t("tym.novy")} <span class="gc-tm-section-label__dash" />
                  </div>
                  <button
                    class="gc-tm-btn gc-tm-btn--primary gc-tm-btn--lg gc-tm-btn--full"
                    disabled={!můžeZaložitTým}
                    onClick={onZaložitTým}
                  >
                    {t("tym.zalozit")} <IconArrowRight />
                  </button>
                  <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 8 }}>
                    {t("tym.zalozit_popis")}
                  </div>
                </div>

                <div class="gc-tm-or">{t("tym.nebo")}</div>

                <div class="gc-tm-section" style={{ marginTop: 0 }}>
                  <div class="gc-tm-section-label">
                    {t("tym.pridat_se_kodem")} <span class="gc-tm-section-label__dash" />
                  </div>
                  <div class="gc-tm-code-join">
                    <input
                      class="gc-tm-input gc-tm-input--code"
                      placeholder="XXXX"
                      maxLength={4}
                      value={kódPřipojeníDoTýmu}
                      onInput={(x) => setKódPřipojeníDoTýmu((x.currentTarget.value || "").replace(/[^0-9]/g, "").slice(0, 4))}
                    />
                    <button
                      class="gc-tm-btn gc-tm-btn--dark"
                      disabled={kódPřipojeníDoTýmu.length < 4}
                      onClick={() => onPřipojitSe(undefined, +kódPřipojeníDoTýmu)}
                    >
                      {t("tym.pripoj_se")}
                    </button>
                  </div>
                </div>

                {data?.vsechnyTymy && data.vsechnyTymy.length > 0 && (
                  <div class="gc-tm-section">
                    <div class="gc-tm-section-label">
                      {t("tym.verejne_tymy")} <span class="gc-tm-section-label__dash" />
                    </div>
                    <SeznamTymu
                      tymy={data.vsechnyTymy}
                      zobrazitPřipojení={true}
                      onPřipojitSe={onPřipojitSe}
                    />
                  </div>
                )}
              </>
            )}

            {/* Detail týmu */}
            {!načítá && týmJePřipravený && !načítáAkci && data && (
              <TymDetail
                data={data}
                jeKapitán={jeKapitán}
                onPřepniVerejnost={onPřepniVerejnost}
                onPřegenrovatSPotvrzením={onPřegenrovatSPotvrzením}
                onPředejKapitánaSPotvrzením={onPředejKapitánaSPotvrzením}
                onOdebratČlenaSPotvrzením={onOdebratČlenaSPotvrzením}
                onNastavLimit={onNastavLimit}
                onNastavNazev={onNastavNazev}
                onZamkniTym={onZamkniTym}
              />
            )}

            {/* Všechny týmy – kontextový seznam pod detailem */}
            {data?.vsechnyTymy && data.vsechnyTymy.length > 0 && modalMáTým && (
              <div class="gc-tm-section" style={{ marginTop: 8 }}>
                <div class="gc-tm-section-label">
                  {t("tym.vsechny_tymy")} <span class="gc-tm-section-label__dash" />
                </div>
                <SeznamTymu
                  tymy={data.vsechnyTymy}
                  zobrazitPřipojení={false}
                  onPřipojitSe={onPřipojitSe}
                />
              </div>
            )}
          </div>

          {/* Footer */}
          <div class="gc-tm-footer">
            <div class="gc-tm-footer__left" />
            <button class="gc-tm-btn gc-tm-btn--ghost" onClick={onZavřít}>{t("tym.zavrit")}</button>
          </div>
        </div>
      </div>

      {modalPotvrzení}
    </>
  );
};
