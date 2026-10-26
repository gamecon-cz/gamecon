import { FunctionComponent } from "preact";
import { useEffect, useLayoutEffect, useMemo, useState } from "preact/hooks";
import Select, { GroupBase, OptionsOrGroups } from "react-select";
import { ApiFiltrAktivit, ApiFiltrAktivitKarta } from "../../api/filtrAktivit";
import { distinct } from "../../utils";
import {
  formatOptionLabel, GroupFlex, MultiValueTagy, TagValueLabel,
} from "../program/components/vstupy/FiltrŠtítků";
import {
  filtrujBezŠtítků, filtrujPodleŠtítků, FiltrKaret, filtrZUrl, jeFiltrPrázdný, KartaProFiltr, normalizujText,
  PRÁZDNÝ_FILTR, urlSFiltrem,
} from "./filtr";

import "../program/components/vstupy/ReactSelect.less";
import "./AktivityFiltr.less";

/*
 * Filtry nad seznamem aktivit na stránce linie. Karty vykresluje PHP
 * (web/moduly/aktivity.php), tady se jen schovávají a přepíná jejich vzhled.
 */

type KartaSPrvkem = KartaProFiltr & { prvek: HTMLElement | null };

const načtiKarty = (karty: ApiFiltrAktivitKarta[]): KartaSPrvkem[] =>
  karty.map((karta) => {
    const prvek = document.getElementById(karta.htmlId);
    // název, štítky a celý popis jsou v detailu aktivity, vypravěč jen na kartě
    const text = [
      document.getElementById(`aktivity_nahledText${karta.id}`)?.textContent,
      prvek?.querySelector(".aktivity_vypravec")?.textContent,
    ].join(" ");

    return { ...karta, text: normalizujText(text), prvek };
  });

const ZOBRAZENÍ_STORAGE_KEY = "aktivity_zobrazeni";

const načtiZobrazeníSeznamem = (): boolean => {
  try {
    return localStorage.getItem(ZOBRAZENÍ_STORAGE_KEY) === "seznam";
  } catch {
    return false;
  }
};

const uložZobrazeníSeznamem = (seznam: boolean) => {
  try {
    localStorage.setItem(ZOBRAZENÍ_STORAGE_KEY, seznam ? "seznam" : "karty");
  } catch {
    // bez úložiště se volba jen nezapamatuje
  }
};

const třídaTlačítka = (aktivní: boolean) =>
  "aktivityFiltr_tlacitko" + (aktivní ? " aktivni" : "");

type TAktivityFiltrSDatyProps = {
  data: ApiFiltrAktivit;
};

const AktivityFiltrSDaty: FunctionComponent<TAktivityFiltrSDatyProps> = (props) => {
  const { data } = props;

  const karty = useMemo(() => načtiKarty(data.karty), [data]);
  const kategorieŠtítků = useMemo(
    () => new Map(data.tagy.map((tag) => [tag.id, tag.kategorie])),
    [data],
  );

  const [filtr, setFiltr] = useState<FiltrKaret>(() => {
    // odkaz z jiné linie nebo z loňska: co na stránce není, by v liště nešlo vypnout
    const zUrl = filtrZUrl(window.location.href);
    return {
      ...zUrl,
      štítky: zUrl.štítky.filter((štítek) => kategorieŠtítků.has(štítek)),
      dny: zUrl.dny.filter((den) => data.dny.some((známý) => známý.klic === den)),
      jenVolné: zUrl.jenVolné && data.lzePrihlasovat,
    };
  });
  const [seznam, setSeznam] = useState(načtiZobrazeníSeznamem);

  const kartyBezŠtítků = useMemo(() => filtrujBezŠtítků(karty, filtr), [karty, filtr]);
  const zobrazené = useMemo(
    () => filtrujPodleŠtítků(kartyBezŠtítků, filtr.štítky, kategorieŠtítků),
    [kartyBezŠtítků, filtr.štítky, kategorieŠtítků],
  );

  // layout effect běží hned při vykreslení, tedy před DOMContentLoaded, kdy se obnovuje scroll (zachovejScroll)
  useLayoutEffect(() => {
    const zobrazenéId = new Set(zobrazené.map((karta) => karta.htmlId));
    for (const karta of karty) {
      if (karta.prvek) karta.prvek.hidden = !zobrazenéId.has(karta.htmlId);
    }
  }, [karty, zobrazené]);

  useLayoutEffect(() => {
    document.querySelector(".aktivity_aktivity")?.classList.toggle("aktivity_aktivity-seznam", seznam);
  }, [seznam]);

  useEffect(() => {
    window.history.replaceState(window.history.state, "", urlSFiltrem(window.location.href, filtr));
  }, [filtr]);

  const nastav = (změna: Partial<FiltrKaret>) => {
    setFiltr((předchozí) => ({ ...předchozí, ...změna }));
  };

  const přepniDen = (den: string) => {
    nastav({
      dny: filtr.dny.includes(den)
        ? filtr.dny.filter((vybraný) => vybraný !== den)
        : filtr.dny.concat(den),
    });
  };

  const přepniZobrazení = (naSeznam: boolean) => {
    setSeznam(naSeznam);
    uložZobrazeníSeznamem(naSeznam);
  };

  const možnostiŠtítků: OptionsOrGroups<TagValueLabel, GroupBase<TagValueLabel>> = useMemo(
    () => distinct(data.tagy.map((tag) => tag.kategorie)).map((kategorie) => ({
      label: kategorie,
      options: data.tagy
        .filter((tag) => tag.kategorie === kategorie)
        .map((tag): TagValueLabel => {
          const počet = filtrujPodleŠtítků(kartyBezŠtítků, filtr.štítky.concat(tag.id), kategorieŠtítků).length;
          // jako v programu: když štítek výběr rozšíří (další z už vybrané kategorie), ukáže se, kolik karet přibude
          return počet >= zobrazené.length
            ? { value: tag.id, label: tag.nazev, početMožností: počet - zobrazené.length, početMožnostíNavíc: true }
            : { value: tag.id, label: tag.nazev, početMožností: počet };
        }),
    })),
    [data, kartyBezŠtítků, filtr.štítky, kategorieŠtítků, zobrazené.length],
  );

  // vybrané štítky po kategoriích, název kategorie jako popisek před nimi (MultiValueTagy)
  const vybranéŠtítky = useMemo(
    () => distinct(data.tagy.map((tag) => tag.kategorie)).flatMap((kategorie) => {
      const vybrané = data.tagy.filter((tag) => tag.kategorie === kategorie && filtr.štítky.includes(tag.id));
      if (!vybrané.length) return [];
      const názevKategorie: TagValueLabel = { value: -1, label: kategorie, jeNázevKategorie: true };

      return [názevKategorie].concat(vybrané.map((tag) => ({ value: tag.id, label: tag.nazev })));
    }),
    [data, filtr.štítky],
  );

  return (
    <div class="aktivityFiltr">
      <div class="aktivityFiltr_radek">
        <input
          class="aktivityFiltr_hledat"
          type="search"
          placeholder="Hledej v názvu, popisu, vypravěčích…"
          aria-label="Hledat v aktivitách"
          value={filtr.text}
          onInput={(e) => {
            nastav({ text: e.currentTarget.value });
          }}
        />
        <div class="aktivityFiltr_stitky">
          <Select<TagValueLabel, true>
            placeholder="Štítky (žánr, věk, …)"
            aria-label="Štítky"
            options={možnostiŠtítků}
            isMulti
            closeMenuOnSelect={false}
            value={vybranéŠtítky}
            onChange={(vybrané) => {
              nastav({ štítky: vybrané.filter((x) => !x.jeNázevKategorie).map((x) => x.value) });
            }}
            components={{
              MultiValue: MultiValueTagy,
              Group: GroupFlex,
            }}
            formatOptionLabel={formatOptionLabel}
            noOptionsMessage={() => "Žádný takový štítek"}
            styles={{
              option(base) {
                return {
                  ...base,
                  minWidth: "180px",
                  maxWidth: "180px",
                };
              },
            }}
          />
        </div>
      </div>

      <div class="aktivityFiltr_radek">
        <div class="aktivityFiltr_skupina">
          {data.dny.map((den) => (
            <button
              key={den.klic}
              type="button"
              class={třídaTlačítka(filtr.dny.includes(den.klic))}
              aria-pressed={filtr.dny.includes(den.klic)}
              onClick={() => {
                přepniDen(den.klic);
              }}
            >
              {den.nazev}
            </button>
          ))}
        </div>
        {data.lzePrihlasovat && (
          <button
            type="button"
            class={třídaTlačítka(filtr.jenVolné)}
            aria-pressed={filtr.jenVolné}
            onClick={() => {
              nastav({ jenVolné: !filtr.jenVolné });
            }}
          >
            Jen volná místa
          </button>
        )}
        <div class="aktivityFiltr_skupina aktivityFiltr_zobrazeni">
          <button
            type="button"
            class={třídaTlačítka(!seznam)}
            aria-pressed={!seznam}
            onClick={() => {
              přepniZobrazení(false);
            }}
          >
            Karty
          </button>
          <button
            type="button"
            class={třídaTlačítka(seznam)}
            aria-pressed={seznam}
            onClick={() => {
              přepniZobrazení(true);
            }}
          >
            Seznam
          </button>
        </div>
      </div>

      {!jeFiltrPrázdný(filtr) && (
        <div class="aktivityFiltr_souhrn" role="status">
          {zobrazené.length
            ? `Zobrazeno ${zobrazené.length} z ${karty.length}`
            : "Filtrům neodpovídá žádná aktivita."}
          <button
            type="button"
            class="aktivityFiltr_zrusit"
            onClick={() => {
              setFiltr(PRÁZDNÝ_FILTR);
            }}
          >
            Zrušit filtry
          </button>
        </div>
      )}
    </div>
  );
};

export const AktivityFiltr: FunctionComponent = () => {
  const data = window.gameconPřednačtení?.filtrAktivit;

  return data ? <AktivityFiltrSDaty data={data} /> : null;
};

AktivityFiltr.displayName = "AktivityFiltr";
