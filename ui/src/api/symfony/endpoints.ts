import { symfonyFetch } from "./fetch";
import { ApiCart, ApiHydraCollection, ApiMealProduct } from "./types";

/**
 * Fetch meal products (flat DTOs for the meal matrix)
 */
export const fetchMeals = async (): Promise<ApiMealProduct[]> => {
  const res = await symfonyFetch("cart/meals");
  if (!res.ok) throw new Error(`Failed to fetch meals: ${res.status}`);
  const data = await res.json() as ApiHydraCollection<ApiMealProduct>;
  return data["hydra:member"] ?? data["member"] ?? [];
};

/**
 * Fetch current cart
 */
export const fetchCart = async (): Promise<ApiCart> => {
  const res = await symfonyFetch("cart");
  if (!res.ok) throw new Error(`Failed to fetch cart: ${res.status}`);
  return await res.json() as ApiCart;
};

/**
 * Add variant to cart
 */
export const addToCart = async (variantId: number): Promise<ApiCart> => {
  const res = await symfonyFetch("cart/items", {
    method: "POST",
    body: JSON.stringify({ variantId }),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.detail ?? `Failed to add to cart: ${res.status}`);
  }
  return await res.json() as ApiCart;
};

/**
 * Remove item from cart
 */
/**
 * Remove item from cart. Returns the removed item ID (server returns 204 No Content).
 */
export const removeFromCart = async (itemId: number): Promise<void> => {
  const res = await symfonyFetch(`cart/items/${itemId}`, {
    method: "DELETE",
  });
  if (!res.ok) throw new Error(`Failed to remove from cart: ${res.status}`);
};
