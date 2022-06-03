export * from "./array";
export * from "./gamecon";
export * from "./tranformace";

export const tryParseNumber = (str: string | null): number | undefined =>{
  if (!str) return;
  const maybeNumber = +str;
  if (str && !Number.isNaN(maybeNumber)) return maybeNumber;
}
