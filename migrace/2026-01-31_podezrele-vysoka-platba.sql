-- Přidání nastavení pro detekci podezřele vysokých plateb účastníků
INSERT INTO systemove_nastaveni
(klic, hodnota, vlastni, datovy_typ, nazev, popis, zmena_kdy, skupina, poradi, pouze_pro_cteni, rocnik_nastaveni)
VALUES
('PODEZRELE_VYSOKA_PLATBA_UCASTNIKA', '10000', 1, 'integer', 'Podezřele vysoká platba účastníka',
'Částka v Kč, která už je tak velká, že se odešle upozornění CFO na právě spárovanou platbu',
NOW(), 'Finance', 28, 0, -1);
