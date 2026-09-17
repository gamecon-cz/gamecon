export type ApiMealProduct = {
  name: string;
  day: number;
  price: string;
  /** Cena podle pořadí kusu; vždy aspoň jeden stupeň. */
  priceSteps: ApiPriceStep[];
  variantId: number;
  remainingQuantity: number | null;
};

export type ApiAccommodationCell = {
  variantId: number;
  selected: boolean;
  /** Null = unlimited; dorm beds are effectively uncapped. */
  remaining: number | null;
  soldOut: boolean;
  /** Cannot be ticked. An already-booked night is never locked, so it can be dropped. */
  locked: boolean;
};

export type ApiAccommodationType = {
  productId: number;
  name: string;
  description: string;
  price: string;
  discountedPrice: string;
  /** Keyed by day index; a day this type is not offered on has no entry. */
  nights: Record<number, ApiAccommodationCell>;
};

export type ApiAccommodationDay = {
  /** 0 = Wednesday … 4 = Sunday. */
  day: number;
  name: string;
};

export type ApiAccommodation = {
  days: ApiAccommodationDay[];
  types: ApiAccommodationType[];
  selectedVariantIds: number[];
  /** Fewer nights than this is refused, unless the customer may book a single night. */
  minimumNights: number;
  saleClosed: boolean;
  roommate: string | null;
  declined: boolean;
  /** Names of breakfasts a hotel night cancelled, which no night covers any more. */
  restorableBreakfasts: string[];
};

export type ApiAccommodationWrite = {
  variantIds: number[];
  roommate?: string | null;
  declined?: boolean;
  restoreBreakfasts?: boolean;
};

export type ApiMerchVariant = {
  id: number;
  /** Size label for the picker; hidden when the product has a single variant. */
  name: string;
  purchasedQuantity: number;
  /** Null when the variant has unlimited stock. */
  maxQuantity: number | null;
};

/**
 * Cena podle pořadí kusu. Nárok se vyčerpává — „první zdarma, další za plnou" je proto
 * posloupnost, ne jedno číslo. Přichází celá, aby po přidání do košíku šlo ukázat cenu
 * dalšího kusu bez dalšího dotazu na server.
 */
export type ApiPriceStep = {
  /** Od kolikátého kusu (1 = první) tahle cena platí. */
  fromQuantity: number;
  price: string;
  discountAmount: string;
  /** null u stupně bez slevy. */
  ruleCode: string | null;
  ruleName: string | null;
  /** Lidsky, co zvýhodnění znamená: „první dva zdarma". null u stupně bez slevy. */
  label: string | null;
};

export type ApiMerchProduct = {
  name: string;
  /** Stable product identity; used as the React key. */
  code: string;
  description: string;
  /** Always at least one. Stock and cap are per variant, not per product. */
  variants: ApiMerchVariant[];
  price: string;
  discountedPrice: string;
  /** Cena podle pořadí kusu; vždy aspoň jeden stupeň. */
  priceSteps: ApiPriceStep[];
  /** Summed across variants. */
  purchasedQuantity: number;
  /** Belongs in the collapsed "Další merch" section rather than the main grid. */
  secondary: boolean;
  available: boolean;
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

export type ApiEntryFee = {
  amount: string;
  lastYearAveragePercent: number;
  lastYear: number;
  gammaCorrection: number;
  minimum: number;
  maximum: number;
  maximumAmount: number;
};
