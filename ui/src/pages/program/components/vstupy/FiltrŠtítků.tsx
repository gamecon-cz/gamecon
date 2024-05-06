import React from "react";
import { useMemo } from "preact/hooks";
import Select, { GroupBase, GroupProps, MultiValueProps, OptionsOrGroups, components } from "react-select";
import { useAktivityFiltrované, useŠtítkyPodleKategorie, useŠtítkyPočetAktivit, useŠtítkyVybranéPodleKategorie } from "../../../../store/program/selektory";
import { nastavFiltrŠtítků } from "../../../../store/program/slices/urlSlice";
import { asValueLabel } from "../../../../utils";

type ŠttítekValueLabel = {
  value: number;
  label: string;
  početMožností?: number;
  /** pokud číslo značí kolik vybráním této možnosti přibyde aktivit */
  početMožnostíNavíc?: boolean;
  jeNázevKategorie?: boolean;
}

const formatOptionLabel = (data: ŠttítekValueLabel): React.ReactNode =>
  (
    <div class="react_select_option--container">
      <span>{data.label}</span>
      {data.početMožností !== undefined ? (
        <span class="react_select_option--badge">
          {data.početMožností === 0
            ? "-"
            : ((data?.početMožnostíNavíc ? "+" : "")
              + data.početMožností.toString())}
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

const GroupDefault = components.Group;
const GroupFlex: React.ComponentType<GroupProps<ŠttítekValueLabel, true, GroupBase<ŠttítekValueLabel>>> = (props) => {
  // TODO: použít class name a css
  props.children = <div style={{ display: "flex", flexWrap: "wrap" }}>
    {props.children}
  </div> as any;
  return <GroupDefault {...props} />;
};

type TFiltrŠtítkůProps = {

};

export const FiltrŠtítků: React.FC<TFiltrŠtítkůProps> = (props) => {
  const { } = props;

  const štítkyPodleKategorie = useŠtítkyPodleKategorie();
  const vybranéŠtítkyPodleKategorie = useŠtítkyVybranéPodleKategorie();
  const štítkySPočtemAktivit = useŠtítkyPočetAktivit();
  const početAktivit = useAktivityFiltrované().length;

  const štítkyMožnosti: OptionsOrGroups<ŠttítekValueLabel, GroupBase<ŠttítekValueLabel>> =
    štítkyPodleKategorie.map(({ kategorie, štítky }) => ({
      label: kategorie,
      options: štítky
        .map(((štítek) => {
          const valueLabel: ŠttítekValueLabel = {
            value: štítek.id,
            label: štítek.nazev,
          };
          const početAktivitŠtítku = štítkySPočtemAktivit.find(x => x.štítekId === štítek.id)?.počet ?? -1;
          if (početAktivitŠtítku >= 0) {
            valueLabel.početMožností = početAktivitŠtítku;
            if (početAktivitŠtítku >= početAktivit) {
              valueLabel.početMožností = početAktivitŠtítku - početAktivit;
              valueLabel.početMožnostíNavíc = true;
            }
          }
          return valueLabel;
        }))
    }));

  const vybranéŠtítkySKategorií = useMemo(
    () => {
      return vybranéŠtítkyPodleKategorie.flatMap(({ kategorie, štítky }) => {
        // TODO: opravit typování
        const kat: ŠttítekValueLabel = asValueLabel(kategorie) as any;
        kat.jeNázevKategorie = true;
        return [kat].concat(štítky.map(štítek => ({
          value: štítek.id,
          label: štítek.nazev,
        }))
        );
      });
    }, [vybranéŠtítkyPodleKategorie]);

  return <>
    <Select<ŠttítekValueLabel, true>
      placeholder="Tagy"
      options={štítkyMožnosti}
      isMulti
      closeMenuOnSelect={false}
      value={vybranéŠtítkySKategorií}
      onChange={(e) => {
        console.log(e);
        nastavFiltrŠtítků(e.filter(x => !x?.jeNázevKategorie).map((x) => x.value));
      }}
      components={{
        MultiValue: MultiValueŠtítky,
        Group: GroupFlex,
      }}
      formatOptionLabel={formatOptionLabel}
      styles={{
        option(base, props) {
          return {
            ...base,
            // flex: 1,
            minWidth: "180px",
            maxWidth: "180px",
          };
        },
      }}
    />
  </>;
};

FiltrŠtítků.displayName = "FiltrŠtítků";
