import { Program } from "./program";
import { Obchod } from "./obchod";
import { ObchodNastaveni } from "./obchodNastaveni";
import { AktivityApp } from "./aktivity";
import { Předměty } from "./předměty";
import { JídloMatice } from "./jidlo/JídloMatice";
import { MerchMřížka } from "./merch/MerchMřížka";
import { UbytovaniMřížka } from "./ubytovani/UbytovaniMřížka";
import { Vstupne } from "./vstupne/Vstupne";
import { FunctionComponent, render } from "preact";

import "./index.less";
import "./jidlo/JídloMatice.less";
import "./merch/MerchMřížka.less";

const renderComponent = (
  rootId: string,
  Component: FunctionComponent
) => {
  const root = document.getElementById(rootId);
  if (!root) return;

  root.innerHTML = "";
  render(<Component />, root);
};

export const renderPages = () => {
  renderComponent("preact-obchod-nastaveni", ObchodNastaveni);
  renderComponent("preact-program", Program);
  renderComponent("preact-obchod", Obchod);
  renderComponent("preact-aktivity-modal", AktivityApp);
  renderComponent("preact-jidlo", JídloMatice);
  renderComponent("preact-merch", MerchMřížka);
  renderComponent("preact-ubytovani", UbytovaniMřížka);
  renderComponent("preact-vstupne", Vstupne);
  renderComponent("preact-předměty", Předměty);
};
