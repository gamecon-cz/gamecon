
export const getEnumValues = (myEnum: any): number[] =>
  Object.values(myEnum)
    .filter(value => typeof value === 'number') as any
  ;

export const getEnumNames = (myEnum: any): string[] =>
  getEnumValues(myEnum)
    .map(x => myEnum[x])
  ;