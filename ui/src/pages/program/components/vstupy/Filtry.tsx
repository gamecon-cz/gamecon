import { FunctionComponent } from "preact";
import { useCallback, useEffect, useState } from "preact/hooks";
import Select from "react-select";
import { GAMECON_KONSTANTY, ROKY } from "../../../../env";
import {
  useUrlStav,
  useUrlStavMožnosti,
} from "../../../../store/program/selektory";
import {
  nastavFiltrLinií,
  nastavFiltrPřihlašovatelné,
  nastavFiltrRočník,
  nastavFiltrTextu,
} from "../../../../store/program/slices/urlSlice";

import "./ReactSelect.less";
import "./Filtry.less";
import { FiltrŠtítků } from "./FiltrŠtítků";
import { asValueLabel } from "../../../../utils";
import { useProgramStore } from "../../../../store/program";
import { přepniKompaktní, přepniZvětšeno } from "../../../../store/program/slices/všeobecnéSlice";

type TFiltryProps = {
  otevřeno: boolean;
};

const ROKY_OPTIONS = ROKY.concat(GAMECON_KONSTANTY.ROCNIK)
  .map((x) => ({ value: x, label: x }))
  .reverse();


export const Filtry: FunctionComponent<TFiltryProps> = (props) => {
  const { otevřeno } = props;

  const { ročník, filtrPřihlašovatelné, filtrLinie, filtrText } = useUrlStav();

  const urlStavMožnosti = useUrlStavMožnosti();

  const { zvětšeno, kompaktní } = useProgramStore(s => s.všeobecné);
  const [odkazZkopírován, setOdkazZkopírován] = useState(0);

  useEffect(() => {
    if (!odkazZkopírován) return;
    const timeout = setTimeout(() => {
      setOdkazZkopírován(0);
    }, 1500);
    return () => { clearTimeout(timeout); };
  }, [odkazZkopírován]);

  const sdílejKlik = useCallback(() => {
    void navigator.clipboard.writeText(window.location.href);
    setOdkazZkopírován(Date.now());
  }, []);

  return (
    <>
      <div
        class={
          "program_filtry_container clearfix" +
          (otevřeno ? " program_filtry_container_otevreno" : "")
        }
      >
        <div style={{ display: "flex", gap: 16 }}>
          <div style={{ width: "120px" }}>
            <Select
              value={asValueLabel(ročník)}
              onChange={(e) => {
                nastavFiltrRočník(e?.value);
              }}
              options={ROKY_OPTIONS}
            />
          </div>
          <div style={{ flex: "1", maxWidth: "400px" }}>
            <Select
              placeholder="Linie"
              options={urlStavMožnosti.linie.map(asValueLabel)}
              closeMenuOnSelect={false}
              isMulti
              value={filtrLinie?.map(asValueLabel) ?? []}
              onChange={(e) => {
                nastavFiltrLinií(e.map((x) => x.value));
              }}
            />
          </div>
          <div style={{ flex: "1" }}>
            <FiltrŠtítků />
          </div>
          <div style={{ minWidth: "300px" }} class="formular_polozka">
            <input style={{ marginTop: 0, height: "38px" }} placeholder="Hledej v textu"
              value={filtrText}
              onChange={(e)=>{
                nastavFiltrTextu(e.currentTarget.value);
              }}
            />
          </div>
        </div>

        <div style={{ display: "flex", justifyContent: "flex-end" }}>
          <button class={
            "program_filtry_tlacitko program_filtry_tlacitko_zvetsit" +
            (zvětšeno ? " aktivni" : "")
          } onClick={přepniZvětšeno}>zvětšit</button>
          <button class={
            "program_filtry_tlacitko"
            + (odkazZkopírován ? " aktivni" : "")
          } onClick={sdílejKlik}
          >{odkazZkopírován ? "zkopírováno" : "sdílej"}</button>
          <button
            class={
              "program_filtry_tlacitko" +
              (filtrPřihlašovatelné ? " aktivni" : "")
            }
            onClick={() => {
              nastavFiltrPřihlašovatelné(!filtrPřihlašovatelné);
            }}
          >
            Přihlašovatelné
          </button>
          <button class={
            "program_filtry_tlacitko"
            + (kompaktní ? " aktivni" : "")
          } onClick={přepniKompaktní}>Kompaktní</button>
        </div>
      </div>
    </>
  );
};

Filtry.displayName = "Filtry";
