
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
  const newArr: [T, T1][] = Array(len);
  for (let i = len; i--;) {
    newArr[i] = [arr[i], arr1[i]];
  }
  return newArr;
};
