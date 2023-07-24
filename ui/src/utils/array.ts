
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

export const range: (min: number, max?: number, step?: number) => number[] = (n: number, n1?: number, step = 1): number[] => {
  const length = (n1 === undefined)
    ? n
    : Math.max(Math.ceil((n1 - n) / step), 0);

  return (length >= 0) ? 
    Array.from(Array(length).keys())
      .map((n1 === undefined) ? (x => x) : (x => (x * step + n)))
    : []
  ;
};


/**
 * Returns unique values only 
 */
export const distinct: {
  (input: number[]): number[];
  (input: string[]): string[];
} = (arr: number[] | string[]) =>
  Array.from(new Set(arr as []));


export const zip = <T, T1>(arr: T[], arr1: T1[]): [T, T1][] => {
  const len = Math.max(arr.length, arr1.length);
  const newArr = new Array(len) as [T, T1][];
  for (let i = len; i--;) {
    newArr[i] = [arr[i], arr1[i]];
  }
  return newArr;
};
