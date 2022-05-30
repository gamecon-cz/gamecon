import { render } from 'preact'
import { Program } from './app'

export const renderProgram = () =>{
  const programRoot = document.getElementById('preact-program');
  
  if (programRoot) {
    programRoot.innerHTML = "";
    render(<Program />, programRoot)
  }
}
