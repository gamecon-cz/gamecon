/* Celohra: typ_1pmn "Celohry" -> "Celohra" */
UPDATE akce_typy
SET typ_1pmn = 'Celohra'
WHERE url_typu_mn = 'celohra'
  AND typ_1pmn = 'Celohry';