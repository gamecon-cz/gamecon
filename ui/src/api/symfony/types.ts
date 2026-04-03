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
  accommodationDay: number | null;
  tags: ApiProductTag[];
  variants: ApiProductVariant[];
};

export type ApiProductTag = {
  code: string;
  name: string;
};

export type ApiProductVariant = {
  id: number;
  name: string;
  code: string;
  price: string | null;
  remainingQuantity: number | null;
  accommodationDay: number | null;
  position: number;
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
