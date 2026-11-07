export type ApiMealProduct = {
  /** Po termínu objednávek jídla; pult zamčený nemá. */
  locked: boolean;
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
  /**
   * Kolik postelí je odložených pro organizátory, null bez rezervace. Do `remaining` se
   * nepočítá — je to údaj pro obsluhu pultu, ne pro účastníka.
   */
  reservedForOrganizers: number | null;
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
  /** Size label for the picker; absent for a product's only variant, which has none. */
  name?: string | null;
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
  accommodationDay: number | null;
  breakfastIncluded: boolean;
  description: string;
  reservedForOrganizers: number | null;
  /** Read-only; absent for this year's catalog, as the API leaves out null fields. */
  archivedAt?: string;
  /** Read-only sum of the variants' capacities; null when any of them is unlimited. */
  capacity: number | null;
  tags: ApiProductTag[];
  variants: ApiProductVariant[];
  /** Read-only: ever bought, cancelled purchases included, so the server refuses to delete it. */
  sold: boolean;
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
  /** Absent for a product's only variant, which is shown as the product itself. */
  name?: string | null;
  code: string;
  price: string | null;
  /** Null = unlimited. */
  capacity: number | null;
  /** Read-only, computed on the server: capacity minus this year's purchases. */
  remaining?: number | null;
  /** Read-only: ever bought, so it cannot be removed. Absent for a variant not saved yet. */
  sold?: boolean;
  reservedForOrganizers: number | null;
  accommodationDay: number | null;
  /** Offer state of this night or size; the variant sharing the product's code takes the product's. */
  state?: number;
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

export type ApiCollection<T> = {
  member?: T[];
  totalItems?: number;
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
