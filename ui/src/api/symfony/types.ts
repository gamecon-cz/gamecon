export type ApiMealProduct = {
  name: string;
  day: number;
  price: string;
  variantId: number;
  remainingQuantity: number | null;
};

export type ApiProduct = {
  "@id": string;
  id: number;
  name: string;
  code: string;
  currentPrice: string;
  state: number;
  availableUntil: string | null;
  producedQuantity: number | null;
  accommodationDay: number | null;
  breakfastIncluded: boolean;
  description: string;
  reservedForOrganizers: number | null;
  tags: ApiProductTag[];
  variants: ApiProductVariant[];
};

export type ApiProductTag = {
  "@id"?: string;
  code: string;
  name: string;
  description?: string | null;
};

export type ApiProductVariant = {
  "@id"?: string;
  id?: number;
  name: string;
  code: string;
  price: string | null;
  remainingQuantity: number | null;
  reservedForOrganizers: number | null;
  accommodationDay: number | null;
  position: number;
};

/**
 * Write-shape payload for POST /products and PATCH /products/{id}.
 * `id` is omitted for new rows. Tags are sent as IRI strings. Variants
 * omitted from the array are removed via orphanRemoval.
 */
export type ApiProductWrite = {
  name: string;
  code: string;
  currentPrice: string;
  state: number;
  availableUntil: string | null;
  producedQuantity: number | null;
  accommodationDay: number | null;
  breakfastIncluded: boolean;
  description: string;
  reservedForOrganizers: number | null;
  tags: string[]; // ProductTag IRIs
  variants: ApiProductVariant[];
};

export type ApiCartItem = {
  id: number;
  productName: string;
  productCode: string | null;
  variantId: number | null;
  variantName: string | null;
  variantCode: string | null;
  purchasePrice: string;
  originalPrice: string | null;
  discountAmount: string | null;
  discountReason: string | null;
  bundleId: number | null;
};

export type ApiCart = {
  id: number | null;
  status: string;
  totalPrice: string;
  itemCount: number;
  items: ApiCartItem[];
};

export type ApiHydraCollection<T> = {
  "hydra:member"?: T[];
  "member"?: T[];
  "hydra:totalItems"?: number;
  "totalItems"?: number;
};
