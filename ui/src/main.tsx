import './index.less'
import { renderPages } from "./pages"

// TODO: linter
// TODO: revidovat názvosloví
// TODO: návod na práci s ui BUILDING Vývoj atd.
// TODO: pro api používat normalizovaný čas třeba unix timestamp
// TODO: Vytvořit zálohy node_modules pro případ nekompatabilní změny balíčku a smazání staré verze
// TODO: program/muj_program při refreshi vrací nenalezeno. Preact by měl mít pod kontrolou komplet url za program/
// TODO: legendaText by nemělo být html
// TODO: uklidit duplicitní less styly.

console.log("Preact starting ...")

renderPages();
