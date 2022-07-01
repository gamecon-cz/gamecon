import { render } from 'preact'
import Router, { Route } from "preact-router";
import { Shop } from './app'

export const renderProgram = () =>{
  const programRoot = document.getElementById('preact-shop');

  if (programRoot) {
    programRoot.innerHTML = "";
    render(
    <Router>
      <Route component={Shop} default />
    </Router>
    , programRoot)
  }
}
