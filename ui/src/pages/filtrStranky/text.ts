/** malá písmena bez diakritiky, ať „pribeh“ najde „Příběh“ */
export const normalizujText = (text: string): string =>
  text.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

/** slova hledaného textu, normalizovaná */
export const slovaHledání = (hledaný: string): string[] =>
  normalizujText(hledaný).split(/\s+/).filter(Boolean);

/** všechna slova hledání musí v (už normalizovaném) textu být */
export const obsahujeVšechnaSlova = (normalizovanýText: string, slova: string[]): boolean =>
  slova.every((slovo) => normalizovanýText.includes(slovo));
