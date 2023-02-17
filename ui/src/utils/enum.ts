
type StringEnum = { [key: string | number]: number | string };

export const getEnumValues = (myEnum: StringEnum) =>
  Object.values(myEnum)
    .filter(value => typeof value === "number") as number[]
  ;

export const getEnumNames = (myEnum: StringEnum): string[] =>
  getEnumValues(myEnum)
    .map(x => myEnum[x] as string)
  ;