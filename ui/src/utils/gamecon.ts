
export const DNY = [
  'pondělí',
  'úterý',
  'středa',
  'čtvrtek',
  'pátek',
  'sobota',
  'neděle',
]

export const doplňHáčkyDoDne = (den: string) => {
  if (den === 'pondeli') return 'pondělí';
  if (den === 'utery') return 'úterý';
  if (den === 'streda') return 'středa';
  if (den === 'ctvrtek') return 'čtvrtek';
  if (den === 'patek') return 'pátek';
  if (den === 'sobota') return 'sobota';
  if (den === 'nedele') return 'neděle';
  console.warn(`nepodařilo se oháčkovat den ${den}`);
  return den;
}
