import React from "react";

type TProgramPlanovacProps = {

};

export const ProgramPlanovac: React.FC<TProgramPlanovacProps> = (props) => {
  const {} = props;
  const urlStavMožnosti = useUrlStavMožnostiDny();

  return <>
    {urlStavMožnosti}


  </>;
};

ProgramPlanovac.displayName = ProgramPlanovac.name;
