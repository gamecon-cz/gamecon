import { FunctionComponent } from "preact";
import { useEffect, useState } from "preact/hooks";
import Select from "react-select";
import { GAMECON_KONSTANTY, ROKY } from "../../../../env";
import {
  useTagySPočtemAktivit,
  useUrlStav,
  useUrlStavMožnosti,
} from "../../../../store/program/selektory";
import {
  nastavFiltrLinií,
  nastavFiltrPřihlašovatelné,
  nastavFiltrRočník,
  nastavFiltrTagů,
  nastavFiltrTextu,
} from "../../../../store/program/slices/urlSlice";

import "./ReactSelect.less";
import "./Filtry.less";
import { přepniKompaktní, přepniZvětšeno } from "../../../../store/program/slices/všeobecnéSlice";
import { useProgramStore } from "../../../../store/program";
import React, { ReactNode, useCallback } from "react";

type TFiltryProps = {
  otevřeno: boolean;
};

const ROKY_OPTIONS = ROKY.concat(GAMECON_KONSTANTY.ROCNIK)
  .map((x) => ({ value: x, label: x }))
  .reverse();

type TValueLabel<T = any> = {
  value: T;
  label: T;
  početMožností?: number;
};

const asValueLabel = <T,>(obj: T): TValueLabel<T> => ({
  value: obj,
  label: obj,
});

const formatOptionLabel = (data: TValueLabel): ReactNode =>
  (
    <div class="react_select_option--container">
      <span>{data.label}</span>
      {data.početMožností !== undefined ? (
        <span class="react_select_option--badge">
          {data.početMožností === 0 ? "-" : data.početMožností}
        </span>
      ) : undefined}
    </div>
  ) as any;

export const Filtry: FunctionComponent<TFiltryProps> = (props) => {
  const { otevřeno } = props;

  const { ročník, filtrPřihlašovatelné, filtrLinie, filtrTagy, filtrText } = useUrlStav();

  const urlStavMožnosti = useUrlStavMožnosti();

  const tagySPočtemAktivit = useTagySPočtemAktivit();

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
          {// TODO: Vytvořit filtr štítků stejně jako je v adminu → aktivity → nová aktivita → tagy
          }
            <Select<TValueLabel<string>, true>
              placeholder="Tagy"
              options={tagySPočtemAktivit.map((x) => ({
                ...asValueLabel(x.tag),
                početMožností: x.celkemVRočníku,
              }))}
              isMulti
              closeMenuOnSelect={false}
              value={filtrTagy?.map(asValueLabel) ?? []}
              onChange={(e) => {
                nastavFiltrTagů(e.map((x) => x.value));
              }}
              formatOptionLabel={formatOptionLabel}
            />
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
          } onClick={() => {
            přepniKompaktní();
          }}>Kompaktní</button>
        </div>
      </div>
    </>
  );
};

Filtry.displayName = "Filtry";
