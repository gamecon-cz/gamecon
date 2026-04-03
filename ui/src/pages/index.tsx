import { Program } from "./program";
import { Obchod } from "./obchod";
import { ObchodNastaveni } from "./obchodNastaveni";
import { AktivityApp } from "./aktivity";
import { JídloMatice } from "./jidlo/JídloMatice";
import { FunctionComponent, render } from "preact";

import "./index.less";
import "./jidlo/JídloMatice.less";

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
};
