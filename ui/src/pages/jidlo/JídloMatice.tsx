import { h } from "preact";
import { useCallback, useEffect, useState } from "preact/hooks";
import { addToCart, fetchCart, fetchMeals, removeFromCart } from "../../api/symfony/endpoints";
import { ApiCart, ApiCartItem, ApiMealProduct } from "../../api/symfony/types";

/** Format price string for Czech locale: "120.00" → "120 Kč", "80.50" → "80,50 Kč" */
function formatCena(price: string): string {
  const num = parseFloat(price);
  if (isNaN(num)) return price + "\u2009Kč";
  if (Number.isInteger(num)) return num + "\u2009Kč";
  return num.toFixed(2).replace(".", ",") + "\u2009Kč";
}

/** Day index → Czech day name */
const DNY: Record<number, string> = {
  0: "Středa",
  1: "Čtvrtek",
  2: "Pátek",
  3: "Sobota",
  4: "Neděle",
};

/** Meal type order for rows */
const DRUHY_JÍDEL = ["snídaně", "oběd", "večeře"] as const;

type MealCell = {
  meal: ApiMealProduct;
  mealType: string;
  soldOut: boolean;
};

/** Group meal products into a matrix: mealType → day → cell */
function buildMatrix(meals: ApiMealProduct[]): Map<string, Map<number, MealCell>> {
  const matrix = new Map<string, Map<number, MealCell>>();

  for (const druh of DRUHY_JÍDEL) {
    matrix.set(druh, new Map());
  }

  for (const meal of meals) {
    const nameLower = meal.name.toLowerCase();
    let mealType: string | null = null;
    for (const d of DRUHY_JÍDEL) {
      if (nameLower.startsWith(d) || nameLower.includes(d)) {
        mealType = d;
        break;
      }
    }
    if (!mealType) continue;

    const dayMap = matrix.get(mealType);
    if (!dayMap) continue;

    dayMap.set(meal.day, {
      meal,
      mealType,
      soldOut: meal.remainingQuantity !== null && meal.remainingQuantity <= 0,
    });
  }

  return matrix;
}

/** Find cart item matching a variant */
function findCartItem(cart: ApiCart | null, variantId: number): ApiCartItem | undefined {
  if (!cart) return undefined;
  return cart.items.find((item) => item.variantId === variantId);
}

export function JídloMatice() {
  const [meals, setMeals] = useState<ApiMealProduct[]>([]);
  const [cart, setCart] = useState<ApiCart | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<Set<number>>(new Set());

  useEffect(() => {
    Promise.all([
      fetchMeals(),
      fetchCart(),
    ]).then(([m, c]) => {
      setMeals(m);
      setCart(c);
      setLoading(false);
    }).catch((e) => {
      setError(e.message);
      setLoading(false);
    });
  }, []);

  const matrix = buildMatrix(meals);

  const allDays = new Set<number>();
  for (const [, dayMap] of matrix) {
    for (const day of dayMap.keys()) {
      allDays.add(day);
    }
  }
  const days = [...allDays].sort((a, b) => a - b);

  const toggleMeal = useCallback(async (variantId: number, cartItem: ApiCartItem | undefined) => {
    if (busy.has(variantId)) return;

    setBusy((busyVariants) => new Set(busyVariants).add(variantId));
    try {
      if (cartItem) {
        await removeFromCart(cartItem.id);
        // Optimistic update — remove item from local cart state
        setCart((currentCart) => {
          if (!currentCart) return currentCart;
          const remainingItems = currentCart.items.filter((item) => item.id !== cartItem.id);
          const totalPrice = remainingItems.reduce((sum, item) => sum + parseFloat(item.purchasePrice), 0);
          return {
            ...currentCart,
            items: remainingItems,
            itemCount: remainingItems.length,
            totalPrice: totalPrice.toFixed(2),
          };
        });
      } else {
        const newCart = await addToCart(variantId);
        setCart(newCart);
      }
    } catch (error: unknown) {
      setError(error instanceof Error ? error.message : "Chyba při změně jídla");
    } finally {
      setBusy((busyVariants) => {
        const updated = new Set(busyVariants);
        updated.delete(variantId);
        return updated;
      });
    }
  }, [busy]);

  if (loading) return <div class="jidlo-matice--loading">Načítám jídla…</div>;
  if (error) return <div class="jidlo-matice--error">Chyba: {error}</div>;
  if (days.length === 0) return <div class="jidlo-matice--empty">Žádná jídla k dispozici.</div>;

  return (
    <div class="jidlo-matice">
      <table class="jidlo-matice--table">
        <thead>
          <tr>
            <th></th>
            {days.map((day) => (
              <th key={day}>{DNY[day] ?? `Den ${day}`}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {DRUHY_JÍDEL.map((druh) => {
            const dayMap = matrix.get(druh);
            if (!dayMap || dayMap.size === 0) return null;

            return (
              <tr key={druh}>
                <td class="jidlo-matice--druh">{druh.charAt(0).toUpperCase() + druh.slice(1)}</td>
                {days.map((day) => {
                  const cell = dayMap.get(day);
                  if (!cell) return <td key={day} class="jidlo-matice--empty-cell"></td>;

                  const cartItem = findCartItem(cart, cell.meal.variantId);
                  const checked = !!cartItem;
                  const isBusy = busy.has(cell.meal.variantId);

                  return (
                    <td
                      key={day}
                      class={`jidlo-matice--cell ${checked ? "jidlo-matice--selected" : ""} ${cell.soldOut && !checked ? "jidlo-matice--sold-out" : ""} ${isBusy ? "jidlo-matice--busy" : ""}`}
                    >
                      <label>
                        <input
                          type="checkbox"
                          checked={checked}
                          disabled={isBusy || (cell.soldOut && !checked)}
                          onChange={() => toggleMeal(cell.meal.variantId, cartItem)}
                        />
                        <span class="jidlo-matice--price">{formatCena(cell.meal.price)}</span>
                      </label>
                    </td>
                  );
                })}
              </tr>
            );
          })}
        </tbody>
      </table>
      {cart && cart.itemCount > 0 && (
        <div class="jidlo-matice--total">
          Celkem: {formatCena(cart.totalPrice)} ({cart.itemCount} položek)
        </div>
      )}
    </div>
  );
}
