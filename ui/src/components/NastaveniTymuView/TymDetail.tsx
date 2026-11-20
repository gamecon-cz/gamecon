/**
 * Detail týmu – zobrazuje se když je tým založen a má alespoň 1 člena.
 *
 * Prop interface zachován 1:1 s původní implementací.
 * Logika (editace názvu, kód, veřejnost, kapacita, zámek) je přejatá,
 * pouze JSX a styly jsou přepsané do nového systému.
 */
import { FunctionComponent } from "preact";
import { useEffect, useState } from "preact/hooks";
import { NastaveniTymuData } from "../../store/program/slices/všeobecnéSlice";
import { ApiAktivitaTym, ClenTymu } from "../../api/program";
import { generujNahodnyNazevTymu } from "../../utils/nazevTymu";
import { Alert } from "./Alert";
import { IconCopy, IconDice } from "./Ikony";
import { t, tSPrvky } from "../../i18n";

type TymDetailProps = {
  data: NastaveniTymuData;
  jeKapitán: boolean;
  onPřepniVerejnost: () => void;
  onPřegenrovatSPotvrzením: () => void;
  onPředejKapitánaSPotvrzením: (clen: ClenTymu) => void;
  onOdebratČlenaSPotvrzením: (clen: ClenTymu) => void;
  onNastavLimit: (limit: number) => void;
  onNastavNazev: (nazev: string) => void;
  onZamkniTym: () => void;
};

export const TymDetail: FunctionComponent<TymDetailProps> = ({
  data,
  jeKapitán,
  onPřepniVerejnost,
  onPřegenrovatSPotvrzením,
  onPředejKapitánaSPotvrzením,
  onOdebratČlenaSPotvrzením,
  onNastavLimit,
  onNastavNazev,
  onZamkniTym,
}) => {
  const dataTymu = data.tym;
  const časExpiraceMs = !dataTymu?.zamceny ? dataTymu?.casExpiraceMs : undefined;

  const jeVTymu = !!dataTymu?.id;
  const maxKapacita = dataTymu?.maxKapacita ?? null;
  const minKapacita = dataTymu?.minKapacita ?? 0;
  const pocetClenu = dataTymu?.clenove?.length ?? 0;
  const limit = dataTymu?.limitTymu ?? null;

  const minKapacitaNaplněna = pocetClenu >= minKapacita;

  const [zkopírováno, setZkopírováno] = useState(false);
  const zkopírujKód = (kód: number) => {
    void navigator.clipboard.writeText(String(kód)).then(() => {
      setZkopírováno(true);
      setTimeout(() => setZkopírováno(false), 1500);
    });
  };

  const nazevServer = dataTymu?.nazev ?? "";
  const [nazevDraft, setNazevDraft] = useState(nazevServer);
  useEffect(() => { setNazevDraft(nazevServer); }, [nazevServer]);
  const ulozNazev = () => {
    const novy = nazevDraft.trim();
    if (novy !== nazevServer) onNastavNazev(novy);
  };
  const nazevEditovatelny = jeVTymu && jeKapitán && !dataTymu?.zamceny;

  /* ── jednotlivé sekce ─────────────────────────────────────────── */

  const status = dataTymu?.zamceny ? (
    <Alert kind="success" icon="✓">
      <div class="gc-tm-alert__title">{t("tym.zamceny")}</div>
      <div class="gc-tm-alert__desc">{t("tym.uzijte_si_hru")}</div>
    </Alert>
  ) : (
    <OdpočetExpiraceAktivity
      dataTym={dataTymu}
      časExpiraceMs={časExpiraceMs}
      minKapacitaNaplněna={minKapacitaNaplněna}
    />
  );

  const sekceNazev = jeVTymu && (
    <div class="gc-tm-section" style={{ marginTop: 0 }}>
      <div class="gc-tm-section-label">
        {t("tym.nazev")} <span class="gc-tm-section-label__dash" />
      </div>
      {nazevEditovatelny ? (
        <div class="gc-tm-name-edit">
          <input
            class="gc-tm-input"
            value={nazevDraft}
            maxLength={255}
            placeholder={t("tym.nazev_zadejte")}
            onInput={(e) => setNazevDraft(e.currentTarget.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") ulozNazev();
              if (e.key === "Escape") setNazevDraft(nazevServer);
            }}
          />
          <button
            class="gc-tm-icon-btn"
            title={t("tym.nazev_nahodny")}
            onClick={() => setNazevDraft(generujNahodnyNazevTymu())}
          >
            <IconDice size={18} />
          </button>
          <button
            class="gc-tm-btn"
            disabled={nazevDraft.trim() === nazevServer}
            onClick={ulozNazev}
          >
            {t("tym.ulozit")}
          </button>
        </div>
      ) : (
        <div style={{ fontWeight: 700, fontSize: 18 }}>
          {nazevServer || <span style={{ color: "var(--ink-3)", fontStyle: "italic", fontWeight: 500 }}>{t("tym.bez_nazvu")}</span>}
        </div>
      )}
    </div>
  );

  const sekceKod = !dataTymu?.zamceny && dataTymu?.kod && (
    <div class="gc-tm-section">
      <div class="gc-tm-code-box">
        <div>
          <div class="gc-tm-code-box__label">{t("tym.kod")}</div>
          <div class="gc-tm-code-box__value">{dataTymu.kod}</div>
        </div>
        <div class="gc-tm-code-box__right">
          <button
            class="gc-tm-icon-btn"
            title={t(zkopírováno ? "tym.zkopirovano" : "tym.zkopirovat")}
            onClick={() => zkopírujKód(dataTymu.kod!)}
          >
            {zkopírováno ? "✓" : <IconCopy size={16} />}
          </button>
          {jeKapitán && (
            <button
              class="gc-tm-btn gc-tm-btn--ghost gc-tm-btn--sm"
              onClick={onPřegenrovatSPotvrzením}
            >
              {t("tym.pregenerovat")}
            </button>
          )}
        </div>
      </div>
    </div>
  );

  const sekceVerejnost = !dataTymu?.zamceny && jeKapitán && dataTymu?.verejny !== undefined && (
    <div class="gc-tm-section">
      <div class="gc-tm-section-label">
        {t("tym.viditelnost")} <span class="gc-tm-section-label__dash" />
      </div>
      <div class="gc-tm-vis-row">
        <div
          class={`gc-tm-vis-pill ${!dataTymu.verejny ? "is-active" : ""}`}
          onClick={() => dataTymu.verejny && onPřepniVerejnost()}
        >
          {t("tym.soukromy_popis")}
        </div>
        <div
          class={`gc-tm-vis-pill ${dataTymu.verejny ? "is-active" : ""}`}
          onClick={() => !dataTymu.verejny && onPřepniVerejnost()}
        >
          {t("tym.verejny_popis")}
        </div>
      </div>
    </div>
  );

  const sekceClenove = dataTymu?.clenove && dataTymu.clenove.length > 0 && (
    <div class="gc-tm-section">
      <div class="gc-tm-section-label">
        {t("tym.clenove")}
        {limit !== null && (
          <span class="gc-tm-members-count">
            ({pocetClenu}/{limit}{minKapacita ? `, min. ${minKapacita}${maxKapacita !== null && limit < maxKapacita ? ` max. ${maxKapacita}` : ""}` : ""})
          </span>
        )}
        <span class="gc-tm-section-label__dash" />
      </div>

      {dataTymu.clenove.map((clen) => (
        <div key={clen.id} class={`gc-tm-member ${clen.jeKapitan ? "gc-tm-member--captain" : ""}`}>
          <span class="gc-tm-member__avatar">{iniciály(clen.jmeno)}</span>
          <span class="gc-tm-member__name">{clen.jmeno}</span>
          {clen.jeKapitan && <span class="gc-tm-member__badge">{t("tym.kapitan")}</span>}
          {!dataTymu?.zamceny && jeKapitán && !clen.jeKapitan && (
            <div class="gc-tm-member__actions">
              <button
                class="gc-tm-btn gc-tm-btn--ghost gc-tm-btn--sm"
                onClick={() => onPředejKapitánaSPotvrzením(clen)}
              >
                {t("tym.predat_kapitana")}
              </button>
              <button
                class="gc-tm-btn gc-tm-btn--ghost gc-tm-btn--sm"
                onClick={() => onOdebratČlenaSPotvrzením(clen)}
              >
                {t("tym.odebrat")}
              </button>
            </div>
          )}
        </div>
      ))}

      {limit !== null && Array.from({ length: Math.max(0, limit - pocetClenu) }).map((_, i) => {
        const idx = pocetClenu + i;
        const povinné = idx < minKapacita;
        return (
          <div key={`volne-${i}`} class={`gc-tm-member gc-tm-member--empty ${povinné ? "gc-tm-member--required" : ""}`}>
            <span class="gc-tm-member__avatar">+</span>
            <span class="gc-tm-member__name">{t(povinné ? "tym.volne_misto_povinne" : "tym.volne_misto")}</span>
          </div>
        );
      })}

      {!dataTymu?.zamceny && jeKapitán && limit !== null && (
        <div style={{ display: "flex", alignItems: "center", gap: 12, marginTop: 12 }}>
          <span style={{ fontSize: 12.5, color: "var(--ink-3)", fontWeight: 600 }}>{t("tym.volnych_mist")}</span>
          <div class="gc-tm-stepper">
            <button
              disabled={limit <= Math.max(pocetClenu, minKapacita)}
              onClick={() => onNastavLimit(limit - 1)}
            >−</button>
            <span class="gc-tm-stepper__value">{Math.max(0, limit - pocetClenu)}</span>
            <button
              disabled={maxKapacita !== null && limit >= maxKapacita}
              onClick={() => onNastavLimit(limit + 1)}
            >+</button>
          </div>
        </div>
      )}
    </div>
  );

  const tlacitkoZamknout = !dataTymu?.zamceny && jeKapitán && (
    <button
      class="gc-tm-btn gc-tm-btn--primary gc-tm-btn--lg gc-tm-btn--full"
      disabled={pocetClenu < minKapacita}
      onClick={onZamkniTym}
      style={{ marginTop: 18 }}
    >
      {t("tym.zamknout")}
    </button>
  );

  return (
    <div>
      {status}
      {sekceNazev}
      {sekceKod}
      {sekceVerejnost}
      {sekceClenove}
      {tlacitkoZamknout}
    </div>
  );
};

/* ─────────────────────────────────────────────────────────────────────── */

const OdpočetExpiraceAktivity: FunctionComponent<{
  dataTym: ApiAktivitaTym | undefined;
  časExpiraceMs: number | undefined;
  minKapacitaNaplněna: boolean;
}> = ({ dataTym, časExpiraceMs, minKapacitaNaplněna }) => {
  const odpočet = useOdpočet(časExpiraceMs);
  if (!odpočet || odpočet <= 0) return null;

  const smaže = dataTym?.smazatPoExpiraci ?? false;

  return (
    <Alert kind={smaže ? "danger" : "warning"} icon="⏱">
      <div class="gc-tm-alert__title">
        {tSPrvky(smaže ? "tym.odpocet.smazan" : "tym.odpocet.zverejnen", {
          cas: <span class="gc-tm-alert__count">{formatZbývá(odpočet)} h</span>,
        })}
      </div>
      <div class="gc-tm-alert__desc">
        {t("tym.kapitan_musi_zamknout")}{" "}
        {minKapacitaNaplněna ? "" : t("tym.naplnit_minimum")}
      </div>
    </Alert>
  );
};

const formatZbývá = (ms: number): string => {
  if (ms <= 0) return "0:00";
  const h = Math.floor(ms / 3600000);
  const m = Math.floor((ms % 3600000) / 60000);
  return `${h}:${m.toString().padStart(2, "0")}`;
};

const useOdpočet = (casExpiraceMs: number | undefined): number | null => {
  const [zbývá, setZbývá] = useState<number | null>(null);
  useEffect(() => {
    if (!casExpiraceMs) return;
    const update = () => setZbývá(Math.max(0, casExpiraceMs - Date.now()));
    update();
    const id = setInterval(update, 60000);
    return () => clearInterval(id);
  }, [casExpiraceMs]);
  return zbývá;
};

/** První písmena ze jména a příjmení/přezdívky. Vrací max 2 znaky. */
const iniciály = (jmeno: string): string => {
  const ocistene = jmeno.replace(/[„""]/g, "").trim();
  const časti = ocistene.split(/\s+/).filter(Boolean);
  if (časti.length === 0) return "?";
  if (časti.length === 1) return časti[0].slice(0, 2).toUpperCase();
  return (časti[0][0] + časti[časti.length - 1][0]).toUpperCase();
};
