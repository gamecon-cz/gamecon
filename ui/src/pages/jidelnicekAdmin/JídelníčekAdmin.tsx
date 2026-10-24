import { h } from "preact";
import { useEffect, useState } from "preact/hooks";
import { fetchAdminMealMenu, saveMealMenuSlot } from "../../api/symfony/endpoints";
import { ApiAdminMealMenu, ApiAdminMealMenuSlot } from "../../api/symfony/types";
import { datumCesky, poDnech, velkePismeno } from "../jidlo/jidelnicek";
import {
  doZapisu,
  jeVychozi,
  jeZmeneno,
  KonceptJidla,
  konceptZeSlotu,
  nastavVychozi,
  noveJidlo,
  posun,
  pridejKopii,
} from "./koncept";
import "./JídelníčekAdmin.less";

/**
 * Zadávání jídelníčku: ke každému obědu a večeři jídla, ze kterých si účastníci vybírají.
 * Ukládá se po jednotlivých obědech a večeřích, rozpracované ostatní zůstanou.
 */
export function JídelníčekAdmin() {
  const [menu, setMenu] = useState<ApiAdminMealMenu | null>(null);
  const [koncepty, setKoncepty] = useState<Record<number, KonceptJidla[]>>({});
  // Víc slotů se může ukládat najednou; tlačítko se odemkne, až doběhne právě jeho uložení.
  const [ukladane, setUkladane] = useState<Set<number>>(new Set());
  const [chybySlotu, setChybySlotu] = useState<Record<number, string>>({});
  const [chyba, setChyba] = useState<string | null>(null);

  const prevezmiMenu = (data: ApiAdminMealMenu, jenSlot?: number) => {
    setMenu(data);
    setKoncepty((puvodni) => {
      const nove: Record<number, KonceptJidla[]> = {};
      for (const slot of data.slots) {
        nove[slot.variantId] = jenSlot === undefined || slot.variantId === jenSlot || !puvodni[slot.variantId]
          ? konceptZeSlotu(slot)
          : puvodni[slot.variantId];
      }
      return nove;
    });
  };

  useEffect(() => {
    fetchAdminMealMenu()
      .then((data) => prevezmiMenu(data))
      .catch((e: unknown) => setChyba(e instanceof Error ? e.message : "Nepodařilo se načíst jídelníček"));
  }, []);

  if (chyba) return <div class="jidelnicek-admin--chyba">Chyba: {chyba}</div>;
  if (!menu) return <div>Načítám jídelníček…</div>;

  const uloz = async (slot: ApiAdminMealMenuSlot) => {
    setUkladane((puvodni) => new Set(puvodni).add(slot.variantId));
    setChybySlotu((puvodni) => ({ ...puvodni, [slot.variantId]: "" }));
    try {
      prevezmiMenu(await saveMealMenuSlot(slot.variantId, doZapisu(koncepty[slot.variantId] ?? [])), slot.variantId);
    } catch (e: unknown) {
      setChybySlotu((puvodni) => ({
        ...puvodni,
        [slot.variantId]: e instanceof Error ? e.message : "Uložení se nepodařilo",
      }));
    } finally {
      setUkladane((puvodni) => {
        const zbyva = new Set(puvodni);
        zbyva.delete(slot.variantId);
        return zbyva;
      });
    }
  };

  const zmen = (variantId: number, koncept: KonceptJidla[]) =>
    setKoncepty((puvodni) => ({ ...puvodni, [variantId]: koncept }));

  return (
    <div class="jidelnicek-admin">
      <p>
        Jídelníček uvidí účastníci od <b>{datumCesky(menu.publishedFrom)}</b>
        {menu.published ? " (už je zveřejněný)" : ""}, vybírat si mohou do <b>{datumCesky(menu.choiceDeadline)}</b>
        {menu.choiceClosed ? " (výběr skončil)" : ""}. Termíny jsou v Nastavení
        (<i>Zveřejnění jídelníčku</i> a <i>Ukončení prodeje jídla</i>). Kdo si nevybere, dostane výchozí jídlo.
      </p>
      {menu.slots.length === 0 && <p>V e-shopu letos nejsou žádné obědy ani večeře.</p>}
      {poDnech(menu.slots).map((den) => (
        <div class="jidelnicek-admin--den" key={den.day}>
          <h4>{velkePismeno(den.dayName)}</h4>
          {den.sloty.map((slot) => {
            const obedStejnehoDne = slot.name.toLowerCase().startsWith("večeře")
              ? den.sloty.find((kandidat) => kandidat.name.toLowerCase().startsWith("oběd"))
              : undefined;
            return (
              <EditorSlotu
                key={slot.variantId}
                slot={slot}
                koncept={koncepty[slot.variantId] ?? []}
                obed={obedStejnehoDne ? koncepty[obedStejnehoDne.variantId] : undefined}
                uklada={ukladane.has(slot.variantId)}
                vyberSkoncil={menu.choiceClosed}
                chyba={chybySlotu[slot.variantId] ?? ""}
                onZmena={(koncept) => zmen(slot.variantId, koncept)}
                onUloz={() => { void uloz(slot); }}
              />
            );
          })}
        </div>
      ))}
    </div>
  );
}

function EditorSlotu({ slot, koncept, obed, uklada, vyberSkoncil, chyba, onZmena, onUloz }: {
  slot: ApiAdminMealMenuSlot;
  koncept: KonceptJidla[];
  obed?: KonceptJidla[];
  uklada: boolean;
  /** Po termínu už počty šly do jídelny a účastníci si nový výběr neudělají. */
  vyberSkoncil: boolean;
  chyba: string;
  onZmena: (koncept: KonceptJidla[]) => void;
  onUloz: () => void;
}) {
  const zmeneno = jeZmeneno(koncept, slot);
  const uprav = (klic: string, zmena: Partial<KonceptJidla>) =>
    onZmena(koncept.map((jidlo) => (jidlo.klic === klic ? { ...jidlo, ...zmena } : jidlo)));
  const odeber = (jidlo: KonceptJidla) => {
    if (jidlo.chosenCount > 0 && !window.confirm(
      `Jídlo „${jidlo.name}" si vybralo ${jidlo.chosenCount} lidí. Po smazání dostanou výchozí jídlo`
      + (vyberSkoncil ? " a sami si už jiné nevyberou, protože výběr jídel skončil (počty už mohla dostat jídelna)." : ".")
      + " Smazat?",
    )) {
      return;
    }
    onZmena(koncept.filter((kandidat) => kandidat.klic !== jidlo.klic));
  };
  const lzeKopirovat = obed !== undefined && obed.some((jidlo) => jidlo.name.trim() !== "");

  return (
    <div class="jidelnicek-admin--slot">
      <div class="jidelnicek-admin--hlavicka">
        <b>{slot.name}</b>
        <span class="jidelnicek-admin--pocty">
          prodáno {slot.portionCount} porcí, bez výběru {slot.unchosenCount}
        </span>
      </div>
      {koncept.length > 0 && (
        <table class="jidelnicek-admin--tabulka">
          <thead>
            <tr>
              <th>Jídlo</th>
              <th>Popis (alergeny, příloha)</th>
              <th>Vegetariánské</th>
              <th>Výchozí</th>
              <th>Vybralo</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {koncept.map((jidlo, index) => (
              <tr key={jidlo.klic}>
                <td>
                  <input
                    type="text"
                    maxLength={255}
                    value={jidlo.name}
                    aria-label="Název jídla"
                    onInput={(e) => uprav(jidlo.klic, { name: (e.target as HTMLInputElement).value })}
                  />
                </td>
                <td>
                  <input
                    type="text"
                    maxLength={255}
                    value={jidlo.description}
                    aria-label="Popis jídla"
                    onInput={(e) => uprav(jidlo.klic, { description: (e.target as HTMLInputElement).value })}
                  />
                </td>
                <td class="jidelnicek-admin--stred">
                  <input
                    type="checkbox"
                    checked={jidlo.vegetarian}
                    aria-label="Vegetariánské"
                    onChange={(e) => uprav(jidlo.klic, { vegetarian: (e.target as HTMLInputElement).checked })}
                  />
                </td>
                <td class="jidelnicek-admin--stred">
                  <input
                    type="radio"
                    name={`vychozi-${slot.variantId}`}
                    checked={jeVychozi(koncept, jidlo)}
                    aria-label="Výchozí jídlo"
                    onChange={() => onZmena(nastavVychozi(koncept, jidlo.klic))}
                  />
                </td>
                <td class="jidelnicek-admin--stred">{jidlo.id !== undefined ? jidlo.chosenCount : ""}</td>
                <td class="jidelnicek-admin--akce">
                  <button type="button" title="Posunout výš" disabled={index === 0} onClick={() => onZmena(posun(koncept, index, -1))}>↑</button>
                  <button type="button" title="Posunout níž" disabled={index === koncept.length - 1} onClick={() => onZmena(posun(koncept, index, 1))}>↓</button>
                  <button type="button" title="Smazat" onClick={() => odeber(jidlo)}>✕</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <div class="jidelnicek-admin--tlacitka">
        <button type="button" onClick={() => onZmena([...koncept, noveJidlo()])}>Přidat jídlo</button>
        {lzeKopirovat && (
          <button type="button" onClick={() => onZmena(pridejKopii(koncept, obed ?? []))}>
            Zkopírovat jídla z obědu
          </button>
        )}
        <button type="button" disabled={!zmeneno || uklada} onClick={onUloz}>
          {uklada ? "Ukládám…" : "Uložit"}
        </button>
        {zmeneno && !uklada && <span class="jidelnicek-admin--neulozeno">neuloženo</span>}
      </div>
      {chyba && <p class="jidelnicek-admin--chyba">{chyba}</p>}
    </div>
  );
}
