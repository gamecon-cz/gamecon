import React from "react";
import { useMemo } from "preact/hooks";
import Select, { GroupBase, MultiValueProps, components } from "react-select";
import { useŠtítkyPodleKategorie, useŠtítkyVybranéPodleKategorie } from "../../../../store/program/selektory";
import { nastavFiltrTagů } from "../../../../store/program/slices/urlSlice";
import { TValueLabel, asValueLabel } from "../../../../utils";

type ŠttítekValueLabel = TValueLabel<string> & {
  početMožností?: number;
  jeNázevKategorie?: boolean;
}

const formatOptionLabel = (data: ŠttítekValueLabel): React.ReactNode =>
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

const MultiValueDefault = components.MultiValue;
const MultiValueŠtítky = (props: MultiValueProps<ŠttítekValueLabel, true, GroupBase<ŠttítekValueLabel>>) => {
  const { data } = props;
  if (data?.jeNázevKategorie) {
    return (<div>{data?.label}:</div>);
  } else {
    return <MultiValueDefault {...props} />;
  }
};

type TFiltrŠtítkůProps = {

};

export const FiltrŠtítků: React.FC<TFiltrŠtítkůProps> = (props) => {
  const { } = props;

  const štítkyPodleKategorie = useŠtítkyPodleKategorie();
  const vybranéŠtítkyPodleKategorie = useŠtítkyVybranéPodleKategorie();

  const vybranéŠtítkySKategorií = useMemo(
    () => {
      return vybranéŠtítkyPodleKategorie.flatMap(({ kategorie, štítky }) => {
        const kat: ŠttítekValueLabel = asValueLabel(kategorie);
        kat.jeNázevKategorie = true;
        return [kat].concat(štítky.map(x => x.nazev).map(asValueLabel));
      });
    }, [vybranéŠtítkyPodleKategorie]);

  return <>
    <Select<ŠttítekValueLabel, true>
      placeholder="Tagy"
      options={
        štítkyPodleKategorie.map(({ kategorie, štítky }) => ({
          label: kategorie,
          options: štítky.map(x => ({
            ...asValueLabel(x.nazev),
          }))
        }))
      }
      isMulti
      closeMenuOnSelect={false}
      value={vybranéŠtítkySKategorií}
      onChange={(e) => {
        nastavFiltrTagů(e.filter(x => !x?.jeNázevKategorie).map((x) => x.value));
      }}
      components={{
        MultiValue: MultiValueŠtítky,
      }}
      formatOptionLabel={formatOptionLabel}
    />
  </>;
};

FiltrŠtítků.displayName = "FiltrŠtítků";
