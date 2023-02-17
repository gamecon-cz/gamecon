import Preact from "preact";
import { Ref} from "preact/hooks";
import { useEffect, useState } from "preact/hooks";

const POSUN = 220;

type ProgramPosuvProps = {
  obalRef: Ref<HTMLElement>;
}

export const ProgramPosuv: Preact.FunctionComponent<ProgramPosuvProps> = (
  props: ProgramPosuvProps
) => {
  const [lVisible, setLVisible] = useState(false);
  const [rVisible, setRVisible] = useState(false);
  const visible = [lVisible, rVisible];

  const obal = props.obalRef.current;

  const checkScroll = () => {
    if (!obal) return;
    const left = obal.scrollLeft;
    if (left <= 0) {
      setLVisible(false);
    } else {
      setLVisible(true);
    }

    const innerWidth = obal.scrollWidth;
    const outerWidth = obal.clientWidth;
    const right = innerWidth - (left + outerWidth);
    if (right <= 0) {
      setRVisible(false);
    } else {
      setRVisible(true);
    }
  };

  useEffect(() => {
    checkScroll();
  }, []);

  useEffect(() => {
    if (!obal) return;
    const obalCurrent = obal;
    const checkScrollCurrent = checkScroll;

    const observer = new ResizeObserver(checkScrollCurrent);
    observer.observe(obalCurrent);
    obalCurrent.addEventListener("scroll", checkScrollCurrent);

    return () => {
      observer.disconnect();
      obalCurrent.removeEventListener("scroll", checkScrollCurrent);
    };
  }, [obal, checkScroll]);

  return (
    <>
      {["l", "r"].map((strana, i) => {
        return (
          <div
            class={`programPosuv_posuv programPosuv_${strana}posuv`}
            style={{ display: visible[i] ? "block" : "none" }}
          >
            <div
              onClick={() =>
                obal?.scrollBy({
                  left: POSUN * (i ? 1 : -1),
                  behavior: "smooth",
                })
              }
            />
          </div>
        );
      })}
    </>
  );
};
