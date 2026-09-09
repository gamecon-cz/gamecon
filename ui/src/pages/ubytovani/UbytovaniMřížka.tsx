import { h } from "preact";
import { useEffect, useState } from "preact/hooks";
import { fetchAccommodation, saveAccommodation } from "../../api/symfony/endpoints";
import { ApiAccommodation, ApiAccommodationCell } from "../../api/symfony/types";
import "./UbytovaniMřížka.less";

/** Format price string for Czech locale: "1300.00" → "1300 Kč". */
function formatCena(price: string): string {
  const castka = parseFloat(price);
  if (isNaN(castka)) return `${price}\u2009Kč`;
  if (Number.isInteger(castka)) return `${castka}\u2009Kč`;
  return `${castka.toFixed(2).replace(".", ",")}\u2009Kč`;
}

function stavBuňky(cell: ApiAccommodationCell): string {
  if (cell.selected) return "ubytovani-mrizka--vybrano";
  if (cell.soldOut) return "ubytovani-mrizka--vyprodano";
  if (cell.locked) return "ubytovani-mrizka--zamceno";
  return "";
}

/**
 * Every change sends the whole selection, not the one night that moved: the nights of a
 * booking have to be consecutive, so the server judges the set as a whole and refuses it
 * as a whole.
 */
export function UbytovaniMřížka() {
  const [ubytovani, setUbytovani] = useState<ApiAccommodation | null>(null);
  const [loading, setLoading] = useState(true);
  const [ukladani, setUkladani] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [rozpojeno, setRozpojeno] = useState(false);

  useEffect(() => {
    fetchAccommodation()
      .then((data) => {
        setUbytovani(data);
        setLoading(false);
      })
      .catch((chyba: unknown) => {
        setError(chyba instanceof Error ? chyba.message : "Nepodařilo se načíst ubytování");
        setLoading(false);
      });
  }, []);

  const uloz = async (
    variantIds: number[],
    zmena: Partial<ApiAccommodation> & { restoreBreakfasts?: boolean } = {},
  ) => {
    if (!ubytovani) return;
    setUkladani(true);
    setError(null);
    try {
      setUbytovani(await saveAccommodation({
        variantIds,
        roommate: zmena.roommate !== undefined ? zmena.roommate : ubytovani.roommate,
        declined: zmena.declined !== undefined ? zmena.declined : ubytovani.declined,
        restoreBreakfasts: zmena.restoreBreakfasts ?? false,
      }));
    } catch (chyba: unknown) {
      setError(chyba instanceof Error ? chyba.message : "Uložení se nepodařilo");
      // The whole set was refused, so nothing was saved — reload rather than leave the grid
      // showing a pick the server does not have.
      try {
        setUbytovani(await fetchAccommodation());
      } catch {
        // Now the grid cannot be trusted to match the server, so leave it disabled rather
        // than invite a click that would save against a state we no longer know.
        setRozpojeno(true);
      }
    } finally {
      setUkladani(false);
    }
  };

  if (loading) return <div class="ubytovani-mrizka--loading">Načítám ubytování…</div>;
  if (error && !ubytovani) return <div class="ubytovani-mrizka--error">{error}</div>;
  if (!ubytovani || ubytovani.types.length === 0) {
    return <div class="ubytovani-mrizka--empty">Žádné ubytování k dispozici.</div>;
  }

  const {
    days,
    types,
    minimumNights,
    saleClosed,
    roommate,
    declined,
    selectedVariantIds,
    restorableBreakfasts,
  } = ubytovani;

  const prepniNoc = (cell: ApiAccommodationCell) => {
    void uloz(
      cell.selected
        ? selectedVariantIds.filter((variantId) => variantId !== cell.variantId)
        : [...selectedVariantIds, cell.variantId],
    );
  };

  return (
    <div class="ubytovani-mrizka">
      {saleClosed && (
        <p class="ubytovani-mrizka--uzavreno">Možnost objednání ubytování už skončila.</p>
      )}
      {error && <div class="ubytovani-mrizka--error">{error}</div>}

      <table class="ubytovani-mrizka--tabulka">
        <thead>
          <tr>
            <th></th>
            {days.map((den) => (
              <th key={den.day}>{den.name}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {types.map((typ) => (
            <tr key={typ.productId}>
              <td class="ubytovani-mrizka--typ">
                <span class="ubytovani-mrizka--nazev">{typ.name}</span>
                {typ.description && (
                  <span class="ubytovani-mrizka--popis">{typ.description}</span>
                )}
                <span class="ubytovani-mrizka--cena">
                  {typ.discountedPrice !== typ.price && (
                    <span class="ubytovani-mrizka--cena-puvodni">{formatCena(typ.price)}</span>
                  )}
                  {formatCena(typ.discountedPrice)}
                </span>
              </td>
              {days.map((den) => {
                const cell = typ.nights[den.day];
                if (!cell) {
                  return <td key={den.day} class="ubytovani-mrizka--nenabizeno"></td>;
                }

                return (
                  <td key={den.day} class={`ubytovani-mrizka--bunka ${stavBuňky(cell)}`}>
                    <input
                      type="checkbox"
                      checked={cell.selected}
                      disabled={cell.locked || ukladani || rozpojeno}
                      onChange={() => prepniNoc(cell)}
                    />
                    {cell.remaining !== null && (
                      <span class="ubytovani-mrizka--zbyva">
                        {cell.soldOut ? "vyprodáno" : `zbývá ${cell.remaining}`}
                      </span>
                    )}
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>

      <p class="ubytovani-mrizka--pravidlo">
        {minimumNights > 1
          ? `Noci musí na sebe navazovat, nejméně ${minimumNights}.`
          : "Noci musí na sebe navazovat."}
      </p>

      {restorableBreakfasts.length > 0 && (
        <p class="ubytovani-mrizka--snidane">
          Hotelový pokoj zrušil tyto snídaně: {restorableBreakfasts.join(", ")}.{" "}
          <button
            type="button"
            disabled={ukladani || rozpojeno}
            onClick={() => void uloz(selectedVariantIds, { restoreBreakfasts: true })}
          >
            Objednat je znovu
          </button>
        </p>
      )}

      <label class="ubytovani-mrizka--spolubydlici">
        Na pokoji s:{" "}
        <input
          type="text"
          value={roommate ?? ""}
          disabled={saleClosed || ukladani || rozpojeno}
          onChange={(udalost) =>
            void uloz(selectedVariantIds, {
              roommate: (udalost.target as HTMLInputElement).value,
            })
          }
        />
      </label>

      <label class="ubytovani-mrizka--nechci">
        <input
          type="checkbox"
          checked={declined}
          disabled={saleClosed || ukladani || rozpojeno || selectedVariantIds.length > 0}
          onChange={(udalost) =>
            void uloz([], { declined: (udalost.target as HTMLInputElement).checked })
          }
        />{" "}
        Ubytování nechci
      </label>
    </div>
  );
}
