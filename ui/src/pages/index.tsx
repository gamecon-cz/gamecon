import { Program } from "./program"
import { Obchod } from "./obchod";
import { ObchodNastaveni } from "./obchodNastaveni";
import { render } from "preact";
import Router, { Route } from "preact-router";

// TODO: otypovat component
const renderComponent = (rootId: string, component: any) =>{
  const root = document.getElementById(rootId);

  if (root) {
    root.innerHTML = "";
    render(
    <Router>
      <Route component={component} default />
    </Router>
    , root)
  }
}

export const renderPages = () =>{
  renderComponent('preact-obchod-nastaveni', ObchodNastaveni);
  renderComponent('preact-program', Program);
  renderComponent('preact-obchod', Obchod);
}

