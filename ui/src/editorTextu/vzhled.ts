import { HighlightStyle } from "@codemirror/language";
import { EditorView } from "@codemirror/view";
import { tags } from "@lezer/highlight";

/**
 * Vzhled editoru. Barvy bere z proměnných moderního vzhledu adminu (`--m-*`, admin/files/design/moderni.css), klasický
 * vzhled je nemá a dostane světlé náhradní hodnoty. Tmavé barvy zapíná třída `moderni-tmave` na `<html>`
 * (admin/files/barvy_adminu.js), ne `prefers-color-scheme`: volba patří adminu, ne systému.
 * Styly vkládá CodeMirror sám, editor je jediný soubor bez CSS (style.css patří programu).
 */

const TMAVE = ".moderni-tmave &";

/** Třídy místo barev ve stylu, ať je tmavé barvy můžou přebarvit */
export const zvyrazneni = HighlightStyle.define([
  { tag: tags.heading, class: "et-nadpis" },
  { tag: tags.strong, class: "et-tucne" },
  { tag: tags.emphasis, class: "et-kurziva" },
  { tag: tags.strikethrough, class: "et-preskrtnute" },
  { tag: [tags.link, tags.url], class: "et-odkaz" },
  { tag: [tags.processingInstruction, tags.contentSeparator, tags.labelName], class: "et-znacka" },
  { tag: tags.monospace, class: "et-kod" },
  { tag: tags.quote, class: "et-citace" },
  { tag: [tags.tagName, tags.angleBracket], class: "et-html-znacka" },
  { tag: tags.attributeName, class: "et-html-atribut" },
  { tag: [tags.attributeValue, tags.string], class: "et-html-hodnota" },
  { tag: tags.keyword, class: "et-klicove-slovo" },
  { tag: tags.comment, class: "et-komentar" },
  { tag: tags.character, class: "et-entita" },
]);

const barvy = (svetla: string, tmava: string) => ({ svetla, tmava });

const BARVY = {
  nadpis: barvy("#1d4ed8", "#93b4ff"),
  odkaz: barvy("#0f766e", "#5eead4"),
  znacka: barvy("#8a93a3", "#7c8596"),
  kod: barvy("#9a3412", "#fdba74"),
  citace: barvy("#5b6370", "#a3abb8"),
  htmlZnacka: barvy("#7e22ce", "#d8b4fe"),
  htmlAtribut: barvy("#a16207", "#fcd34d"),
  htmlHodnota: barvy("#15803d", "#86efac"),
  komentar: barvy("#6b7280", "#8b93a3"),
  chyba: barvy("#b42318", "#ff9b8f"),
};

const obarvi = (trida: string, barva: { svetla: string; tmava: string }) => ({
  [`.${trida}`]: { color: barva.svetla },
  [`${TMAVE} .${trida}`]: { color: barva.tmava },
});

export const vzhled = EditorView.theme({
  "&": {
    border: "1px solid var(--m-okraj-pole, #b9bec7)",
    borderRadius: "6px",
    backgroundColor: "var(--m-pole, #fff)",
    color: "var(--m-text, #1f2328)",
    fontSize: "13px",
  },
  "&.cm-focused": {
    outline: "2px solid var(--m-fokus, #2563eb)",
    outlineOffset: "-1px",
  },
  ".cm-scroller": {
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace",
    lineHeight: "1.55",
  },
  ".cm-content, .cm-gutter": { minHeight: "20em" },
  ".cm-content": { padding: "8px 0", caretColor: "var(--m-text, #1f2328)" },
  ".cm-line": { padding: "0 10px" },
  "&.cm-focused .cm-selectionBackground, .cm-selectionBackground, ::selection": {
    backgroundColor: "var(--m-radek-zvyrazneny, #d7e4ff)",
  },
  ".cm-panels": {
    backgroundColor: "var(--m-plocha, #f6f7f9)",
    color: "var(--m-text, #1f2328)",
  },
  ".cm-panels-top": { borderBottom: "1px solid var(--m-okraj, #e2e5ea)", borderRadius: "6px 6px 0 0" },
  ".cm-panels-bottom": { borderTop: "1px solid var(--m-okraj, #e2e5ea)", borderRadius: "0 0 6px 6px" },
  ".cm-search input, .cm-search button, .cm-search label": { fontSize: "12px" },

  ".et-lista": {
    display: "flex",
    flexWrap: "wrap",
    alignItems: "center",
    gap: "2px",
    padding: "4px 6px",
  },
  ".et-lista-oddelovac": {
    width: "1px",
    alignSelf: "stretch",
    margin: "2px 4px",
    backgroundColor: "var(--m-okraj, #e2e5ea)",
  },
  ".et-lista-mezera": { flex: "1" },
  ".et-lista button": {
    minWidth: "30px",
    height: "28px",
    padding: "0 7px",
    margin: "0",
    border: "1px solid transparent",
    borderRadius: "5px",
    background: "transparent",
    color: "inherit",
    cursor: "pointer",
    fontSize: "13px",
    lineHeight: "1",
    boxShadow: "none",
  },
  ".et-lista button:hover": {
    backgroundColor: "var(--m-hover, #e9ebee)",
    borderColor: "var(--m-okraj-pole, #c4c9d1)",
  },
  ".et-lista button:focus-visible": {
    outline: "2px solid var(--m-fokus, #2563eb)",
    outlineOffset: "0",
  },
  ".et-lista button:active": { backgroundColor: "var(--m-tlacitko-stisk, #dfe2e6)" },
  ".et-stav": {
    minHeight: "1.4em",
    padding: "4px 10px",
    fontFamily: "var(--m-pismo, system-ui, sans-serif)",
    fontSize: "12px",
    color: "var(--m-text-tlumeny, #5b6370)",
  },
  ".et-nahravani": {
    padding: "1px 6px",
    borderRadius: "4px",
    backgroundColor: "var(--m-vybrana-polozka, #eef2f7)",
    color: "var(--m-text-tlumeny, #5b6370)",
    fontFamily: "var(--m-pismo, system-ui, sans-serif)",
    fontSize: "12px",
    fontStyle: "italic",
  },
  ".et-stav-chyba": { color: BARVY.chyba.svetla },
  [`${TMAVE} .et-stav-chyba`]: { color: BARVY.chyba.tmava },

  ".et-nadpis": { fontWeight: "bold" },
  ".et-tucne": { fontWeight: "bold" },
  ".et-kurziva": { fontStyle: "italic" },
  ".et-preskrtnute": { textDecoration: "line-through" },
  ".et-odkaz": { textDecoration: "underline", textDecorationColor: "color-mix(in srgb, currentColor 40%, transparent)" },
  ".et-citace": { fontStyle: "italic" },
  ...obarvi("et-nadpis", BARVY.nadpis),
  ...obarvi("et-odkaz", BARVY.odkaz),
  ...obarvi("et-znacka", BARVY.znacka),
  ...obarvi("et-kod", BARVY.kod),
  ...obarvi("et-citace", BARVY.citace),
  ...obarvi("et-html-znacka", BARVY.htmlZnacka),
  ...obarvi("et-html-atribut", BARVY.htmlAtribut),
  ...obarvi("et-html-hodnota", BARVY.htmlHodnota),
  ...obarvi("et-klicove-slovo", BARVY.htmlZnacka),
  ...obarvi("et-komentar", BARVY.komentar),
  ...obarvi("et-entita", BARVY.kod),
});

/** Popisky CodeMirroru (hledání a nahrazení, přechod na řádek) česky */
export const CESKE_POPISKY: Record<string, string> = {
  Find: "Hledat",
  Replace: "Nahradit čím",
  next: "další",
  previous: "předchozí",
  all: "vše",
  "match case": "rozlišovat velikost písmen",
  regexp: "regulární výraz",
  "by word": "celá slova",
  replace: "nahradit",
  "replace all": "nahradit vše",
  close: "zavřít",
  "current match": "aktuální výskyt",
  "replaced $ matches": "nahrazeno $×",
  "replaced match on line $": "nahrazeno na řádku $",
  "on line": "na řádku",
  "Go to line": "Přejít na řádek",
  go: "přejít",
  "Control character": "Řídicí znak",
};
