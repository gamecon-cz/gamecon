import React from "react";
import { useMemo } from "preact/hooks";
import Select, { GroupBase, GroupProps, MultiValueProps, OptionsOrGroups, components } from "react-select";
import { useAktivityFiltrované, useTagyPodleKategorie, useTagyPočetAktivit, useTagyVybranéPodleKategorie } from "../../../../store/program/selektory";
import { nastavFiltrTagů } from "../../../../store/program/slices/urlSlice";
import { asValueLabel } from "../../../../utils";

type TagValueLabel = {
  value: number;
  label: string;
  početMožností?: number;
  /** pokud číslo značí kolik vybráním této možnosti přibyde aktivit */
  početMožnostíNavíc?: boolean;
  jeNázevKategorie?: boolean;
}

const formatOptionLabel = (data: TagValueLabel): React.ReactNode =>
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
const MultiValueTagy = (props: MultiValueProps<TagValueLabel, true, GroupBase<TagValueLabel>>) => {
  const { data } = props;
  if (data?.jeNázevKategorie) {
    return (<div>{data?.label}:</div>);
  } else {
    return <MultiValueDefault {...props} />;
  }
};

const GroupDefault = components.Group;
const GroupFlex: React.ComponentType<GroupProps<TagValueLabel, true, GroupBase<TagValueLabel>>> = (props) => {
  // TODO: použít class name a css
  props.children = <div style={{ display: "flex", flexWrap: "wrap" }}>
    {props.children}
  </div> as any;
  return <GroupDefault {...props} />;
};

type TFiltrTagůProps = {

};

export const FiltrTagů: React.FC<TFiltrTagůProps> = (props) => {
  const { } = props;

  const tagyPodleKategorie = useTagyPodleKategorie();
  const vybranéTagyPodleKategorie = useTagyVybranéPodleKategorie();
  const tagySPočtemAktivit = useTagyPočetAktivit();
  const početAktivit = useAktivityFiltrované().length;

  const tagyMožnosti: OptionsOrGroups<TagValueLabel, GroupBase<TagValueLabel>> =
    tagyPodleKategorie.map(({ kategorie, tagy }) => ({
      label: kategorie,
      options: tagy
        .map(((tag) => {
          const valueLabel: TagValueLabel = {
            value: tag.id,
            label: tag.nazev,
          };
          const početAktivitTagu = tagySPočtemAktivit.find(x => x.tagId === tag.id)?.počet ?? -1;
          if (početAktivitTagu >= 0) {
            valueLabel.početMožností = početAktivitTagu;
            if (početAktivitTagu >= početAktivit) {
              valueLabel.početMožností = početAktivitTagu - početAktivit;
              valueLabel.početMožnostíNavíc = true;
            }
          }
          return valueLabel;
        }))
    }));

  const vybranéTagySKategorií = useMemo(
    () => {
      return vybranéTagyPodleKategorie.flatMap(({ kategorie, tagy }) => {
        // TODO: opravit typování
        const kat: TagValueLabel = asValueLabel(kategorie) as any;
        kat.jeNázevKategorie = true;
        return [kat].concat(tagy.map(tag => ({
          value: tag.id,
          label: tag.nazev,
        }))
        );
      });
    }, [vybranéTagyPodleKategorie]);

  return <>
    <Select<TagValueLabel, true>
      placeholder="Tagy"
      options={tagyMožnosti}
      isMulti
      closeMenuOnSelect={false}
      value={vybranéTagySKategorií}
      onChange={(e) => {
        console.log(e);
        nastavFiltrTagů(e.filter(x => !x?.jeNázevKategorie).map((x) => x.value));
      }}
      components={{
        MultiValue: MultiValueTagy,
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

FiltrTagů.displayName = "FiltrTagů";
