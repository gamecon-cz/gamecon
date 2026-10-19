import { Program } from "./program";
import { Obchod } from "./obchod";
import { ObchodNastaveni } from "./obchodNastaveni";
import { AktivityApp } from "./aktivity";
import { Předměty } from "./předměty";
import { JídloMatice } from "./jidlo/JídloMatice";
import { MerchMřížka, SvrškyMřížka } from "./merch/MerchMřížka";
import { UbytovaniMřížka } from "./ubytovani/UbytovaniMřížka";
import { Vstupne } from "./vstupne/Vstupne";
import { FunctionComponent, render } from "preact";
import { MountProps } from "./mountProps";

import "./index.less";
import "./jidlo/JídloMatice.less";
import "./merch/MerchMřížka.less";

const renderComponent = (
  rootId: string,
  Component: FunctionComponent<MountProps>
) => {
  const root = document.getElementById(rootId);
  if (!root) return;

  // Parse the same shape the server accepts: plain digits only. Number() would turn "1e3"
  // into 1000 and round anything past MAX_SAFE_INTEGER, so the page and the API would
  // disagree about which participant was asked for.
  const raw = root.dataset.customerId ?? "";
  const props: MountProps = /^[1-9][0-9]*$/.test(raw) && Number.isSafeInteger(Number(raw))
    ? { customerId: Number(raw) }
    : {};

  root.innerHTML = "";
  render(<Component {...props} />, root);
};

export const renderPages = () => {
  renderComponent("preact-obchod-nastaveni", ObchodNastaveni);
  renderComponent("preact-program", Program);
  renderComponent("preact-obchod", Obchod);
  renderComponent("preact-aktivity-modal", AktivityApp);
  renderComponent("preact-jidlo", JídloMatice);
  renderComponent("preact-merch", MerchMřížka);
  renderComponent("preact-svrsky", SvrškyMřížka);
  renderComponent("preact-ubytovani", UbytovaniMřížka);
  renderComponent("preact-vstupne", Vstupne);
  renderComponent("preact-předměty", Předměty);
};
