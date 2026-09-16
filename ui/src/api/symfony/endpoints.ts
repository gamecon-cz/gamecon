import { symfonyFetch } from "./fetch";
import {
  ApiAccommodation,
  ApiAccommodationWrite,
  ApiCart,
  ApiEntryFee,
  ApiHydraCollection,
  ApiMealProduct,
  ApiMerchProduct,
  ApiProduct,
  ApiProductTag,
  ApiProductWrite,
} from "./types";

/**
 * Fetch meal products (flat DTOs for the meal matrix)
 */
/**
 * Which meals a participant holds, for the admin desk. The catalogue is the same for everyone
 * and comes from fetchMeals(); only the selection differs, and the desk has no cart to read it
 * from the way the participant's own matrix does.
 */
export const fetchCustomerMeals = async (customerId: number): Promise<number[]> => {
  const res = await symfonyFetch(`admin/customer-meals?customerId=${customerId}`);
  if (!res.ok) throw new Error(`Failed to fetch customer meals: ${res.status}`);
  const data = await res.json() as { variantIds?: number[] };
  return data.variantIds ?? [];
};

/**
 * Save a participant's whole meal selection at once, as the desk submits it.
 */
export const saveCustomerMeals = async (customerId: number, variantIds: number[]): Promise<void> => {
  const res = await symfonyFetch("admin/customer-meals", {
    method: "POST",
    headers: { "Content-Type": "application/ld+json" },
    body: JSON.stringify({ customerId, variantIds }),
  });
  if (!res.ok) {
    const chyba = await res.json().catch(() => null) as { "hydra:description"?: string; detail?: string } | null;
    throw new Error(chyba?.["hydra:description"] ?? chyba?.detail ?? `Uložení jídla selhalo: ${res.status}`);
  }
};

export const fetchMeals = async (): Promise<ApiMealProduct[]> => {
  const res = await symfonyFetch("cart/meals");
  if (!res.ok) throw new Error(`Failed to fetch meals: ${res.status}`);
  const data = await res.json() as ApiHydraCollection<ApiMealProduct>;
  return data["hydra:member"] ?? data["member"] ?? [];
};

/**
 * Fetch the accommodation section. One payload rather than a list: the nights of a
 * booking must be consecutive, so they are chosen as a set.
 */
export const fetchAccommodation = async (customerId?: number): Promise<ApiAccommodation> => {
  const res = await symfonyFetch(
    customerId === undefined
      ? "cart/accommodation"
      : `admin/customer-accommodation?customerId=${customerId}`,
  );
  if (!res.ok) throw new Error(`Failed to fetch accommodation: ${res.status}`);
  return await res.json() as ApiAccommodation;
};

/**
 * Save the accommodation booking. Sends the whole set of nights the customer should end up
 * with, and returns the section as GET would, so the caller can render the result directly.
 */
export const saveAccommodation = async (
  data: ApiAccommodationWrite,
  customerId?: number,
): Promise<ApiAccommodation> => {
  const res = await symfonyFetch(
    customerId === undefined ? "cart/accommodation" : "admin/customer-accommodation",
    {
      method: "POST",
      headers: { "Content-Type": "application/ld+json" },
      // The desk names the customer; the participant's own endpoint takes it from the session.
      body: JSON.stringify(customerId === undefined ? data : { ...data, customerId }),
    },
  );
  if (!res.ok) {
    const chyba = await res.json().catch(() => null) as { "hydra:description"?: string; detail?: string } | null;
    throw new Error(chyba?.["hydra:description"] ?? chyba?.detail ?? `Uložení ubytování selhalo: ${res.status}`);
  }
  return await res.json() as ApiAccommodation;
};

/**
 * Fetch merch products (flat DTOs for the merch grid)
 */
export const fetchMerch = async (): Promise<ApiMerchProduct[]> => {
  const res = await symfonyFetch("cart/merch");
  if (!res.ok) throw new Error(`Failed to fetch merch: ${res.status}`);
  const data = await res.json() as ApiHydraCollection<ApiMerchProduct>;
  return data["hydra:member"] ?? data["member"] ?? [];
};

/** Shirts and hoodies: same payload as merch, own endpoint because of their own deadlines. */
export const fetchShirts = async (): Promise<ApiMerchProduct[]> => {
  const res = await symfonyFetch("cart/shirts");
  if (!res.ok) throw new Error(`Failed to fetch shirts: ${res.status}`);
  const data = await res.json() as ApiHydraCollection<ApiMerchProduct>;
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
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.detail ?? `Failed to remove from cart: ${res.status}`);
  }
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

/**
 * Fetch the voluntary entry fee and the parameters its slider needs
 */
export const fetchEntryFee = async (): Promise<ApiEntryFee> => {
  const res = await symfonyFetch("cart/entry-fee");
  if (!res.ok) throw new Error(`Failed to fetch entry fee: ${res.status}`);
  return await res.json() as ApiEntryFee;
};

/**
 * Set the voluntary entry fee for the current year
 */
export const setEntryFee = async (amount: number): Promise<ApiEntryFee> => {
  const res = await symfonyFetch("cart/entry-fee", {
    method: "POST",
    body: JSON.stringify({ amount }),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.detail ?? `Failed to set entry fee: ${res.status}`);
  }
  return await res.json() as ApiEntryFee;
};
