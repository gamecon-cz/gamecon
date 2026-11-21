import { FunctionComponent } from "preact";
import { useEffect, useLayoutEffect, useMemo, useState } from "preact/hooks";
import Select, { GroupBase, OptionsOrGroups } from "react-select";
import { ApiArchivPrednasek, ApiArchivPrednaska } from "../../api/archivPrednasek";
import { distinct } from "../../utils";
import { formatOptionLabel, GroupFlex, TagValueLabel } from "../program/components/vstupy/FiltrŠtítků";
import { normalizujText } from "../filtrStranky/text";
import { useMenuDoSpodkuOkna } from "../filtrStranky/useMenuDoSpodkuOkna";
import {
  filtrArchivuZUrl, FiltrArchivu, filtrJenSeZnámýmiHodnotami, filtrujPřednášky, jeFiltrArchivuPrázdný, PočetMožnosti,
  počtemPřednášek, počtyMožností, PRÁZDNÝ_FILTR_ARCHIVU, PřednáškaProFiltr, sPřednášejícím, urlSFiltremArchivu,
} from "./filtr";

import "../program/components/vstupy/ReactSelect.less";
import "../aktivityFiltr/AktivityFiltr.less";
import "./ArchivPrednasek.less";

/*
 * Filtry archivu přednášek (/prednasky/archiv). Karty vykresluje PHP (web/moduly/archivprednasek.php),
 * tady se jen schovávají. Navíc klik na jméno přednášejícího nastaví filtr a klik na náhled pustí video.
 */

type PřednáškaSPrvkem = PřednáškaProFiltr & { prvek: HTMLElement | null };

const načtiPřednášky = (přednášky: ApiArchivPrednaska[]): PřednáškaSPrvkem[] =>
  přednášky.map((přednáška) => {
    const prvek = document.getElementById(přednáška.htmlId);
    // hledá se v názvu, krátké i dlouhé anotaci (záměr), text je v kartě, ne podruhé v JSON
    const text = [
      prvek?.querySelector(".archivPrednasek_nazev")?.textContent,
      prvek?.querySelector(".archivPrednasek_anotace")?.textContent,
      prvek?.querySelector(".archivPrednasek_dlouhaAnotace .stranka_format")?.textContent,
    ].join(" ");

    return { ...přednáška, text: normalizujText(text), prvek };
  });

/** odkaz otevíraný s klávesou nebo prostředním tlačítkem patří prohlížeči (nová záložka) */
const jeObycejnyKlik = (událost: MouseEvent): boolean =>
  událost.button === 0 && !událost.ctrlKey && !událost.metaKey && !událost.shiftKey && !událost.altKey;

/** přehrávač z youtube-nocookie.com až po kliknutí, do té doby se k YouTube nesahá víc než pro náhled */
const pustVideo = (náhled: HTMLAnchorElement) => {
  const adresa = náhled.dataset.prehravac;
  if (!adresa) return;
  const přehrávač = document.createElement("iframe");
  přehrávač.className = "archivPrednasek_prehravac";
  přehrávač.src = adresa;
  přehrávač.title = náhled.getAttribute("aria-label") ?? "Záznam přednášky";
  přehrávač.allow = "autoplay; encrypted-media; picture-in-picture; fullscreen";
  přehrávač.allowFullscreen = true;
  přehrávač.referrerPolicy = "strict-origin-when-cross-origin";
  náhled.replaceWith(přehrávač);
  přehrávač.focus();
};

const sPočtem = (možnost: TagValueLabel, počet: PočetMožnosti | undefined): TagValueLabel =>
  počet === undefined
    ? možnost
    : { ...možnost, početMožností: počet.počet, početMožnostíNavíc: počet.navíc || undefined };

type TArchivPrednasekFiltrSDatyProps = {
  data: ApiArchivPrednasek;
};

const ArchivPrednasekFiltrSDaty: FunctionComponent<TArchivPrednasekFiltrSDatyProps> = (props) => {
  const { data } = props;

  const přednášky = useMemo(() => načtiPřednášky(data.prednasky), [data]);
  const sekceRoků = useMemo(
    () => Array.from(document.querySelectorAll<HTMLElement>(".archivPrednasek_rok")),
    [],
  );

  const [filtr, setFiltr] = useState<FiltrArchivu>(
    () => filtrJenSeZnámýmiHodnotami(filtrArchivuZUrl(window.location.href), data),
  );
  const zobrazené = useMemo(() => filtrujPřednášky(přednášky, filtr), [přednášky, filtr]);
  const menuŠtítků = useMenuDoSpodkuOkna();
  const menuPřednášejících = useMenuDoSpodkuOkna();
  const menuRoků = useMenuDoSpodkuOkna();

  // před prvním vykreslením prohlížeče, ať karty mimo filtr z odkazu neproblikly
  useLayoutEffect(() => {
    const zobrazenéId = new Set(zobrazené.map((přednáška) => přednáška.htmlId));
    for (const přednáška of přednášky) {
      if (přednáška.prvek) přednáška.prvek.hidden = !zobrazenéId.has(přednáška.htmlId);
    }
    // rok bez jediné zobrazené přednášky by zůstal jako prázdný nadpis
    for (const sekce of sekceRoků) {
      sekce.hidden = !sekce.querySelector(".archivPrednasek_prednaska:not([hidden])");
    }
  }, [přednášky, zobrazené, sekceRoků]);

  useEffect(() => {
    window.history.replaceState(window.history.state, "", urlSFiltremArchivu(window.location.href, filtr));
  }, [filtr]);

  // jména přednášejících a náhledy videí jsou v kartách z PHP
  useEffect(() => {
    const naKlik = (událost: MouseEvent) => {
      if (!jeObycejnyKlik(událost) || !(událost.target instanceof Element)) return;
      const náhled = událost.target.closest<HTMLAnchorElement>("a.archivPrednasek_nahled");
      if (náhled) {
        událost.preventDefault();
        pustVideo(náhled);
        return;
      }
      const jméno = událost.target.closest<HTMLAnchorElement>("a[data-prednasejici]");
      if (jméno) {
        událost.preventDefault();
        const id = Number(jméno.dataset.prednasejici);
        setFiltr((předchozí) => sPřednášejícím(předchozí, id));
        // výsledek je nahoře u lišty, karta pod kurzorem se možná schová
        const lišta = document.getElementById("preact-archiv-prednasek");
        if (lišta && lišta.getBoundingClientRect().top < 0) lišta.scrollIntoView({ behavior: "smooth" });
      }
    };
    document.addEventListener("click", naKlik);

    return () => {
      document.removeEventListener("click", naKlik);
    };
  }, []);

  const nastav = (změna: Partial<FiltrArchivu>) => {
    setFiltr((předchozí) => ({ ...předchozí, ...změna }));
  };

  const možnostiRoků = useMemo(() => {
    const počty = počtyMožností(přednášky, filtr, "roky", data.roky);

    return data.roky.map((rok) => sPočtem({ value: rok, label: rok.toString() }, počty.get(rok)));
  }, [data, přednášky, filtr]);

  const možnostiPřednášejících = useMemo(() => {
    const počty = počtyMožností(přednášky, filtr, "přednášející", data.prednasejici.map((přednášející) => přednášející.id));

    return data.prednasejici.map((přednášející) =>
      sPočtem({ value: přednášející.id, label: přednášející.jmeno }, počty.get(přednášející.id))
    );
  }, [data, přednášky, filtr]);

  // react-select chce u možností čísla, štítek je pořadí v data.stitky
  const možnostiŠtítků: OptionsOrGroups<TagValueLabel, GroupBase<TagValueLabel>> = useMemo(() => {
    const počty = počtyMožností(přednášky, filtr, "štítky", data.stitky.map((štítek) => štítek.klic));

    return distinct(data.stitky.map((štítek) => štítek.skupina)).map((skupina) => ({
      label: skupina,
      options: data.stitky.flatMap((štítek, pořadí) => štítek.skupina === skupina
        ? [sPočtem({ value: pořadí, label: štítek.nazev }, počty.get(štítek.klic))]
        : []),
    }));
  }, [data, přednášky, filtr]);

  // vybrané bez počtu, ten patří jen do nabídky
  const vybranéRoky = data.roky
    .filter((rok) => filtr.roky.includes(rok))
    .map((rok): TagValueLabel => ({ value: rok, label: rok.toString() }));
  const vybraníPřednášející = data.prednasejici
    .filter((přednášející) => filtr.přednášející.includes(přednášející.id))
    .map((přednášející): TagValueLabel => ({ value: přednášející.id, label: přednášející.jmeno }));
  const vybranéŠtítky = data.stitky
    .flatMap((štítek, pořadí): TagValueLabel[] => filtr.štítky.includes(štítek.klic)
      ? [{ value: pořadí, label: štítek.nazev }]
      : []);

  return (
    <div class="aktivityFiltr archivPrednasekFiltr">
      <div class="aktivityFiltr_radek">
        <input
          class="aktivityFiltr_hledat"
          type="search"
          placeholder="Hledej v názvu a anotaci…"
          aria-label="Hledat v přednáškách"
          value={filtr.text}
          onInput={(e) => {
            nastav({ text: e.currentTarget.value });
          }}
        />
        {data.stitky.length > 0 && (
          <div class="aktivityFiltr_stitky" ref={menuŠtítků.obal}>
            <Select<TagValueLabel, true>
              placeholder="Štítky a klíčová slova"
              aria-label="Štítky a klíčová slova"
              options={možnostiŠtítků}
              isMulti
              closeMenuOnSelect={false}
              maxMenuHeight={menuŠtítků.výška}
              onMenuOpen={menuŠtítků.otevři}
              onMenuClose={menuŠtítků.zavři}
              value={vybranéŠtítky}
              onChange={(vybrané) => {
                nastav({ štítky: vybrané.map((vybraný) => data.stitky[vybraný.value].klic) });
              }}
              components={{ Group: GroupFlex }}
              formatOptionLabel={formatOptionLabel}
              noOptionsMessage={() => "Žádný takový štítek"}
              styles={{
                option(base) {
                  return { ...base, minWidth: "180px", maxWidth: "180px" };
                },
              }}
            />
          </div>
        )}
      </div>

      <div class="aktivityFiltr_radek">
        <div class="archivPrednasekFiltr_roky" ref={menuRoků.obal}>
          <Select<TagValueLabel, true>
            placeholder="Ročníky"
            aria-label="Ročníky"
            options={možnostiRoků}
            isMulti
            closeMenuOnSelect={false}
            maxMenuHeight={menuRoků.výška}
            onMenuOpen={menuRoků.otevři}
            onMenuClose={menuRoků.zavři}
            value={vybranéRoky}
            onChange={(vybrané) => {
              nastav({ roky: vybrané.map((vybraný) => vybraný.value) });
            }}
            formatOptionLabel={formatOptionLabel}
            noOptionsMessage={() => "Takový ročník v archivu není"}
          />
        </div>
        <div class="aktivityFiltr_vypravec" ref={menuPřednášejících.obal}>
          <Select<TagValueLabel, true>
            placeholder="Přednášející"
            aria-label="Přednášející"
            options={možnostiPřednášejících}
            isMulti
            closeMenuOnSelect={false}
            maxMenuHeight={menuPřednášejících.výška}
            onMenuOpen={menuPřednášejících.otevři}
            onMenuClose={menuPřednášejících.zavři}
            value={vybraníPřednášející}
            onChange={(vybraní) => {
              nastav({ přednášející: vybraní.map((vybraný) => vybraný.value) });
            }}
            formatOptionLabel={formatOptionLabel}
            noOptionsMessage={() => "Žádný takový přednášející"}
            classNamePrefix="aktivityFiltrVypravec"
            styles={{
              // ve sloupcích jako na stránce linie (šířka menu v AktivityFiltr.less)
              menuList(base) {
                return { ...base, display: "flex", flexWrap: "wrap" };
              },
              option(base) {
                return { ...base, width: "180px" };
              },
            }}
          />
        </div>
        <label class="archivPrednasekFiltr_zaznam">
          <input
            type="checkbox"
            checked={filtr.jenSeZáznamem}
            onChange={(e) => {
              nastav({ jenSeZáznamem: e.currentTarget.checked });
            }}
          />
          Jen se záznamem
        </label>
      </div>

      <div class="aktivityFiltr_souhrn" role="status">
        {jeFiltrArchivuPrázdný(filtr)
          ? `${počtemPřednášek(přednášky.length)} v archivu`
          : zobrazené.length
            ? `Zobrazeno ${zobrazené.length} z ${počtemPřednášek(přednášky.length)}`
            : "Filtrům neodpovídá žádná přednáška."}
        {!jeFiltrArchivuPrázdný(filtr) && (
          <button
            type="button"
            class="aktivityFiltr_zrusit"
            onClick={() => {
              setFiltr(PRÁZDNÝ_FILTR_ARCHIVU);
            }}
          >
            Zrušit filtry
          </button>
        )}
      </div>
    </div>
  );
};

export const ArchivPrednasekFiltr: FunctionComponent = () => {
  const data = window.gameconPřednačtení?.archivPrednasek;

  return data ? <ArchivPrednasekFiltrSDaty data={data} /> : null;
};

ArchivPrednasekFiltr.displayName = "ArchivPrednasekFiltr";
