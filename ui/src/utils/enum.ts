
type StringEnum = { [key: string | number]: number | string };

export const getEnumValues = (myEnum: StringEnum): number[] =>
  Object.values(myEnum)
    .filter((value): value is number => typeof value === "number")
  ;

export const getEnumNames = (myEnum: StringEnum): string[] =>
  getEnumValues(myEnum)
    .map(x => myEnum[x] as string)
  ;