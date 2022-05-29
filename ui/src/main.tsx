import { render } from 'preact'
import { App } from './app'
import './index.less'

console.log("Preact starting ...")
render(<App />, document.getElementById('preact-program')!)
