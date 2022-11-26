import { FunctionComponent } from "preact";
import "./Overlay.less";

type TOverlayProps = {
  onClickOutside?: () => void;
};

export const Overlay: FunctionComponent<TOverlayProps> = (props) => {
  const { children, onClickOutside } = props;

  return (
    <>
      <div
        class="overlay--container"
        onClick={(e) => {
          if (e.target === e.currentTarget) onClickOutside?.();
        }}
      >
        <div class="overlay--child">{children}</div>
      </div>
    </>
  );
};

Overlay.displayName = "Overlay";
