import React from "react";
import { Mapa } from "./Mapa";

type TUbytovaníNastaveníProps = {

};

export const UbytovaníNastavení: React.FC<TUbytovaníNastaveníProps> = (props) => {
  const { } = props;

  return <>
    <div style={{height:"85vh", overflow:"auto"}}>
      <Mapa />
    </div>
  </>;
};

UbytovaníNastavení.displayName = "UbytovaníNastavení";
