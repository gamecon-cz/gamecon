
import FlexSearch from "flexsearch";

// TODO:
export const registerFlexSearchCs = () => {
  FlexSearch.registerLanguage("cs", {
    filter: false, 
    matcher: false,
    stemmer: false,
  });
};
