import { h } from "preact";
import { useEffect, useState } from "preact/hooks";
import { fetchAccommodation } from "../../api/symfony/endpoints";
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
 * Read-only for now: the write path saves the whole selection at once, because the nights
 * of a booking must be consecutive and a per-click save cannot express that. Until then
 * the legacy form below still does the saving.
 */
export function UbytovaniMřížka() {
  const [ubytovani, setUbytovani] = useState<ApiAccommodation | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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

  if (loading) return <div class="ubytovani-mrizka--loading">Načítám ubytování…</div>;
  if (error) return <div class="ubytovani-mrizka--error">{error}</div>;
  if (!ubytovani || ubytovani.types.length === 0) {
    return <div class="ubytovani-mrizka--empty">Žádné ubytování k dispozici.</div>;
  }

  const { days, types, minimumNights, saleClosed, roommate, declined } = ubytovani;

  return (
    <div class="ubytovani-mrizka">
      {saleClosed && (
        <p class="ubytovani-mrizka--uzavreno">Možnost objednání ubytování už skončila.</p>
      )}

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
                    <input type="checkbox" checked={cell.selected} disabled />
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

      {roommate && (
        <p class="ubytovani-mrizka--spolubydlici">
          Na pokoji s: <strong>{roommate}</strong>
        </p>
      )}
      {declined && (
        <p class="ubytovani-mrizka--nechci">Ubytování nechceš.</p>
      )}
    </div>
  );
}
