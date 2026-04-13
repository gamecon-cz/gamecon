import { symfonyFetch } from "./fetch";
import {
  ApiCart,
  ApiHydraCollection,
  ApiMealProduct,
  ApiProduct,
  ApiProductTag,
  ApiProductWrite,
} from "./types";

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

// ==================== Admin: Products + Variants ====================

/**
 * Fetch all products (admin only). Returns an empty array on error so callers
 * can render a "no items" state without crashing.
 */
export const fetchProducts = async (): Promise<ApiProduct[]> => {
  const res = await symfonyFetch("products?itemsPerPage=200&page=1");
  if (!res.ok) throw new Error(`Failed to fetch products: ${res.status}`);
  const data = await res.json() as ApiHydraCollection<ApiProduct>;
  return data["hydra:member"] ?? data["member"] ?? [];
};

/**
 * Fetch all product tags (admin only). Used to populate the category dropdown
 * in the product editor.
 */
export const fetchProductTags = async (): Promise<ApiProductTag[]> => {
  const res = await symfonyFetch("product_tags");
  if (!res.ok) throw new Error(`Failed to fetch product tags: ${res.status}`);
  const data = await res.json() as ApiHydraCollection<ApiProductTag>;
  return data["hydra:member"] ?? data["member"] ?? [];
};

/**
 * Create a new product (POST /products) with its nested variants.
 */
export const createProduct = async (
  payload: ApiProductWrite,
): Promise<ApiProduct> => {
  const res = await symfonyFetch("products", {
    method: "POST",
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.detail ?? err["hydra:description"] ?? `Failed to create product: ${res.status}`);
  }
  return await res.json() as ApiProduct;
};

/**
 * Update an existing product (PATCH /products/{id}) with its nested variants.
 * Variants omitted from `payload.variants` are removed via Doctrine orphanRemoval.
 */
export const updateProduct = async (
  id: number,
  payload: ApiProductWrite,
): Promise<ApiProduct> => {
  const res = await symfonyFetch(`products/${id}`, {
    method: "PATCH",
    headers: {
      "Content-Type": "application/merge-patch+json",
    },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.detail ?? err["hydra:description"] ?? `Failed to update product: ${res.status}`);
  }
  return await res.json() as ApiProduct;
};

/**
 * Delete a product (DELETE /products/{id}).
 */
export const deleteProduct = async (id: number): Promise<void> => {
  const res = await symfonyFetch(`products/${id}`, {
    method: "DELETE",
  });
  if (!res.ok) throw new Error(`Failed to delete product: ${res.status}`);
};
