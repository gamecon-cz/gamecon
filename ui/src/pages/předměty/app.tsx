import { FunctionComponent } from "preact";
import { useCallback, useEffect, useMemo, useState } from "preact/hooks";
import {
  createProduct,
  deleteProduct,
  fetchProducts,
  fetchProductTags,
  updateProduct,
} from "../../api/symfony/endpoints";
import {
  ApiProduct,
  ApiProductTag,
  ApiProductVariant,
  ApiProductWrite,
} from "../../api/symfony/types";
import "./app.less";

/** Category tag codes that classify a product (mutually exclusive). */
const KATEGORIE_TAG_KODY = [
  "predmet",
  "ubytovani",
  "tricko",
  "jidlo",
  "vstupne",
  "parcon",
  "proplaceni-bonusu",
] as const;

const ACCOMMODATION_TAG_CODE = "ubytovani";

const STAV_NAZVY: Record<number, string> = {
  0: "Vyřazený",
  1: "Veřejný",
  2: "Orgové",
  3: "Prodejný na místě",
};

type EditorState =
  | { mode: "closed" }
  | { mode: "new" }
  | { mode: "edit"; produkt: ApiProduct };

export const Předměty: FunctionComponent = () => {
  const [produkty, setProdukty] = useState<ApiProduct[] | null | undefined>(undefined);
  const [tagy, setTagy] = useState<ApiProductTag[] | null | undefined>(undefined);
  const [editorState, setEditorState] = useState<EditorState>({ mode: "closed" });
  const [loadError, setLoadError] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setLoadError(null);
    try {
      const [produktyResult, tagyResult] = await Promise.all([
        fetchProducts(),
        fetchProductTags(),
      ]);
      setProdukty(produktyResult);
      setTagy(tagyResult);
    } catch (error) {
      console.error(error);
      setLoadError(error instanceof Error ? error.message : String(error));
      setProdukty(null);
      setTagy(null);
    }
  }, []);

  useEffect(() => {
    void loadData();
  }, [loadData]);

  const kategorieTagy = useMemo(
    () => (tagy ?? []).filter((tag) => KATEGORIE_TAG_KODY.includes(tag.code as typeof KATEGORIE_TAG_KODY[number])),
    [tagy],
  );

  const smazat = useCallback(async (produkt: ApiProduct) => {
    if (!confirm(`Opravdu smazat „${produkt.name}"?`)) return;
    try {
      await deleteProduct(produkt.id);
      await loadData();
    } catch (error) {
      alert(error instanceof Error ? error.message : String(error));
    }
  }, [loadData]);

  const uložit = useCallback(async (payload: ApiProductWrite, editingId: number | null) => {
    try {
      if (editingId === null) {
        await createProduct(payload);
      } else {
        await updateProduct(editingId, payload);
      }
      setEditorState({ mode: "closed" });
      await loadData();
    } catch (error) {
      throw error; // let the editor surface it inline
    }
  }, [loadData]);

  if (produkty === undefined || tagy === undefined) {
    return <div className="produkty__loading">Načítám produkty…</div>;
  }

  if (produkty === null || tagy === null) {
    return (
      <div className="produkty">
        <div className="produkty__error">
          Nepodařilo se načíst produkty: {loadError ?? "neznámá chyba"}
        </div>
        <button className="produkty__button" onClick={() => void loadData()}>
          Zkusit znovu
        </button>
      </div>
    );
  }

  return (
    <div className="produkty">
      {editorState.mode === "closed" && (
        <>
          <button
            className="produkty__button produkty__button--primary"
            onClick={() => setEditorState({ mode: "new" })}
          >
            + Nová položka
          </button>
          <table className="produkty__table">
            <thead>
              <tr>
                <th>Název</th>
                <th>Cena za kus</th>
                <th>Kategorie</th>
                <th>Kusů celkem</th>
                <th>Stav</th>
                <th>Snídaně v&nbsp;ceně</th>
                <th>Variant</th>
                <th>Akce</th>
              </tr>
            </thead>
            <tbody>
              {produkty.map((produkt) => {
                const kategorieTag = (produkt.tags ?? []).find((tag) =>
                  KATEGORIE_TAG_KODY.includes(tag.code as typeof KATEGORIE_TAG_KODY[number]),
                );
                return (
                  <tr key={produkt.id}>
                    <td>{produkt.name}</td>
                    <td>{produkt.currentPrice}.-</td>
                    <td>{kategorieTag?.name ?? "—"}</td>
                    <td>{produkt.producedQuantity ?? "∞"}</td>
                    <td>{STAV_NAZVY[produkt.state] ?? produkt.state}</td>
                    <td>{produkt.breakfastIncluded ? "ano" : ""}</td>
                    <td>{produkt.variants?.length ?? 0}</td>
                    <td className="produkty__actions">
                      <button
                        className="produkty__button"
                        onClick={() => setEditorState({ mode: "edit", produkt })}
                      >
                        <i className="fa fa-pencil-square-o" aria-hidden="true" />{" "}Upravit
                      </button>
                      <button
                        className="produkty__button produkty__button--danger"
                        title="Smazat"
                        onClick={() => void smazat(produkt)}
                      >
                        <i className="fa fa-trash" aria-hidden="true" />{" "}Smazat
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </>
      )}

      {editorState.mode !== "closed" && (
        <EditorPředmětu
          produkt={editorState.mode === "edit" ? editorState.produkt : null}
          kategorieTagy={kategorieTagy}
          uložit={uložit}
          zrušit={() => setEditorState({ mode: "closed" })}
        />
      )}
    </div>
  );
};

Předměty.displayName = "Předměty";

// ==================== Editor ====================

type EditorProps = {
  produkt: ApiProduct | null;
  kategorieTagy: ApiProductTag[];
  uložit: (payload: ApiProductWrite, editingId: number | null) => Promise<void>;
  zrušit: () => void;
};

const EditorPředmětu: FunctionComponent<EditorProps> = ({
  produkt,
  kategorieTagy,
  uložit,
  zrušit,
}) => {
  const isNew = produkt === null;
  const initialCategory = useMemo(
    () => (produkt?.tags ?? []).find((tag) =>
      KATEGORIE_TAG_KODY.includes(tag.code as typeof KATEGORIE_TAG_KODY[number]),
    )?.code ?? "",
    [produkt],
  );

  const [name, setName] = useState(produkt?.name ?? "");
  const [code, setCode] = useState(produkt?.code ?? "");
  const [currentPrice, setCurrentPrice] = useState(produkt?.currentPrice ?? "0.00");
  const [state, setState] = useState(produkt?.state ?? 1);
  const [availableUntil, setAvailableUntil] = useState(produkt?.availableUntil ?? "");
  const [producedQuantity, setProducedQuantity] = useState<string>(
    produkt?.producedQuantity?.toString() ?? "",
  );
  const [accommodationDay, setAccommodationDay] = useState<string>(
    produkt?.accommodationDay?.toString() ?? "",
  );
  const [breakfastIncluded, setBreakfastIncluded] = useState(produkt?.breakfastIncluded ?? false);
  const [description, setDescription] = useState(produkt?.description ?? "");
  const [reservedForOrganizers, setReservedForOrganizers] = useState<string>(
    produkt?.reservedForOrganizers?.toString() ?? "",
  );
  const [category, setCategory] = useState<string>(initialCategory);
  const [variants, setVariants] = useState<ApiProductVariant[]>(
    produkt?.variants?.map((variant) => ({ ...variant })) ?? [],
  );
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  // Keep breakfastIncluded consistent with category: turn it off when
  // switching away from 'ubytovani' (server-side validator will reject
  // otherwise, and toggling is confusing for the admin).
  const isAccommodation = category === ACCOMMODATION_TAG_CODE;
  const handleCategoryChange = useCallback((nextCategory: string) => {
    setCategory(nextCategory);
    if (nextCategory !== ACCOMMODATION_TAG_CODE) {
      setBreakfastIncluded(false);
      setAccommodationDay("");
    }
  }, []);

  const addVariant = useCallback(() => {
    setVariants((currentVariants) => [
      ...currentVariants,
      {
        name: "",
        code: "",
        price: null,
        remainingQuantity: null,
        reservedForOrganizers: null,
        accommodationDay: null,
        position: currentVariants.length,
      },
    ]);
  }, []);

  const updateVariant = useCallback(
    (index: number, patch: Partial<ApiProductVariant>) => {
      setVariants((currentVariants) =>
        currentVariants.map((variant, variantIndex) =>
          variantIndex === index ? { ...variant, ...patch } : variant,
        ),
      );
    },
    [],
  );

  const removeVariant = useCallback((index: number) => {
    setVariants((currentVariants) =>
      currentVariants.filter((_variant, variantIndex) => variantIndex !== index),
    );
  }, []);

  const handleSave = useCallback(async () => {
    setSaveError(null);

    const categoryTag = kategorieTagy.find((tag) => tag.code === category);
    if (!categoryTag?.["@id"]) {
      setSaveError("Vyberte kategorii produktu.");
      return;
    }

    const payload: ApiProductWrite = {
      name,
      code,
      currentPrice,
      state: Number(state),
      availableUntil: availableUntil || null,
      producedQuantity: producedQuantity === "" ? null : Number(producedQuantity),
      accommodationDay: accommodationDay === "" ? null : Number(accommodationDay),
      breakfastIncluded,
      description,
      reservedForOrganizers:
        reservedForOrganizers === "" ? null : Number(reservedForOrganizers),
      tags: [categoryTag["@id"]],
      variants: variants.map((variant, position) => ({
        ...variant,
        position,
      })),
    };

    setSaving(true);
    try {
      await uložit(payload, produkt?.id ?? null);
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : String(error));
    } finally {
      setSaving(false);
    }
  }, [
    accommodationDay,
    availableUntil,
    breakfastIncluded,
    category,
    code,
    currentPrice,
    description,
    kategorieTagy,
    name,
    producedQuantity,
    produkt,
    reservedForOrganizers,
    state,
    uložit,
    variants,
  ]);

  return (
    <div className="produkty__editor">
      <h4>{isNew ? "Nová položka" : `Upravit: ${produkt?.name}`}</h4>

      {saveError && <div className="produkty__error">{saveError}</div>}

      <label className="produkty__field">
        <span>Název</span>
        <input
          type="text"
          value={name}
          onInput={(event) => setName((event.target as HTMLInputElement).value)}
        />
      </label>

      <label className="produkty__field">
        <span>Kód</span>
        <input
          type="text"
          value={code}
          onInput={(event) => setCode((event.target as HTMLInputElement).value)}
        />
      </label>

      <label className="produkty__field">
        <span>Cena</span>
        <input
          type="text"
          value={currentPrice}
          onInput={(event) => setCurrentPrice((event.target as HTMLInputElement).value)}
        />
      </label>

      <label className="produkty__field">
        <span>Stav</span>
        <select
          value={state}
          onChange={(event) => setState(Number((event.target as HTMLSelectElement).value))}
        >
          <option value={0}>0 — mimo</option>
          <option value={1}>1 — veřejný</option>
          <option value={2}>2 — podpultový</option>
          <option value={3}>3 — pozastavený</option>
        </select>
      </label>

      <label className="produkty__field">
        <span>Kategorie</span>
        <select
          value={category}
          onChange={(event) =>
            handleCategoryChange((event.target as HTMLSelectElement).value)
          }
        >
          <option value="">— zvolte —</option>
          {kategorieTagy.map((tag) => (
            <option key={tag.code} value={tag.code}>
              {tag.name ?? tag.code}
            </option>
          ))}
        </select>
      </label>

      <label className="produkty__field">
        <span>Snídaně v ceně</span>
        <input
          type="checkbox"
          checked={breakfastIncluded}
          disabled={!isAccommodation}
          onChange={(event) =>
            setBreakfastIncluded((event.target as HTMLInputElement).checked)
          }
        />
        {!isAccommodation && (
          <span style={{ marginLeft: 8, color: "#888" }}>
            (dostupné pouze pro ubytování)
          </span>
        )}
      </label>

      <label className="produkty__field">
        <span>Nabízet do</span>
        <input
          type="datetime-local"
          value={availableUntil ? availableUntil.substring(0, 16) : ""}
          onInput={(event) => setAvailableUntil((event.target as HTMLInputElement).value)}
        />
      </label>

      <label className="produkty__field">
        <span>Kusů vyrobeno</span>
        <input
          type="number"
          value={producedQuantity}
          onInput={(event) => setProducedQuantity((event.target as HTMLInputElement).value)}
        />
      </label>

      <label className="produkty__field">
        <span>Ubytovací den (0–4)</span>
        <input
          type="number"
          min={0}
          max={4}
          value={accommodationDay}
          disabled={!isAccommodation}
          onInput={(event) => setAccommodationDay((event.target as HTMLInputElement).value)}
        />
        {!isAccommodation && (
          <span style={{ marginLeft: 8, color: "#888" }}>
            (dostupné pouze pro ubytování)
          </span>
        )}
      </label>

      <label className="produkty__field">
        <span>Rezerva pro organizátory</span>
        <input
          type="number"
          value={reservedForOrganizers}
          onInput={(event) =>
            setReservedForOrganizers((event.target as HTMLInputElement).value)
          }
        />
      </label>

      <label className="produkty__field">
        <span>Popis</span>
        <textarea
          value={description}
          rows={3}
          onInput={(event) => setDescription((event.target as HTMLTextAreaElement).value)}
        />
      </label>

      <div className="produkty__variants">
        <h4>Varianty</h4>
        <table>
          <thead>
            <tr>
              <th>Název</th>
              <th>Kód</th>
              <th>Cena (prázdné = dědí)</th>
              <th>Skladem</th>
              <th>Den</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {variants.map((variant, index) => (
              <tr key={variant.id ?? `new-${index}`}>
                <td>
                  <input
                    type="text"
                    value={variant.name}
                    onInput={(event) =>
                      updateVariant(index, {
                        name: (event.target as HTMLInputElement).value,
                      })
                    }
                  />
                </td>
                <td>
                  <input
                    type="text"
                    value={variant.code}
                    onInput={(event) =>
                      updateVariant(index, {
                        code: (event.target as HTMLInputElement).value,
                      })
                    }
                  />
                </td>
                <td>
                  <input
                    type="text"
                    value={variant.price ?? ""}
                    onInput={(event) => {
                      const nextPrice = (event.target as HTMLInputElement).value;
                      updateVariant(index, { price: nextPrice === "" ? null : nextPrice });
                    }}
                  />
                </td>
                <td>
                  <input
                    type="number"
                    value={variant.remainingQuantity ?? ""}
                    onInput={(event) => {
                      const rawValue = (event.target as HTMLInputElement).value;
                      updateVariant(index, {
                        remainingQuantity: rawValue === "" ? null : Number(rawValue),
                      });
                    }}
                  />
                </td>
                <td>
                  <input
                    type="number"
                    min={0}
                    max={4}
                    value={variant.accommodationDay ?? ""}
                    onInput={(event) => {
                      const rawValue = (event.target as HTMLInputElement).value;
                      updateVariant(index, {
                        accommodationDay: rawValue === "" ? null : Number(rawValue),
                      });
                    }}
                  />
                </td>
                <td>
                  <button
                    className="produkty__button produkty__button--danger"
                    onClick={() => removeVariant(index)}
                  >
                    ×
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <button className="produkty__button" onClick={addVariant}>
          + Přidat variantu
        </button>
      </div>

      <div style={{ marginTop: "1em" }}>
        <button
          className="produkty__button produkty__button--primary"
          disabled={saving}
          onClick={() => void handleSave()}
        >
          {saving ? "Ukládám…" : "Uložit"}
        </button>
        <button
          className="produkty__button"
          disabled={saving}
          onClick={zrušit}
        >
          Zrušit
        </button>
      </div>
    </div>
  );
};
