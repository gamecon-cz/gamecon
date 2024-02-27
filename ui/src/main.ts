import { initEnv } from "./env";
import { renderPages } from "./pages";

// TODO: zbavit se html v db kde to jde (legenda text, program náhled ...)
// TODO: uklidit duplicitní less styly.
// TODO: github actions test na linter

//         Spouštění
//         Developement
//          jak pracovat se zustand
/*            ! Pro vytvoření nového slice:
                - má svou složku
                - má svůj vlastní klíč ve store
                  například: type ExmampleSlice = { example: {......} }
                  - může editovat pouze hodnoty ve svém klíčí
                  - může 
*/
console.log("Preact starting ...");

initEnv();
renderPages();
