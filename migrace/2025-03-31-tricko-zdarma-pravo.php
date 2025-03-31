<?php

// přidání JAKEKOLIV_TRICKO_ZDARMA
$this->q(<<<SQL
    INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
    VALUES
        (1035, 'Jedno jakékoliv tričko zdarma', '(právo na dvě trička má přednost)')
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO prava_role(id_role, id_prava)
    VALUES
        (26/* mini-org */, 1035/* JAKEKOLIV_TRICKO_ZDARMA */),
        (26/* mini-org */, 1021/* MUZE_OBJEDNAVAT_MODRA_TRICKA */)
SQL
);
