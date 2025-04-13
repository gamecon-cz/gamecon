<?php

// přidání UBYTOVANI_NEDELNI_NOC_NABIZET
$this->q(<<<SQL
    INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
    VALUES
        (1036/* UBYTOVANI_NEDELNI_NOC_NABIZET */, 'Nedělní ubytování', '(není potřeba pokud má neděli zdarma)')
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO prava_role(id_role, id_prava)
    VALUES
        (26/* MINI_ORG */, 1036/* UBYTOVANI_NEDELNI_NOC_NABIZET */)
SQL
);
