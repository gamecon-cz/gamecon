import { h } from "preact";
import { useEffect, useRef, useState } from "preact/hooks";
import { chooseMealDish, fetchMealMenu } from "../../api/symfony/endpoints";
import { ApiMealMenu, ApiMealMenuSlot, ApiMealPortion } from "../../api/symfony/types";
import { MountProps } from "../mountProps";
import { datumCesky, nazevJidla, poDnech, slotySPorci, velkePismeno, vychoziJidlo } from "./jidelnicek";
import "./VýběrJídel.less";

type Props = MountProps & {
  /**
   * Matice jídel ho zvyšuje po každé změně objednávky, aby se výběr načetl znovu i s nově
   * koupenými porcemi.
   */
  obnoveni?: number;
};

/**
 * Výběr konkrétního jídla k objednaným obědům a večeřím. Jídelníček přichází 14 dní před
 * GameConem; do té doby se jen oznamuje, kdy bude. Kdo si do konce prodeje jídla nevybere,
 * dostane výchozí jídlo.
 *
 * Stejná komponenta je v přihlášce, na stránce Jídelníček i na pultu (s `customerId`).
 */
export function VýběrJídel({ customerId, obnoveni = 0 }: Props) {
  const [menu, setMenu] = useState<ApiMealMenu | null>(null);
  const [chyba, setChyba] = useState<string | null>(null);
  const [ukladane, setUkladane] = useState<number | null>(null);
  // Načtení i uložení odpovídají celým jídelníčkem, takže platí jen ta nejnovější odpověď:
  // pomalejší starší požadavek by jinak vrátil porce nebo výběry, které už neplatí.
  const posledniPozadavek = useRef(0);

  useEffect(() => {
    const pozadavek = ++posledniPozadavek.current;
    fetchMealMenu(customerId)
      .then((data) => {
        if (pozadavek !== posledniPozadavek.current) return;
        setMenu(data);
        setChyba(null);
      })
      .catch((e: unknown) => {
        if (pozadavek !== posledniPozadavek.current) return;
        setChyba(e instanceof Error ? e.message : "Nepodařilo se načíst jídelníček");
      });
  }, [customerId, obnoveni]);

  if (chyba && !menu) return <div class="vyber-jidel--chyba">Chyba: {chyba}</div>;
  if (!menu) return null;

  const sloty = slotySPorci(menu.slots);
  if (sloty.length === 0) return null;

  const pult = customerId !== undefined;
  if (!menu.published && !pult) {
    return (
      <div class="vyber-jidel">
        <p class="vyber-jidel--info">
          Jídelníček zveřejníme {datumCesky(menu.publishedFrom)}. Potom si tu k objednaným obědům
          a večeřím vybereš konkrétní jídla.
        </p>
      </div>
    );
  }

  const vyber = async (porce: ApiMealPortion, hodnota: string) => {
    const pozadavek = ++posledniPozadavek.current;
    setUkladane(porce.orderItemId);
    setChyba(null);
    try {
      const ulozene = await chooseMealDish(porce.orderItemId, hodnota === "" ? null : Number(hodnota), customerId);
      if (pozadavek === posledniPozadavek.current) setMenu(ulozene);
    } catch (e: unknown) {
      setChyba(e instanceof Error ? e.message : "Výběr jídla se nepodařilo uložit");
    } finally {
      setUkladane(null);
    }
  };

  return (
    <div class="vyber-jidel">
      <h3 class="vyber-jidel--nadpis">Výběr jídel</h3>
      {menu.locked
        ? <p class="vyber-jidel--info">Výběr jídel skončil {datumCesky(menu.choiceDeadline)}, počty jsme poslali do jídelny.</p>
        : (
          <p class="vyber-jidel--info">
            K objednaným obědům a večeřím si vyber konkrétní jídlo. Změnit ho můžeš
            do {datumCesky(menu.choiceDeadline)} včetně. Kdo si nevybere, dostane výchozí jídlo.
          </p>
        )}
      {chyba && <p class="vyber-jidel--chyba">{chyba}</p>}
      {poDnech(sloty).map((den) => (
        <div class="vyber-jidel--den" key={den.day}>
          <div class="vyber-jidel--nazev-dne">{velkePismeno(den.dayName)}</div>
          {den.sloty.map((slot) => slot.portions.map((porce) => (
            <Porce
              key={porce.orderItemId}
              slot={slot}
              porce={porce}
              zamceno={menu.locked}
              uklada={ukladane === porce.orderItemId}
              zamcenoBehemUkladani={ukladane !== null}
              onVyber={(hodnota) => { void vyber(porce, hodnota); }}
            />
          )))}
        </div>
      ))}
    </div>
  );
}

function Porce({ slot, porce, zamceno, uklada, zamcenoBehemUkladani, onVyber }: {
  slot: ApiMealMenuSlot;
  porce: ApiMealPortion;
  zamceno: boolean;
  uklada: boolean;
  /** Ukládá se jiná porce; než odpoví, nejde měnit nic, aby se odpovědi nepřebíjely. */
  zamcenoBehemUkladani: boolean;
  onVyber: (hodnota: string) => void;
}) {
  const druh = slot.name.split(" ")[0] ?? slot.name;
  const vychozi = vychoziJidlo(slot);
  const dostane = slot.dishes.find((jidlo) => jidlo.id === porce.effectiveDishId);
  const idPorce = `vyber-jidel-${porce.orderItemId}`;

  if (slot.dishes.length === 0) {
    return (
      <div class="vyber-jidel--porce">
        <span class="vyber-jidel--druh">{druh}</span>
        <span class="vyber-jidel--nezadano">jídla zatím nejsou v jídelníčku</span>
      </div>
    );
  }

  if (zamceno) {
    return (
      <div class="vyber-jidel--porce">
        <span class="vyber-jidel--druh">{druh}</span>
        <span>
          {dostane ? nazevJidla(dostane) : "—"}
          {porce.chosenDishId === null && dostane && <span class="vyber-jidel--poznamka"> (výchozí)</span>}
        </span>
      </div>
    );
  }

  return (
    <div class={`vyber-jidel--porce ${porce.chosenDishId === null ? "vyber-jidel--nevybrano" : ""}`}>
      <label class="vyber-jidel--druh" htmlFor={idPorce}>{druh}</label>
      <div class="vyber-jidel--vyber">
        <select
          id={idPorce}
          value={porce.chosenDishId === null ? "" : String(porce.chosenDishId)}
          disabled={uklada || zamcenoBehemUkladani}
          onChange={(e) => onVyber((e.target as HTMLSelectElement).value)}
        >
          <option value="">{vychozi ? `nevybráno – dostaneš ${nazevJidla(vychozi)}` : "nevybráno"}</option>
          {slot.dishes.map((jidlo) => (
            <option key={jidlo.id} value={String(jidlo.id)}>{nazevJidla(jidlo)}</option>
          ))}
        </select>
        {dostane?.description && <div class="vyber-jidel--popis">{dostane.description}</div>}
      </div>
    </div>
  );
}
