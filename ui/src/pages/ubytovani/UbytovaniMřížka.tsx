import { h } from "preact";
import { useEffect, useRef, useState } from "preact/hooks";
import { fetchAccommodation, saveAccommodation } from "../../api/symfony/endpoints";
import { MountProps } from "../mountProps";
import { ApiAccommodation, ApiAccommodationCell, ApiAccommodationType } from "../../api/symfony/types";
import { formatCena } from "../../utils/castka";
import "./UbytovaniMřížka.less";

function stavBuňky(cell: ApiAccommodationCell): string {
  if (cell.selected) return "ubytovani-mrizka--vybrano";
  if (cell.soldOut) return "ubytovani-mrizka--vyprodano";
  if (cell.locked) return "ubytovani-mrizka--zamceno";
  return "";
}

function CenaTypu({ typ, showReason }: { typ: ApiAccommodationType; showReason: boolean }) {
  const sleveno = typ.discountedPrice !== typ.price;
  const ceny = (
    <>
      {sleveno && <span class="ubytovani-mrizka--cena-puvodni">{formatCena(typ.price)}</span>}
      {formatCena(typ.discountedPrice)}
    </>
  );

  return showReason && sleveno && typ.discountReason ? (
    <span class="hinted">
      {ceny}
      <span class="hint">{typ.discountReason}</span>
    </span>
  ) : (
    ceny
  );
}

/**
 * Every change sends the whole selection, not the one night that moved: the nights of a
 * booking have to be consecutive, so the server judges the set as a whole and refuses it
 * as a whole.
 */
export function UbytovaniMřížka({ customerId }: MountProps) {
  const [ubytovani, setUbytovani] = useState<ApiAccommodation | null>(null);
  const [loading, setLoading] = useState(true);
  const [ukladani, setUkladani] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [rozpojeno, setRozpojeno] = useState(false);
  const probihaUlozeni = useRef(false);

  useEffect(() => {
    fetchAccommodation(customerId)
      .then((data) => {
        setUbytovani(data);
        setLoading(false);
      })
      .catch((chyba: unknown) => {
        setError(chyba instanceof Error ? chyba.message : "Nepodařilo se načíst ubytování");
        setLoading(false);
      });
  }, [customerId]);

  const uloz = async (
    variantIds: number[],
    zmena: Partial<ApiAccommodation> & { restoreBreakfasts?: boolean } = {},
  ) => {
    // A second click can arrive before the re-render that disables the grid.
    if (!ubytovani || probihaUlozeni.current) return;
    probihaUlozeni.current = true;
    setUkladani(true);
    setError(null);
    try {
      setUbytovani(await saveAccommodation({
        variantIds,
        roommate: zmena.roommate !== undefined ? zmena.roommate : ubytovani.roommate,
        declined: zmena.declined !== undefined ? zmena.declined : ubytovani.declined,
        restoreBreakfasts: zmena.restoreBreakfasts ?? false,
      }, customerId));
    } catch (chyba: unknown) {
      setError(chyba instanceof Error ? chyba.message : "Uložení se nepodařilo");
      // The whole set was refused, so nothing was saved — reload rather than leave the grid
      // showing a pick the server does not have.
      try {
        setUbytovani(await fetchAccommodation(customerId));
      } catch {
        // Now the grid cannot be trusted to match the server, so leave it disabled rather
        // than invite a click that would save against a state we no longer know.
        setRozpojeno(true);
      }
    } finally {
      probihaUlozeni.current = false;
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

  const prepniNoc = (cell: ApiAccommodationCell, day: number) => {
    if (cell.selected) {
      void uloz(selectedVariantIds.filter((variantId) => variantId !== cell.variantId));
      return;
    }

    // A night holds one room type: picking another type for it replaces the first, as the
    // radio buttons of the old form did.
    const variantsOfTheNight = types.flatMap((typ) => typ.nights[day]?.variantId ?? []);
    void uloz([
      ...selectedVariantIds.filter((variantId) => !variantsOfTheNight.includes(variantId)),
      cell.variantId,
    ]);
  };

  const isAdminView = customerId !== undefined;

  // Jen pro obsluhu pultu: veřejná část došla, ale postele fyzicky jsou — drží se pro
  // orgy. Je to podnět ke kontrole, ne chyba.
  const seSahaDoRezervy =
    isAdminView &&
    types.some((typ) =>
      Object.values(typ.nights).some(
        (cell) => cell.soldOut && (cell.reservedForOrganizers ?? 0) > 0,
      ),
    );

  return (
    <div class="ubytovani-mrizka">
      {saleClosed && (
        <p class="ubytovani-mrizka--uzavreno">Možnost objednání ubytování už skončila.</p>
      )}
      {error && <div class="ubytovani-mrizka--error">{error}</div>}
      {seSahaDoRezervy && (
        <p class="ubytovani-mrizka--rezerva-varovani">
          ⚠ U některých nocí je veřejná část vyprodaná — prodáváš už z rezervy pro
          organizátory.
        </p>
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
                <div class="ubytovani-mrizka--typ-obsah">
                  <span class="ubytovani-mrizka--nazev">
                    {typ.name}
                    {/* Staff at the desk scan this table quickly, so the description waits in a hint. */}
                    {typ.description && isAdminView && (
                      <span class="hinted ubytovani-mrizka--napoveda">
                        <i class="fa-solid fa-circle-question" aria-hidden="true" />
                        <span class="hint">{typ.description}</span>
                      </span>
                    )}
                  </span>
                  {typ.description && !isAdminView && (
                    <span class="ubytovani-mrizka--popis">{typ.description}</span>
                  )}
                  <span class="ubytovani-mrizka--cena">
                    <CenaTypu typ={typ} showReason={isAdminView} />
                  </span>
                </div>
              </td>
              {days.map((den) => {
                const cell = typ.nights[den.day];
                if (!cell) {
                  return <td key={den.day} class="ubytovani-mrizka--nenabizeno"></td>;
                }

                const zakazano = cell.locked || ukladani || rozpojeno;

                return (
                  <td
                    key={den.day}
                    class={`ubytovani-mrizka--bunka ${stavBuňky(cell)} ${zakazano ? "" : "ubytovani-mrizka--klikaci"}`}
                    onClick={(udalost) => {
                      // The checkbox toggles itself; a click on the rest of the tile must do the same.
                      if (!zakazano && (udalost.target as HTMLElement).tagName !== "INPUT") {
                        prepniNoc(cell, den.day);
                      }
                    }}
                  >
                    <input
                      type="checkbox"
                      checked={cell.selected}
                      disabled={zakazano}
                      onChange={() => prepniNoc(cell, den.day)}
                    />
                    {cell.remaining !== null && (
                      <span class="ubytovani-mrizka--zbyva">
                        {cell.soldOut ? "vyprodáno" : `zbývá ${cell.remaining}`}
                        {/* Rezervu vidí jen obsluha pultu — účastníkovi by číslo, na
                            které nedosáhne, jen mátlo. */}
                        {isAdminView && cell.reservedForOrganizers ? (
                          <span class="ubytovani-mrizka--rezerva">
                            {` (+${cell.reservedForOrganizers} org)`}
                          </span>
                        ) : null}
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
