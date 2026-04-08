/* DnD turnaj: aktualizovat krátký popis pro homepage */
UPDATE akce_typy
SET popis_kratky = 'Prověř své detektivní a roleplayové schopnosti, dobře se bav a vytvoř se svou družinou příběh, který vás společně dovede k vítězství.'
WHERE url_typu_mn = 'dnd';
