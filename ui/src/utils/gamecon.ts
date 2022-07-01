/** Dny v týdnu bez diakritiky. Začíná pondeli. Pro háčky použít funkci doplňHáčkyDoDne */
export const DNY_NÁZVY = [
  'pondeli',
  'utery',
  'streda',
  'ctvrtek',
  'patek',
  'sobota',
  'nedele',
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

/** den v týdnu bez háčků */
export const formátujDenVTýdnu = (datum: Date) => {
  // vrací den v týdnu začínající nedělí
  //  proto potřebujeme den o jedno posunout zpět
  const denVTýdnu = (datum.getDay() + 6) % 7;
  const denText = DNY_NÁZVY[denVTýdnu];
  return denText;
};

/** datum ve tvaru "denVTýdnuNázev denVMesíci.měsíc" */
export const formátujDatum = (datum: Date) => {
  const denText = doplňHáčkyDoDne(formátujDenVTýdnu(datum));
  const den = datum.getDate();
  // Měsíce jsou oproti dnům idexované od 0. fakt se mě neptejte proč
  const měsíc = datum.getMonth() + 1;

  return `${denText} ${den}.${měsíc}`;
};

