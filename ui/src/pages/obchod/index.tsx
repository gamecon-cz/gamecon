import { render } from 'preact'
import Router, { Route } from "preact-router";
import { Obchod } from './app'

export const renderObchod = () =>{
  const obchodRoot = document.getElementById('preact-obchod');

  if (obchodRoot) {
    obchodRoot.innerHTML = "";
    render(
    <Router>
      <Route component={Obchod} default />
    </Router>
    , obchodRoot)
  }
}
