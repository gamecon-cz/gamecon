import { h } from "preact";
import { useCallback, useEffect, useRef, useState } from "preact/hooks";
import { addToCart, fetchCart, fetchMerch, fetchShirts, removeFromCart } from "../../api/symfony/endpoints";
import { ApiCart, ApiCartItem, ApiMerchProduct, ApiMerchVariant } from "../../api/symfony/types";
import { cenaDalsihoKusu, formatCena, popisSchodu } from "../../api/symfony/cenovySchod";

function cartItemsForVariant(cart: ApiCart | null, variantId: number): ApiCartItem[] {
  if (!cart) return [];
  return cart.items.filter((item) => item.variantId === variantId);
}

type MerchŘádekProps = {
  product: ApiMerchProduct;
  cart: ApiCart | null;
  busy: (variantId: number) => boolean;
  onAdd: (product: ApiMerchProduct, variant: ApiMerchVariant) => Promise<void>;
  onRemove: (product: ApiMerchProduct, variant: ApiMerchVariant, item: ApiCartItem) => Promise<void>;
};

/** První velikost, která je ještě na skladě — jinak ta první. */
function vychoziVarianta(variants: ApiMerchVariant[]): ApiMerchVariant {
  return variants.find((v) => v.maxQuantity === null || v.maxQuantity > 0) ?? variants[0];
}

function MerchŘádek({ product, cart, busy, onAdd, onRemove }: MerchŘádekProps) {
  // Velikost si drží řádek, ne rodič: přepnutí jedné velikosti nesmí překreslit celou mřížku.
  // Vyprodaná velikost by se jako výchozí tvářila, že se produkt nedá koupit, i když jiná
  // velikost na skladě je.
  const [vybranaId, setVybranaId] = useState<number>(() => vychoziVarianta(product.variants).id);
  const vybrana = product.variants.find((v) => v.id === vybranaId) ?? vychoziVarianta(product.variants);

  // purchasedQuantity počítá všechny letošní položky včetně košíku, takže je to celkový
  // počet. Odebrat jde jen to, co je pořád v košíku.
  const vKosiku = cartItemsForVariant(cart, vybrana.id);
  const quantity = vybrana.purchasedQuantity;
  const zlevneno = product.discountedPrice !== product.price;
  // Nárok s omezeným počtem se do jedné ceny nevejde, tak se ukáže celý žebřík. Server
  // ho posílá celý, takže po přidání do košíku stačí přečíst další stupeň — bez dotazu.
  const schody = product.priceSteps ?? [];
  // Žebřík ze serveru už počítá s tím, co zákazník má — první stupeň je cena kusu, který
  // si teprve koupí. Odečítat purchasedQuantity znovu by nárok spotřebovalo dvakrát.
  const popis = popisSchodu(schody);
  const cenaDalsiho = cenaDalsihoKusu(schody, 0);
  const naMaximu = vybrana.maxQuantity !== null && quantity >= vybrana.maxQuantity;
  const vyprodano = vybrana.maxQuantity !== null && vybrana.maxQuantity <= 0;
  const maVyber = product.variants.length > 1;
  const zaneprazdneno = busy(vybrana.id);

  return (
    <div class="merch-mrizka--predmet">
      <div class="merch-mrizka--popis">
        {product.name}
        {product.description && (
          <div class="merch-mrizka--popisek">{product.description}</div>
        )}
        <div class="merch-mrizka--cena">
          {popis ?? (
            <>
              {zlevneno && (
                <span class="merch-mrizka--cena-puvodni">{formatCena(product.price)}</span>
              )}
              {formatCena(cenaDalsiho ?? product.discountedPrice)}
            </>
          )}
        </div>
        {/* Počet u tlačítek je za vybranou velikost, takže kdo má víc velikostí, by jinak
            viděl „1" u produktu, kterého má dva. */}
        {maVyber && product.purchasedQuantity > quantity && (
          <div class="merch-mrizka--celkem">
            Celkem objednáno: {product.purchasedQuantity}
          </div>
        )}
      </div>

      {/* Select schválně bez `disabled`: přepnout na jinou velikost je bezpečné i během
          požadavku, a zrovna tehdy si to člověk rozmyslí nejčastěji. */}
      {maVyber && (
        <select
          class="merch-mrizka--velikost"
          value={String(vybrana.id)}
          aria-label={`Velikost – ${product.name}`}
          onChange={(event) => {
            setVybranaId(Number((event.currentTarget as HTMLSelectElement).value));
          }}
        >
          {product.variants.map((varianta) => (
            <option key={varianta.id} value={String(varianta.id)}>
              {varianta.name}
              {varianta.maxQuantity !== null && varianta.maxQuantity <= 0 ? " (vyprodáno)" : ""}
            </option>
          ))}
        </select>
      )}

      {product.available && !vyprodano ? (
        <div class="merch-mrizka--pocet">
          <button
            type="button"
            class="merch-mrizka--minus"
            disabled={zaneprazdneno || vKosiku.length === 0}
            aria-label={`Odebrat ${product.name}`}
            onClick={() => {
              const posledni = vKosiku[vKosiku.length - 1];
              if (posledni) void onRemove(product, vybrana, posledni);
            }}
          >
            −
          </button>
          <span class="merch-mrizka--kusu">{quantity}</span>
          <button
            type="button"
            class="merch-mrizka--plus"
            disabled={zaneprazdneno || naMaximu}
            aria-label={`Přidat ${product.name}`}
            onClick={() => void onAdd(product, vybrana)}
          >
            +
          </button>
        </div>
      ) : (
        <div class="merch-mrizka--pocet merch-mrizka--pocet-zamceny">
          <span class="merch-mrizka--kusu">{quantity}</span>
          <span class="merch-mrizka--nedostupne">
            {vyprodano ? "vyprodáno" : "není v prodeji"}
          </span>
        </div>
      )}
    </div>
  );
}

type MřížkaProps = {
  /** Odkud se berou položky — merch a svršky mají každý svůj endpoint. */
  nacti: () => Promise<ApiMerchProduct[]>;
  prazdno: string;
};

function Mřížka({ nacti, prazdno }: MřížkaProps) {
  const [products, setProducts] = useState<ApiMerchProduct[]>([]);
  const [cart, setCart] = useState<ApiCart | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<Set<number>>(new Set());
  const probihajici = useRef<Set<number>>(new Set());

  useEffect(() => {
    Promise.all([
      nacti(),
      fetchCart(),
    ]).then(([merch, currentCart]) => {
      setProducts(merch);
      setCart(currentCart);
      setLoading(false);
    }).catch((chyba: unknown) => {
      setError(chyba instanceof Error ? chyba.message : "Nepodařilo se načíst nabídku");
      setLoading(false);
    });
  }, [nacti]);

  const markBusy = useCallback((variantId: number, running: boolean) => {
    if (running) {
      probihajici.current.add(variantId);
    } else {
      probihajici.current.delete(variantId);
    }
    setBusy((busyVariants) => {
      const updated = new Set(busyVariants);
      if (running) {
        updated.add(variantId);
      } else {
        updated.delete(variantId);
      }
      return updated;
    });
  }, []);

  /**
   * Both counts and caps come from the server: purchasedQuantity counts every OrderItem
   * of the year, cart items included, so it goes stale the moment anything changes.
   */
  const obnovit = useCallback(async () => {
    const [cerstvyMerch, cerstvyKosik] = await Promise.all([
      nacti().catch(() => null),
      fetchCart().catch(() => null),
    ]);
    if (cerstvyMerch) setProducts(cerstvyMerch);
    if (cerstvyKosik) setCart(cerstvyKosik);
  }, [nacti]);

  const pridat = useCallback(async (product: ApiMerchProduct, variant: ApiMerchVariant) => {
    if (probihajici.current.has(variant.id)) return;
    markBusy(variant.id, true);
    setError(null);
    try {
      await addToCart(variant.id);
    } catch (chyba: unknown) {
      // CapacityManager rejects an oversell, so this is also the sold-out signal.
      setError(chyba instanceof Error ? chyba.message : `Nepodařilo se přidat ${product.name}`);
    } finally {
      await obnovit();
      markBusy(variant.id, false);
    }
  }, [markBusy, obnovit]);

  const odebrat = useCallback(async (product: ApiMerchProduct, variant: ApiMerchVariant, item: ApiCartItem) => {
    if (probihajici.current.has(variant.id)) return;
    markBusy(variant.id, true);
    setError(null);
    try {
      await removeFromCart(item.id);
    } catch (chyba: unknown) {
      setError(chyba instanceof Error ? chyba.message : `Nepodařilo se odebrat ${product.name}`);
    } finally {
      await obnovit();
      markBusy(variant.id, false);
    }
  }, [markBusy, obnovit]);

  if (loading) return <div class="merch-mrizka--loading">Načítám předměty…</div>;
  if (products.length === 0) {
    return <div class="merch-mrizka--empty">{prazdno}</div>;
  }

  const hlavni = products.filter((product) => !product.secondary);
  const vedlejsi = products.filter((product) => product.secondary);

  const řádek = (product: ApiMerchProduct) => (
    <MerchŘádek
      key={product.code}
      product={product}
      cart={cart}
      busy={(variantId: number) => busy.has(variantId)}
      onAdd={pridat}
      onRemove={odebrat}
    />
  );

  return (
    <div class="merch-mrizka">
      {error && <div class="merch-mrizka--error">{error}</div>}

      {hlavni.length > 0 && (
        <div class="merch-mrizka--predmety">{hlavni.map(řádek)}</div>
      )}

      {vedlejsi.length > 0 && (
        <details class="merch-mrizka--dalsi">
          <summary>Další merch</summary>
          <div class="merch-mrizka--predmety">{vedlejsi.map(řádek)}</div>
        </details>
      )}
    </div>
  );
}

export function MerchMřížka() {
  return <Mřížka nacti={fetchMerch} prazdno="Žádné předměty k dispozici." />;
}

/** Trička a mikiny. Stejná mřížka, jiný endpoint — svršky mají vlastní termíny prodeje. */
export function SvrškyMřížka() {
  return <Mřížka nacti={fetchShirts} prazdno="Žádná trička k dispozici." />;
}
