import { ComponentChildren, FunctionComponent } from "preact";
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from "preact/hooks";
import Select, { GroupBase, OptionsOrGroups } from "react-select";
import { ApiFiltrAktivit, ApiFiltrAktivitKarta, ApiRocnikyAktivit } from "../../api/filtrAktivit";
import { distinct } from "../../utils";
import {
  formatOptionLabel, GroupFlex, MultiValueTagy, TagValueLabel,
} from "../program/components/vstupy/FiltrŠtítků";
import {
  filtrujBezŠtítků, filtrujBezVypravěčů, filtrujPodleŠtítků, filtrujPodleVypravěčů, FiltrKaret, filtrZUrl,
  jeFiltrPrázdný, KartaProFiltr, normalizujText, PRÁZDNÝ_FILTR, urlSFiltrem, urlSRočníkem,
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

/** výchozí maxMenuHeight v react-select */
const NEJMENŠÍ_VÝŠKA_MENU = 300;

/**
 * Menu výběru až ke spodku okna. Kdo vybírá štítky, stejně nekouká na aktivity pod ním.
 * Výška se počítá podle toho, kde zrovna výběr na stránce je, a znovu po každém vykreslení,
 * dokud je menu otevřené: s dalšími vybranými štítky výběr naroste o řádek a menu sjede níž.
 */
const useMenuDoSpodkuOkna = () => {
  const obal = useRef<HTMLDivElement>(null);
  const [otevřené, setOtevřené] = useState(false);
  const [výška, setVýška] = useState(NEJMENŠÍ_VÝŠKA_MENU);

  useLayoutEffect(() => {
    const spodekVýběru = obal.current?.getBoundingClientRect().bottom;
    if (!otevřené || spodekVýběru === undefined) return;
    // odsazení menu od výběru a kousek místa pod ním
    const nováVýška = Math.max(NEJMENŠÍ_VÝŠKA_MENU, Math.floor(window.innerHeight - spodekVýběru - 24));
    // bez porovnání by se vykreslovalo dokola
    if (nováVýška !== výška) setVýška(nováVýška);
  });

  return {
    obal,
    výška,
    otevři: () => {
      setOtevřené(true);
    },
    zavři: () => {
      setOtevřené(false);
    },
  };
};

type RočníkValueLabel = { value: number, label: string };

type TPřepínačRočníkuProps = {
  ročníky: ApiRocnikyAktivit;
};

/** jiný ročník načte PHP, výběr proto znovu načte stránku */
const PřepínačRočníku: FunctionComponent<TPřepínačRočníkuProps> = (props) => {
  const { ročníky } = props;
  const možnosti = ročníky.rocniky.map((rok): RočníkValueLabel => ({ value: rok, label: rok.toString() }));

  return (
    <div class="aktivityFiltr_rocnik">
      <Select<RočníkValueLabel>
        aria-label="Ročník"
        options={možnosti}
        value={možnosti.find((možnost) => možnost.value === ročníky.rocnik)}
        onChange={(vybraný) => {
          if (vybraný && vybraný.value !== ročníky.rocnik) {
            window.location.assign(urlSRočníkem(window.location.href, vybraný.value, ročníky.letosni));
          }
        }}
        formatOptionLabel={(možnost, { context }) =>
          context === "value" ? `Ročník ${možnost.label}` : možnost.label}
        noOptionsMessage={() => "Takový ročník tu nebyl"}
      />
    </div>
  );
};

/** kdo přijde odkazem na loňské aktivity, musí to poznat i bez pohledu na přepínač */
const MinulýRočník: FunctionComponent<TPřepínačRočníkuProps> = (props) => {
  const { ročníky } = props;
  if (ročníky.rocnik === ročníky.letosni) return null;

  return (
    <div class="aktivityFiltr_souhrn">
      {`Aktivity z ročníku ${ročníky.rocnik}`}
      <a
        class="aktivityFiltr_zrusit"
        href={urlSRočníkem(window.location.href, ročníky.letosni, ročníky.letosni)}
        onClick={(e) => {
          // adresa až teď, od vykreslení se v ní mohl změnit filtr (replaceState) a odkaz se znovu nevykreslí
          e.preventDefault();
          window.location.assign(urlSRočníkem(window.location.href, ročníky.letosni, ročníky.letosni));
        }}
      >
        Zpět na letošní
      </a>
    </div>
  );
};

type TAktivityFiltrSDatyProps = {
  data: ApiFiltrAktivit;
  přepínačRočníku?: ComponentChildren;
  minulýRočník?: ComponentChildren;
};

const AktivityFiltrSDaty: FunctionComponent<TAktivityFiltrSDatyProps> = (props) => {
  const { data, přepínačRočníku, minulýRočník } = props;

  const karty = useMemo(() => načtiKarty(data.karty), [data]);
  const kategorieŠtítků = useMemo(
    () => new Map(data.tagy.map((tag) => [tag.id, tag.kategorie])),
    [data],
  );
  // lišta je i u pár karet, jediný den by ale nic nezúžil
  const nabídnoutDny = data.dny.length > 1;

  const [filtr, setFiltr] = useState<FiltrKaret>(() => {
    // odkaz z jiné linie nebo z loňska: co na stránce není, by v liště nešlo vypnout
    const zUrl = filtrZUrl(window.location.href);
    return {
      ...zUrl,
      štítky: zUrl.štítky.filter((štítek) => kategorieŠtítků.has(štítek)),
      vypravěči: zUrl.vypravěči.filter((vypravěč) => data.vypraveci.some((známý) => známý.id === vypravěč)),
      dny: nabídnoutDny
        ? zUrl.dny.filter((den) => data.dny.some((známý) => známý.klic === den))
        : [],
      jenVolné: zUrl.jenVolné && data.lzePrihlasovat,
    };
  });
  const [seznam, setSeznam] = useState(načtiZobrazeníSeznamem);

  const kartyBezŠtítků = useMemo(() => filtrujBezŠtítků(karty, filtr), [karty, filtr]);
  const kartyBezVypravěčů = useMemo(
    () => filtrujBezVypravěčů(karty, filtr, kategorieŠtítků),
    [karty, filtr, kategorieŠtítků],
  );
  const zobrazené = useMemo(
    () => filtrujPodleŠtítků(kartyBezŠtítků, filtr.štítky, kategorieŠtítků),
    [kartyBezŠtítků, filtr.štítky, kategorieŠtítků],
  );
  const menuŠtítků = useMenuDoSpodkuOkna();
  const menuVypravěčů = useMenuDoSpodkuOkna();

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

  const možnostiVypravěčů = useMemo(
    () => data.vypraveci.map((vypravěč): TagValueLabel => {
      // s už vybraným vypravěčem další karty přidává, jinak vybírá z toho, co je vidět
      return filtr.vypravěči.length
        ? {
          value: vypravěč.id,
          label: vypravěč.jmeno,
          početMožností: filtrujPodleVypravěčů(kartyBezVypravěčů, filtr.vypravěči.concat(vypravěč.id)).length
            - zobrazené.length,
          početMožnostíNavíc: true,
        }
        : {
          value: vypravěč.id,
          label: vypravěč.jmeno,
          početMožností: filtrujPodleVypravěčů(kartyBezVypravěčů, [vypravěč.id]).length,
        };
    }),
    [data, kartyBezVypravěčů, filtr.vypravěči, zobrazené.length],
  );
  // bez počtu, ten patří jen do nabídky
  const vybraníVypravěči = useMemo(
    () => data.vypraveci
      .filter((vypravěč) => filtr.vypravěči.includes(vypravěč.id))
      .map((vypravěč): TagValueLabel => ({ value: vypravěč.id, label: vypravěč.jmeno })),
    [data, filtr.vypravěči],
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
        {data.tagy.length > 0 && (
          <div class="aktivityFiltr_stitky" ref={menuŠtítků.obal}>
            <Select<TagValueLabel, true>
              placeholder="Štítky (žánr, věk, …)"
              aria-label="Štítky"
              options={možnostiŠtítků}
              isMulti
              closeMenuOnSelect={false}
              maxMenuHeight={menuŠtítků.výška}
              onMenuOpen={menuŠtítků.otevři}
              onMenuClose={menuŠtítků.zavři}
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
        )}
      </div>

      <div class="aktivityFiltr_radek">
        {přepínačRočníku}
        {data.vypraveci.length > 1 && (
          <div class="aktivityFiltr_vypravec" ref={menuVypravěčů.obal}>
            <Select<TagValueLabel, true>
              placeholder="Vypravěči"
              aria-label="Vypravěči"
              options={možnostiVypravěčů}
              isMulti
              closeMenuOnSelect={false}
              maxMenuHeight={menuVypravěčů.výška}
              onMenuOpen={menuVypravěčů.otevři}
              onMenuClose={menuVypravěčů.zavři}
              value={vybraníVypravěči}
              onChange={(vybraní) => {
                nastav({ vypravěči: vybraní.map((x) => x.value) });
              }}
              formatOptionLabel={formatOptionLabel}
              noOptionsMessage={() => "Žádný takový vypravěč"}
              classNamePrefix="aktivityFiltrVypravec"
              styles={{
                // ve sloupcích jako štítky, jinak by jich bylo vidět jen pár pod sebou (šířka menu v AktivityFiltr.less)
                menuList(base) {
                  return { ...base, display: "flex", flexWrap: "wrap" };
                },
                option(base) {
                  return { ...base, width: "180px" };
                },
              }}
            />
          </div>
        )}
        {nabídnoutDny && (
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
        )}
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

      {minulýRočník}
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
  const ročníky = window.gameconPřednačtení?.rocnikyAktivit;
  const přepínačRočníku = ročníky && <PřepínačRočníku ročníky={ročníky} />;
  const minulýRočník = ročníky && <MinulýRočník ročníky={ročníky} />;

  if (data) {
    return <AktivityFiltrSDaty data={data} přepínačRočníku={přepínačRočníku} minulýRočník={minulýRočník} />;
  }

  // u jediné aktivity bez filtrů, ale k loňským se jde dostat i tady
  return ročníky
    ? (
      <div class="aktivityFiltr">
        <div class="aktivityFiltr_radek">{přepínačRočníku}</div>
        {minulýRočník}
      </div>
    )
    : null;
};

AktivityFiltr.displayName = "AktivityFiltr";
