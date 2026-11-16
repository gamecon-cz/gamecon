import { h } from "preact";
import { useEffect, useRef, useState } from "preact/hooks";
import { fetchAccommodation, saveAccommodation } from "../../api/symfony/endpoints";
import { SaveStatus, useSaveState } from "../../components/SaveStatus";
import { MountProps } from "../mountProps";
import { ApiAccommodation, ApiAccommodationCell, ApiAccommodationType } from "../../api/symfony/types";
import { formatCena } from "../../utils/castka";
import { stejnaMnozina, vyberTypNaNoci, zkontrolujVyberVMřížce } from "./vyberNoci";
import "./UbytovaniMřížka.less";

function třídyBuňky(
  cell: ApiAccommodationCell,
  vybrano: boolean,
  neulozeno: boolean,
  vyzadujeAkci: boolean,
): string {
  const třídy: string[] = [];
  if (vybrano) {
    třídy.push("ubytovani-mrizka--vybrano");
  } else if (cell.soldOut) {
    třídy.push("ubytovani-mrizka--vyprodano");
  } else if (cell.locked) {
    třídy.push("ubytovani-mrizka--zamceno");
  }
  // A full night that is not locked is one the desk may overbook; a night already past its
  // capacity is shown the same way, since both are the desk going beyond what is on offer.
  if (cell.soldOut && !cell.locked && !vybrano) {
    třídy.push("ubytovani-mrizka--k-preplneni");
  }
  // Not on a locked tile, which keeps its grey: orange says "you may", and there nobody may.
  if (cell.remaining !== null && cell.remaining < 0 && !cell.locked) {
    třídy.push("ubytovani-mrizka--preplneno");
  }
  if (neulozeno) {
    třídy.push("ubytovani-mrizka--neulozeno");
  }
  // Red is for a pick that stays unsaved until the person changes it, not for one on its way to the server.
  if (vyzadujeAkci) {
    třídy.push("ubytovani-mrizka--vyzaduje-akci");
  }

  return třídy.join(" ");
}

function textZbývá(cell: ApiAccommodationCell, vybrano: boolean, remaining: number): string {
  // Only the desk is sent a number below zero, and it should see how far over a night went.
  if (cell.soldOut && !vybrano && remaining >= 0) return "vyprodáno";

  return `zbývá ${remaining < 0 ? `−${-remaining}` : remaining}`;
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

type Zápis = {
  /** Nights to save; left out, the nights the server holds are sent back unchanged. */
  variantIds?: number[];
  roommate?: string | null;
  declined?: boolean;
  restoreBreakfasts?: boolean;
};

/**
 * Every save sends the whole selection, not the one night that moved: the nights of a
 * booking have to be consecutive, so the server judges the set as a whole and refuses it
 * as a whole. A pick that cannot pass that yet (one night of the two it takes) therefore waits
 * in the grid, marked as not saved, and goes out as soon as it can. The exception is a pick
 * with a night the grid does not show: that one is sent as it is (`zkontrolujVyberVMřížce()`).
 */
export function UbytovaniMřížka({ customerId }: MountProps) {
  const [ubytovani, setUbytovani] = useState<ApiAccommodation | null>(null);
  const [vyber, setVyber] = useState<number[] | null>(null);
  const [loading, setLoading] = useState(true);
  const [ukladani, setUkladani] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [rozpojeno, setRozpojeno] = useState(false);
  const probihaUlozeni = useRef(false);
  const vyberRef = useRef<number[] | null>(null);
  vyberRef.current = vyber;
  const stav = useSaveState();

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

  const uloz = async (zápis: Zápis) => {
    // A second click can arrive before the re-render that disables the grid.
    if (!ubytovani || probihaUlozeni.current) return;
    probihaUlozeni.current = true;
    setUkladani(true);
    setError(null);
    stav.markSaving();
    try {
      const uloženo = await saveAccommodation({
        variantIds: zápis.variantIds ?? ubytovani.selectedVariantIds,
        roommate: zápis.roommate !== undefined ? zápis.roommate : ubytovani.roommate,
        declined: zápis.declined !== undefined ? zápis.declined : ubytovani.declined,
        restoreBreakfasts: zápis.restoreBreakfasts ?? false,
      }, customerId);
      setUbytovani(uloženo);
      if (zápis.variantIds !== undefined) {
        setVyber(null);
      }
      // A pick that was waiting for more nights is still waiting: this save did not carry it.
      const čekající = zápis.variantIds === undefined ? vyberRef.current : null;
      const kontrola = čekající === null
        ? null
        : zkontrolujVyberVMřížce(uloženo.types, čekající, uloženo.minimumNights);
      if (čekající !== null) {
        stav.markPending(kontrola !== null && !kontrola.platny ? kontrola.duvod : undefined);
      } else {
        stav.markSaved();
      }
    } catch (chyba: unknown) {
      setError(chyba instanceof Error ? chyba.message : "Uložení se nepodařilo");
      setVyber(null);
      stav.markFailed();
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

  // What the grid shows as ticked: the pick that has not been saved yet, else what the server holds.
  const vybrané = vyber ?? selectedVariantIds;

  const změňVýběr = (další: number[]) => {
    // A click can arrive before the re-render that disables the grid. Another save is running
    // then and this one would be dropped, so drop the click whole: a tick that was never sent
    // must not stay on screen as if it were waiting.
    if (probihaUlozeni.current) return;

    if (stejnaMnozina(další, selectedVariantIds)) {
      setVyber(null);
      stav.markIdle();
      return;
    }

    setVyber(další);
    const kontrola = zkontrolujVyberVMřížce(types, další, minimumNights);
    if (!kontrola.platny) {
      stav.markPending(kontrola.duvod);
      return;
    }
    void uloz({ variantIds: další });
  };

  const prepniNoc = (typ: ApiAccommodationType, cell: ApiAccommodationCell, day: number) => {
    if (vybrané.includes(cell.variantId)) {
      změňVýběr(vybrané.filter((variantId) => variantId !== cell.variantId));
      return;
    }

    // As in the old form, a room type picked on one night moves the other picked nights to it.
    změňVýběr(vyberTypNaNoci(types, vybrané, typ, day, cell.variantId));
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

      <div class="ubytovani-mrizka--hlavicka">
        <p class="ubytovani-mrizka--pravidlo">
          {minimumNights > 1
            ? `Noci musí na sebe navazovat, nejméně ${minimumNights}.`
            : "Noci musí na sebe navazovat."}
        </p>
        <div class="ubytovani-mrizka--stav">
          <SaveStatus state={stav.state} />
        </div>
      </div>

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
                const vybrano = vybrané.includes(cell.variantId);
                const neulozeno = vyber !== null && vybrano !== cell.selected;
                const vyzadujeAkci = neulozeno && stav.state.kind === "pending";

                return (
                  <td
                    key={den.day}
                    class={`ubytovani-mrizka--bunka ${třídyBuňky(cell, vybrano, neulozeno, vyzadujeAkci)} ${zakazano ? "" : "ubytovani-mrizka--klikaci"}`}
                    onClick={(udalost) => {
                      // The checkbox toggles itself; a click on the rest of the tile must do the same.
                      if (!zakazano && (udalost.target as HTMLElement).tagName !== "INPUT") {
                        prepniNoc(typ, cell, den.day);
                      }
                    }}
                  >
                    <input
                      type="checkbox"
                      checked={vybrano}
                      disabled={zakazano}
                      onChange={() => prepniNoc(typ, cell, den.day)}
                    />
                    {cell.remaining !== null && (
                      <span class="ubytovani-mrizka--zbyva">
                        {textZbývá(cell, vybrano, cell.remaining)}
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

      {restorableBreakfasts.length > 0 && (
        <p class="ubytovani-mrizka--snidane">
          Hotelový pokoj zrušil tyto snídaně: {restorableBreakfasts.join(", ")}.{" "}
          <button
            type="button"
            disabled={ukladani || rozpojeno}
            onClick={() => void uloz({ restoreBreakfasts: true })}
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
            void uloz({ roommate: (udalost.target as HTMLInputElement).value })
          }
        />
      </label>

      <label class="ubytovani-mrizka--nechci">
        <input
          type="checkbox"
          checked={declined}
          disabled={saleClosed || ukladani || rozpojeno || vybrané.length > 0}
          onChange={(udalost) =>
            void uloz({ variantIds: [], declined: (udalost.target as HTMLInputElement).checked })
          }
        />{" "}
        Ubytování nechci
      </label>
    </div>
  );
}
