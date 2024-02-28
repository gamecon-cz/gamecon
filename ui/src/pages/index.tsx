import { Program } from "./program";
import { Obchod } from "./obchod";
import { ObchodNastaveni } from "./obchodNastaveni";
import { Fragment, FunctionComponent, JSX, render } from "preact";
import { GAMECON_KONSTANTY } from "../env";
import { ProgramWrapper } from "../testing/ProgramWrapper";

import "./index.less";
import { UbytovaníNastavení } from "./ubytovaniNastaveni";

const renderComponent = (
  rootId: string,
  Component: FunctionComponent,
  DevWrap?: FunctionComponent<{ children: JSX.Element }>
) => {
  const root = document.getElementById(rootId);
  if (!root) return;

  root.innerHTML = "";
  const Wrapper =
    GAMECON_KONSTANTY.IS_DEV_SERVER && DevWrap ? DevWrap : Fragment;
  render(
    <Wrapper>
      <Component />
    </Wrapper>,
    root
  );
};

export const renderPages = () => {
  renderComponent("preact-ubytovani-nastaveni", UbytovaníNastavení);
  renderComponent("preact-obchod-nastaveni", ObchodNastaveni);
  renderComponent("preact-program", Program, ProgramWrapper);
  renderComponent("preact-obchod", Obchod);
};
