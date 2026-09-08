import { h } from "preact";
import { useCallback, useEffect, useRef, useState } from "preact/hooks";
import { addToCart, fetchCart, fetchMerch, removeFromCart } from "../../api/symfony/endpoints";
import { ApiCart, ApiCartItem, ApiMerchProduct } from "../../api/symfony/types";

/** Format price string for Czech locale: "120.00" → "120 Kč", "80.50" → "80,50 Kč" */
function formatCena(price: string): string {
  const castka = parseFloat(price);
  if (isNaN(castka)) return `${price}\u2009Kč`;
  if (Number.isInteger(castka)) return `${castka}\u2009Kč`;
  return `${castka.toFixed(2).replace(".", ",")}\u2009Kč`;
}

function cartItemsForVariant(cart: ApiCart | null, variantId: number): ApiCartItem[] {
  if (!cart) return [];
  return cart.items.filter((item) => item.variantId === variantId);
}

type MerchŘádekProps = {
  product: ApiMerchProduct;
  cart: ApiCart | null;
  busy: boolean;
  onAdd: (product: ApiMerchProduct) => Promise<void>;
  onRemove: (product: ApiMerchProduct, item: ApiCartItem) => Promise<void>;
};

function MerchŘádek({ product, cart, busy, onAdd, onRemove }: MerchŘádekProps) {
  // purchasedQuantity counts every OrderItem of the year, cart included, so it is the
  // total the customer owns. Only what is still in the cart can be taken back out.
  const vKosiku = cartItemsForVariant(cart, product.variantId);
  const quantity = product.purchasedQuantity;
  const zlevneno = product.discountedPrice !== product.price;
  const naMaximu = product.maxQuantity !== null && quantity >= product.maxQuantity;
  const vyprodano = product.maxQuantity !== null && product.maxQuantity <= 0;

  return (
    <div class="merch-mrizka--predmet">
      <div class="merch-mrizka--popis">
        {product.name}
        {product.description && (
          <div class="merch-mrizka--popisek">{product.description}</div>
        )}
        <div class="merch-mrizka--cena">
          {zlevneno && (
            <span class="merch-mrizka--cena-puvodni">{formatCena(product.price)}</span>
          )}
          {formatCena(product.discountedPrice)}
        </div>
      </div>

      {product.available && !vyprodano ? (
        <div class="merch-mrizka--pocet">
          <button
            type="button"
            class="merch-mrizka--minus"
            disabled={busy || vKosiku.length === 0}
            aria-label={`Odebrat ${product.name}`}
            onClick={() => {
              const posledni = vKosiku[vKosiku.length - 1];
              if (posledni) void onRemove(product, posledni);
            }}
          >
            −
          </button>
          <span class="merch-mrizka--kusu">{quantity}</span>
          <button
            type="button"
            class="merch-mrizka--plus"
            disabled={busy || naMaximu}
            aria-label={`Přidat ${product.name}`}
            onClick={() => void onAdd(product)}
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

export function MerchMřížka() {
  const [products, setProducts] = useState<ApiMerchProduct[]>([]);
  const [cart, setCart] = useState<ApiCart | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<Set<number>>(new Set());
  const probihajici = useRef<Set<number>>(new Set());

  useEffect(() => {
    Promise.all([
      fetchMerch(),
      fetchCart(),
    ]).then(([merch, currentCart]) => {
      setProducts(merch);
      setCart(currentCart);
      setLoading(false);
    }).catch((chyba: unknown) => {
      setError(chyba instanceof Error ? chyba.message : "Nepodařilo se načíst merch");
      setLoading(false);
    });
  }, []);

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
      fetchMerch().catch(() => null),
      fetchCart().catch(() => null),
    ]);
    if (cerstvyMerch) setProducts(cerstvyMerch);
    if (cerstvyKosik) setCart(cerstvyKosik);
  }, []);

  const pridat = useCallback(async (product: ApiMerchProduct) => {
    if (probihajici.current.has(product.variantId)) return;
    markBusy(product.variantId, true);
    setError(null);
    try {
      await addToCart(product.variantId);
    } catch (chyba: unknown) {
      // CapacityManager rejects an oversell, so this is also the sold-out signal.
      setError(chyba instanceof Error ? chyba.message : `Nepodařilo se přidat ${product.name}`);
    } finally {
      await obnovit();
      markBusy(product.variantId, false);
    }
  }, [markBusy, obnovit]);

  const odebrat = useCallback(async (product: ApiMerchProduct, item: ApiCartItem) => {
    if (probihajici.current.has(product.variantId)) return;
    markBusy(product.variantId, true);
    setError(null);
    try {
      await removeFromCart(item.id);
    } catch (chyba: unknown) {
      setError(chyba instanceof Error ? chyba.message : `Nepodařilo se odebrat ${product.name}`);
    } finally {
      await obnovit();
      markBusy(product.variantId, false);
    }
  }, [markBusy, obnovit]);

  if (loading) return <div class="merch-mrizka--loading">Načítám předměty…</div>;
  if (products.length === 0) {
    return <div class="merch-mrizka--empty">Žádné předměty k dispozici.</div>;
  }

  const hlavni = products.filter((product) => !product.secondary);
  const vedlejsi = products.filter((product) => product.secondary);

  const řádek = (product: ApiMerchProduct) => (
    <MerchŘádek
      key={product.variantId}
      product={product}
      cart={cart}
      busy={busy.has(product.variantId)}
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
