import { FunctionComponent } from "preact"
import { useEffect, useState } from "preact/hooks"
import { sleep } from "../../../utils"
import { useProgramStore } from "../../../store/program"
import { nastavChyba } from "../../../store/program/slices/všeobecnéSlice"

type ChybaBoxProps = {
}

export const ChybaBox: FunctionComponent<ChybaBoxProps> = ({ }) => {
  const hláška = useProgramStore(d => d.všeobecné.chyba);

  const [opacity, setOpacity] = useState(1);
  useEffect(() => {
    let canceled = false;
    void (async () => {
      setOpacity(1)
      if (!hláška) return;
      await sleep(6_000);
      if (canceled) return;
      setOpacity(0)
      await sleep(2_000);
      if (canceled) return;
      nastavChyba(undefined)
      setOpacity(1)
    })();
    return () => canceled = true;
  }, [hláška])

  return hláška
    ? <div style={{
      "transition": "opacity " + (2).toString(10) + "s",
      "opacity": opacity,
    }} class="chybaBlok chybaBlok-errorHlaska">
      <div><div class="hlaska errorHlaska">{hláška}</div></div>
      <div class="chybaBlok_zavrit admin_zavrit" onClick={() => nastavChyba(undefined)}>❌</div>
    </div>
    : undefined
}

