import { FunctionComponent } from "preact";
import Select from "react-select";
import { GAMECON_KONSTANTY, ROKY } from "../../../../env";
import {
  useTagySPočtemAktivit,
  useUrlState,
  useUrlStateMožnosti,
} from "../../../../store/program/selektory";
import {
  nastavFiltrLinií,
  nastavFiltrPřihlašovatelné,
  nastavFiltrRočník,
  nastavFiltrTagů,
} from "../../../../store/program/slices/urlSlice";

import "./ReactSelect.less";

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

// TODO: zaobalit a vytáhnout Select do globálních komponent (vedle Overlay)

// TODO: return type
const formatOptionLabel = (data: TValueLabel) =>
  <div class="react_select_option--container">
    <span>{data.label}</span>
    {data.početMožností !== undefined ? (
      <span class="react_select_option--badge">{data.početMožností === 0 ? "-" : data.početMožností}</span>
    ) : undefined}
  </div> as any;


// TODO: můj program je nefiltrovaný - zašednout všechny controly ve filtry a lehce i tlačítko filtry
// TODO: seřadí linie, tagy (přesune nahoru seznamu) podle den -> rok -> zbytek (možná i podle výskytů)
// TODO: tlačítko křížek vedle tlačítka filtry které všechny smaže
// TODO: mobilní zobrazení
export const Filtry: FunctionComponent<TFiltryProps> = (props) => {
  const { otevřeno } = props;

  const urlState = useUrlState();

  const urlStateMožnosti = useUrlStateMožnosti();

  const tagySPočtemAktivit = useTagySPočtemAktivit();

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
              value={asValueLabel(urlState.ročník)}
              onChange={(e) => {
                nastavFiltrRočník(e?.value);
              }}
              options={ROKY_OPTIONS}
            />
          </div>
          <div style={{ flex: "1", maxWidth: "400px" }}>
            <Select
              placeholder="Linie"
              options={urlStateMožnosti.linie.map(asValueLabel)}
              closeMenuOnSelect={false}
              isMulti
              value={urlState.filtrLinie?.map(asValueLabel) ?? []}
              onChange={(e) => {
                nastavFiltrLinií(e.map((x) => x.value));
              }}
            />
          </div>
          <div style={{ flex: "1" }}>
            <Select<TValueLabel<string>, true>
              placeholder="Tagy"
              options={tagySPočtemAktivit.map(x=>({...asValueLabel(x.tag), početMožností:x.celkemVRočníku}))}
              isMulti
              closeMenuOnSelect={false}
              value={urlState.filtrTagy?.map(asValueLabel) ?? []}
              onChange={(e) => {
                nastavFiltrTagů(e.map((x) => x.value));
              }}
              formatOptionLabel={formatOptionLabel}
            />
          </div>
          <div style={{ minWidth: "300px" }} class="formular_polozka">
            <input style={{ marginTop: 0 }} placeholder="Hledej v textu" />
          </div>
        </div>

        <div>
          <button class="program_filtry_tlacitko">zvětšit</button>
          <button class="program_filtry_tlacitko">sdílej</button>
          <button
            class={
              "program_filtry_tlacitko" +
              (urlState.filtrPřihlašovatelné ? " aktivni" : "")
            }
            onClick={() => {
              nastavFiltrPřihlašovatelné(!urlState.filtrPřihlašovatelné);
            }}
          >
            Přihlašovatelné
          </button>
          <button class="program_filtry_tlacitko">Detail</button>
        </div>
      </div>
    </>
  );
};

Filtry.displayName = "Filtry";
