
export const asArray = <T>(arrLike: T | T[]): T[] => {
  return Array.isArray(arrLike)
    ? arrLike
    : [arrLike];
};

export const containsSame = <T>(arr1: T[], arr2: T[]): boolean => {
  for (let i = arr1.length; i--;) {
    const el1 = arr1[i];
    for (let j = arr2.length; j--;)
      if (el1 === arr2[j])
        return true;
  }
  return false;
};


type NewElOptions = { stringMethod?: 'innerText' | 'innerHTML' };

export const newEl = <T extends keyof HTMLElementTagNameMap>(type: T, attributes: [string, string][] | Record<string, string> = [], children: string | Node | Node[] = [], options: NewElOptions = {}): HTMLElementTagNameMap[T] => {
  const element = document.createElement(type);
  (
    (Array.isArray(attributes)) ? attributes : Object.entries(attributes)
  ).forEach(([key, value]) => element.setAttribute(key, value));

  if (typeof children == "string") {
    if (!options?.stringMethod || options.stringMethod === "innerText")
      element.innerText = children;
    else if (options.stringMethod === "innerHTML")
      element.innerHTML = children;
    else
      throw new Error(`invalid stringMethod ${options?.stringMethod}`);
  } else {
    asArray(children).forEach(el => element.appendChild(el));
  }
  return element;
};

export const range: {
  (max: number): number[]
  (min: number, max: number, step?: number): number[]
} = (n: number, n1?: number, step: number = 1): number[] =>
    Array.from(
      Array(
        (n1 === undefined)
          ? n
          : Math.max(Math.ceil((n1 - n) / step), 0)
      ).keys())
      .map((n1 === undefined) ? (x => x) : (x => (x * step + n)))
  ;

/**
 * Returns unique values only 
 */
export const distinct: {
  (input: number[]): number[];
  (input: string[]): string[];
} = (arr: number[] | string[]) =>
    Array.from(new Set(arr as any)) as any;


export const zip = <T, T1>(arr: T[], arr1: T1[]): [T, T1][] => {
  const len = Math.max(arr.length, arr1.length);
  /** @type {[T, T1][]} */
  const newArr: [T, T1][] = Array(len);
  for (let i = len; i--;) {
    newArr[i] = [arr[i], arr1[i]];
  }
  return newArr;
};
