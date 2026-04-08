/* Celohra: aktualizovat krátký popis pro homepage */
UPDATE akce_typy
SET popis_kratky = 'Jediná hra, která se ti neokouká! Každý rok je psaná na míru pro aktuální GameCon téma, má nový příběh i herní mechaniky. Hraj, dokud je čas!'
WHERE url_typu_mn = 'celohra';
