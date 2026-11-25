import { defaultKeymap, history, historyKeymap, redo, undo } from "@codemirror/commands";
import { markdown } from "@codemirror/lang-markdown";
import { syntaxHighlighting } from "@codemirror/language";
import { openSearchPanel, search, searchKeymap } from "@codemirror/search";
import { EditorState, Prec, type StateCommand } from "@codemirror/state";
import {
  drawSelection,
  dropCursor,
  EditorView,
  highlightSpecialChars,
  keymap,
  type Panel,
  showPanel,
} from "@codemirror/view";
import { Nahled } from "./nahled";
import {
  mistaObrazku,
  nazevProNahrani,
  pridejMisto,
  type Soubory,
  souboryZeSchranky,
  souboryZPretazeni,
  vlozNaMisto,
} from "./obrazky";
import { ChybaEditoru, nactiNahled, nahrajObrazek } from "./server";
import {
  CISLOVANY_SEZNAM,
  CITACE,
  NADPIS,
  ODRAZKY,
  prepniObaleni,
  prepniPredponuRadku,
  vlozOdkaz,
} from "./upravy";
import { CESKE_POPISKY, vzhled, zvyrazneni } from "./vzhled";

/**
 * Editor textu v Markdownu nad polem `<textarea data-editor-textu>` (model/dbff-markdown-direct.xtpl). Pole zůstává ve
 * formuláři schované a editor do něj průběžně píše, takže ukládání (AJAX z db-form.xtpl) i upozornění na neuložený
 * text (admin/files/neulozeny-text-editoru.js) fungují beze změny. Text se ukládá přesně tak, jak je napsaný: editor
 * ho nepřevádí, takže HTML, skripty a styly ve starších textech zůstanou, jak jsou.
 */

export type NastaveniEditoru = {
  /** buňka s náhledem vedle pole */
  nahled: HTMLElement | null;
  /** povolené typy obrázků (atribut `accept`), null, když obrázky do tohoto textu vkládat nejde */
  obrazky: string | null;
  /** text si píše uživatel sám (medailonek), náhled z něj ukáže jen vyčištěné HTML */
  odUzivatele: boolean;
};

const NEJDE_VLOZIT_OBRAZEK =
  "Do tohoto textu obrázek vložit nejde. Do nové stránky nebo novinky to půjde, až ji uložíš.";

/** Stavový řádek pod editorem: co se nahrává, co se nepovedlo */
class Stav {
  readonly dom = document.createElement("div");

  constructor(vychozi: string) {
    this.dom.className = "et-stav";
    this.dom.setAttribute("role", "status");
    this.dom.setAttribute("aria-live", "polite");
    this.zobraz(vychozi);
  }

  zobraz(zprava: string, jeChyba = false): void {
    this.dom.textContent = zprava;
    this.dom.classList.toggle("et-stav-chyba", jeChyba);
  }

  chyba(zprava: string): void {
    this.zobraz(zprava, true);
  }

}

const zpravaChyby = (chyba: unknown): string =>
  chyba instanceof ChybaEditoru ? chyba.message : "Něco se pokazilo, zkus to prosím znovu.";

type Tlacitko = {
  ikona: string;
  popis: string;
  prikaz: (view: EditorView) => boolean;
};

const zeStavu = (prikaz: StateCommand) => (view: EditorView): boolean => prikaz(view);

const lista = (view: EditorView, tlacitka: (Tlacitko | "oddelovac" | "mezera")[]): Panel => {
  const dom = document.createElement("div");
  dom.className = "et-lista";
  dom.setAttribute("role", "toolbar");
  dom.setAttribute("aria-label", "Úpravy textu");
  for (const tlacitko of tlacitka) {
    if (tlacitko === "oddelovac" || tlacitko === "mezera") {
      const oddelovac = document.createElement("span");
      oddelovac.className = tlacitko === "oddelovac" ? "et-lista-oddelovac" : "et-lista-mezera";
      dom.append(oddelovac);
      continue;
    }
    const element = document.createElement("button");
    // tlačítko ve formuláři by ho jinak odeslalo
    element.type = "button";
    element.title = tlacitko.popis;
    element.setAttribute("aria-label", tlacitko.popis);
    element.innerHTML = `<i class="fa fa-${tlacitko.ikona}" aria-hidden="true"></i>`;
    // klik nesmí editoru vzít výběr, na který se úprava vztahuje
    element.addEventListener("mousedown", (udalost) => {
      udalost.preventDefault();
    });
    element.addEventListener("click", () => {
      tlacitko.prikaz(view);
      view.focus();
    });
    dom.append(element);
  }

  return { dom, top: true };
};

export const vytvorEditor = (pole: HTMLTextAreaElement, nastaveni: NastaveniEditoru): EditorView => {
  const stav = new Stav(
    nastaveni.obrazky !== null
      ? "Obrázek vložíš i ze schránky (Ctrl+V) nebo přetažením do textu. Ctrl+S text uloží."
      : "Ctrl+S text uloží.",
  );
  const nahled = nastaveni.nahled
    ? new Nahled(
      async (text, signal) => nactiNahled(text, { odUzivatele: nastaveni.odUzivatele, signal }),
      (html) => {
        if (nastaveni.nahled) {
          nastaveni.nahled.innerHTML = html;
        }
      },
      (chyba) => {
        stav.chyba(`Náhled se nepodařilo obnovit: ${zpravaChyby(chyba)}`);
      },
    )
    : null;

  let poradiNahravani = 0;
  let fronta: Promise<void> = Promise.resolve();

  const vlozObrazky = (view: EditorView, soubory: Soubory, pozice: number | null, zeSchranky: boolean): void => {
    if (nastaveni.obrazky === null) {
      stav.chyba(NEJDE_VLOZIT_OBRAZEK);
      return;
    }
    if (soubory.obrazky.length === 0) {
      stav.chyba("Do textu jde vložit jen obrázek. Soubor ke stažení nahraj v bloku Soubory ke stažení pod editorem.");
      return;
    }
    const nahravane = soubory.obrazky.map((soubor) => ({ soubor, nazev: nazevProNahrani(soubor, zeSchranky) }));
    const id = ++poradiNahravani;
    const rozsah = pozice === null ? view.state.selection.main : { from: pozice, to: pozice };
    // vybraný text vložení nahradí hned, obrázky přijdou na jeho místo, až se nahrají
    view.dispatch({
      changes: rozsah.from < rozsah.to ? { from: rozsah.from, to: rozsah.to } : undefined,
      selection: { anchor: rozsah.from },
      effects: pridejMisto.of({
        id,
        pozice: rozsah.from,
        popis: nahravane.length === 1 ? nahravane[0].nazev : `${nahravane.length} obrázky`,
      }),
      scrollIntoView: true,
      userEvent: "input.paste",
    });
    // obrázky se nahrávají po jednom: dva se stejným názvem naráz by si vzaly stejný volný název
    fronta = fronta.then(async () => {
      const zapisy: string[] = [];
      const ulozene: string[] = [];
      const chyby: string[] = [];
      for (const [poradi, { soubor, nazev }] of nahravane.entries()) {
        stav.zobraz(nahravane.length === 1
          ? `Nahrávám ${nazev}…`
          : `Nahrávám ${nazev} (${poradi + 1} z ${nahravane.length})…`);
        try {
          const obrazek = await nahrajObrazek(soubor, nazev);
          zapisy.push(obrazek.markdown);
          ulozene.push(obrazek.cesta);
        } catch (chyba) {
          chyby.push(`${nazev} se nenahrál: ${zpravaChyby(chyba)}`);
        }
      }
      view.dispatch(vlozNaMisto(view.state, id, zapisy.join("\n")));
      if (chyby.length > 0) {
        stav.chyba(chyby.join(" "));
      } else {
        stav.zobraz(`${ulozene.length === 1 ? "Obrázek je nahraný jako" : "Obrázky jsou nahrané jako"} ${ulozene.join(", ")}.`
          + " V seznamu obrázků pod editorem se ukáže po obnovení stránky.");
      }
    });
  };

  const vyberSouboru = document.createElement("input");
  vyberSouboru.type = "file";
  vyberSouboru.multiple = true;
  vyberSouboru.hidden = true;
  vyberSouboru.accept = nastaveni.obrazky ?? "";
  vyberSouboru.addEventListener("change", () => {
    const soubory = souboryZPretazeni(vyberSouboru);
    vyberSouboru.value = "";
    if (soubory) {
      vlozObrazky(view, soubory, null, false);
    }
  });

  const obrazek = (view: EditorView): boolean => {
    if (nastaveni.obrazky === null) {
      // bez nahrávání aspoň zápis obrázku z jiné adresy
      return vlozOdkaz(true)(view);
    }
    vyberSouboru.click();
    return true;
  };

  const ulozit = (): boolean => {
    pole.form?.requestSubmit();
    return true;
  };

  const tucne = zeStavu(prepniObaleni("**"));
  const kurziva = zeStavu(prepniObaleni("_"));
  const odkaz = zeStavu(vlozOdkaz());

  const view = new EditorView({
    state: EditorState.create({
      doc: pole.value,
      extensions: [
        history(),
        mistaObrazku,
        drawSelection(),
        dropCursor(),
        highlightSpecialChars(),
        EditorView.lineWrapping,
        markdown(),
        syntaxHighlighting(zvyrazneni),
        search({ top: true }),
        EditorState.phrases.of(CESKE_POPISKY),
        Prec.high(keymap.of([
          { key: "Mod-b", run: tucne },
          { key: "Mod-i", run: kurziva },
          { key: "Mod-k", run: odkaz },
          { key: "Mod-s", run: ulozit, preventDefault: true },
        ])),
        keymap.of([...defaultKeymap, ...historyKeymap, ...searchKeymap]),
        EditorView.contentAttributes.of({
          "aria-label": "Text v Markdownu",
          spellcheck: "true",
          lang: "cs",
        }),
        EditorView.updateListener.of((update) => {
          if (update.docChanged) {
            const text = update.state.doc.toString();
            pole.value = text;
            nahled?.naplanuj(text);
          }
        }),
        EditorView.domEventHandlers({
          paste(udalost, view) {
            const soubory = souboryZeSchranky(udalost.clipboardData);
            if (!soubory) {
              return false;
            }
            udalost.preventDefault();
            vlozObrazky(view, soubory, null, true);
            return true;
          },
          drop(udalost, view) {
            const soubory = souboryZPretazeni(udalost.dataTransfer);
            if (!soubory) {
              return false;
            }
            udalost.preventDefault();
            vlozObrazky(view, soubory, view.posAtCoords({ x: udalost.clientX, y: udalost.clientY }), false);
            return true;
          },
        }),
        showPanel.of((view) => lista(view, [
          { ikona: "bold", popis: "Tučně (Ctrl+B)", prikaz: tucne },
          { ikona: "italic", popis: "Kurzíva (Ctrl+I)", prikaz: kurziva },
          { ikona: "heading", popis: "Nadpis", prikaz: zeStavu(prepniPredponuRadku(NADPIS)) },
          "oddelovac",
          { ikona: "list-ul", popis: "Odrážky", prikaz: zeStavu(prepniPredponuRadku(ODRAZKY)) },
          { ikona: "list-ol", popis: "Číslovaný seznam", prikaz: zeStavu(prepniPredponuRadku(CISLOVANY_SEZNAM)) },
          { ikona: "quote-right", popis: "Citace", prikaz: zeStavu(prepniPredponuRadku(CITACE)) },
          "oddelovac",
          { ikona: "link", popis: "Odkaz (Ctrl+K)", prikaz: odkaz },
          {
            ikona: "image",
            popis: nastaveni.obrazky !== null
              ? "Nahrát obrázek a vložit ho do textu"
              : "Obrázek z jiné adresy (nahrát sem ho půjde, až text uložíš)",
            prikaz: obrazek,
          },
          "mezera",
          { ikona: "magnifying-glass", popis: "Hledat a nahradit (Ctrl+F)", prikaz: openSearchPanel },
          { ikona: "rotate-left", popis: "Zpět (Ctrl+Z)", prikaz: undo },
          { ikona: "rotate-right", popis: "Znovu (Ctrl+Shift+Z)", prikaz: redo },
        ])),
        showPanel.of(() => ({ dom: stav.dom, top: false })),
        vzhled,
      ],
    }),
  });
  pole.parentElement?.insertBefore(view.dom, pole);
  pole.parentElement?.append(vyberSouboru);
  pole.hidden = true;

  return view;
};

/**
 * Editor nad každým polem s `data-editor-textu` na stránce. Medailonek má pole dvě, skript se pak načte dvakrát
 * (jako modul jen jednou, ale pojistka nevadí), proto si spuštěné pole označí.
 */
export const spustEditoryTextu = (dokument: Document): void => {
  const obrazky = dokument.querySelector<HTMLElement>("[data-editor-textu-obrazky]");
  for (const pole of dokument.querySelectorAll<HTMLTextAreaElement>("textarea[data-editor-textu]")) {
    if (pole.dataset.editorTextuSpusteny !== undefined) {
      continue;
    }
    pole.dataset.editorTextuSpusteny = "";
    try {
      vytvorEditor(pole, {
        nahled: pole.closest("tr")?.querySelector<HTMLElement>("[data-editor-textu-nahled]") ?? null,
        obrazky: obrazky ? obrazky.dataset.editorTextuObrazky ?? "image/*" : null,
        // model/dbff-markdown-od-uzivatele.php
        odUzivatele: pole.dataset.editorTextuOdUzivatele !== undefined,
      });
    } catch (chyba) {
      // bez editoru zůstane obyčejné pole, text jde psát a uložit dál
      pole.hidden = false;
      console.error("Editor textu se nespustil", chyba);
    }
  }
};
