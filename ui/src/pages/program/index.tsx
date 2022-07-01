import { render } from 'preact'
import Router, { Route } from "preact-router";
import { Program } from './app'

export const renderProgram = () =>{
  const programRoot = document.getElementById('preact-program');

  if (programRoot) {
    programRoot.innerHTML = "";
    render(
    <Router>
      <Route component={Program} default />
    </Router>
    , programRoot)
  }
}
