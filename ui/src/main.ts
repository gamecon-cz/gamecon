import { initEnv } from "./env";
import { renderPages } from "./pages";
import { registerFlexSearchCs } from "./store/program/logic/flexSearch";


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
registerFlexSearchCs();
renderPages();
