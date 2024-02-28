import React from "react";
import { jeSchoditě, mapa } from "./mockMapa";

type TUbytovaníNastaveníProps = {

};

export const UbytovaníNastavení: React.FC<TUbytovaníNastaveníProps> = (props) => {
  const { } = props;

  return <>
    {mapa.map((řádek, řádekIndex) =>
      <div style={{
        flexDirection: "row", display: "flex",
        marginTop: řádekIndex % 2 === 0 ? "20px" : "70px",
        paddingBottom: řádekIndex % 2 === 0 ? "" : "20px",
        borderBottom: řádekIndex % 2 === 0 ? "" : "5px solid black",
      }}>
        {řádek.map((místnost, místnostIndex) => {
          const popis = jeSchoditě(místnost) ? místnost.schodiště : (místnost.značení ?? místnost.typ ?? místnost.popis);
          return <div style={{
            width: 25 * místnost.šířka,
            height: 50,
            borderWidth: 2,
            borderColor: "black",
            borderStyle: "solid",
            backgroundColor: "lightgray",
            boxSizing: "border-box",
            flexShrink: 0,
          }}>
            <div style={{ width: "auto",  textAlign:"center", }}>
              {popis}
            </div>
          </div>;
        })}
      </div>
    )}
  </>;
};

UbytovaníNastavení.displayName = "UbytovaníNastavení";
