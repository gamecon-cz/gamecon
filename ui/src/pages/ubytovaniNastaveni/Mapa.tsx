import React, { useEffect, useState } from "react";
import { APIUbytovanýUživatel, fetchUbytovaníUživatelé } from "../../api/ubytovani";
import { mapa, jeSchoditě } from "./mockMapa";

type TMapaProps = {

};

export const Mapa: React.FC<TMapaProps> = (props) => {
  const {} = props;

  const [uživateléPodlePokoje, setUživateléPodlePokoje] = useState<{ [pokoj: string]: APIUbytovanýUživatel[] }>({});

  useEffect(() => {
    void (async () => {
      const uživatelé = await fetchUbytovaníUživatelé();
      const podlePokoje: typeof uživateléPodlePokoje = {};
      for (const uživatel of uživatelé) {
        if (!podlePokoje[uživatel.pokoj])
          podlePokoje[uživatel.pokoj] = [];
        podlePokoje[uživatel.pokoj].push(uživatel);
      }

      setUživateléPodlePokoje(podlePokoje);
    })();

  }, []);

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
          const uživatelé = jeSchoditě(místnost) ? [] : uživateléPodlePokoje[místnost.značení] ?? []

          return <div style={{
            width: 50 * místnost.šířka,
            height: 70,
            borderWidth: 2,
            borderColor: "black",
            borderStyle: "solid",
            backgroundColor: "lightgray",
            boxSizing: "border-box",
            flexShrink: 0,
          }}>
            <div style={{ width: "auto", textAlign: "center", }}>
              {popis}
            </div>
            {
              uživatelé.map(x=>{
                return <div style={{whiteSpace:"nowrap"}}>
                  {x.login_uzivatele + " " + x.id_uzivatele}
                </div>;
              })
            }
          </div>;
        })}
      </div>
    )}
  </>;
};

Mapa.displayName = "Mapa";
