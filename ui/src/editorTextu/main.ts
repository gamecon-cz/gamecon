import { spustEditoryTextu } from "./editor";

/**
 * Vstup souboru `editor-textu.js`, který se sestavuje zvlášť vedle `bundle.js` (vite.config.ts) a načítá ho jen pole
 * s textem v Markdownu v administraci (model/dbff-markdown-direct.xtpl). Skript je modul, spustí se až po načtení HTML.
 */
spustEditoryTextu(document);
