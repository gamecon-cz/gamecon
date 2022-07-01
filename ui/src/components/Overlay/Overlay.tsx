import { FunctionComponent } from "preact";
import "./Overlay.less"

type TOverlayProps = {

};

export const Overlay: FunctionComponent<TOverlayProps> = (props) => {
  const {children} = props;

  return <>
    <div class="overlay--container">
      <div class="overlay--child">
        {children}
      </div>
    </div>
  </>;
};

Overlay.displayName = "Overlay";
