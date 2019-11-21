<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
DELETE FROM reporty
WHERE id IN (
            6,  -- Finance: Lidé v databázi + zůstatky
            7,  -- DrD: Historie účasti
            11, -- Zázemí & Program: Zařízení místností
            12, -- Finance: Aktivity negenerující slevu
            14, -- Finance: Příjmy a výdaje infopulťáka
            34, -- DrD: Seznam přihlášených pro aktuální rok
            35, -- Program: Hoňko report 2019
            36, -- Program: Emaily na vypravěče dle linií
            37, -- Program: Emaily na účastníky dle linií
            38, -- Program: Aktivity pro dotazník dle linií
            40, -- Potvrzení pro návštěvníky mladší patnácti let
            41, -- Zázemí & Program: Časy a umístění aktivit
            42, -- Zázemí & Program: Přehled místností
            43, -- Seznam účastníků a triček 2019
            44  -- Seznam účastníků a triček 2019 (Grouped)
);

RENAME TABLE reporty TO quick_reporty;

CREATE TABLE IF NOT EXISTS univerzalni_reporty(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
    skript VARCHAR(100) PRIMARY KEY,
    nazev VARCHAR(200),
    format_csv TINYINT(1) DEFAULT 1,
    format_html TINYINT(1) DEFAULT 1
);

INSERT INTO univerzalni_reporty(skript, nazev, format_csv, format_html)
VALUES
('aktivity',                                                      'Historie přihlášení na aktivity', 1, 1),                               
('pocty-her',                                                     'Účastníci a počty jejich aktivit', 1, 0),                             
('pocty-her-graf',                                                'Graf rozložení rozmanitosti her', 0, 1),                               
('rozesilani-ankety',                                             'Rozesílání ankety s tokenem', 0, 1),                                   
('parovani-ankety',                                               'Párování ankety a údajů uživatelů', 0, 1),                             
('grafy-ankety',                                                  'Grafy k anketě', 0, 1),                                                
('update-zustatku',                                               'UPDATE příkaz zůstatků pro letošní GC', 0, 1),                         
('neprihlaseni-vypraveci',                                        'Nepřihlášení a neubytovaní vypravěči', 0, 1),                          
('duplicity',                                                     'Duplicitní uživatelé', 0, 1),                                          
('stravenky',                                                     'Stravenky uživatelů', 0, 1),                                           
('stravenky?ciste',                                               'Stravenky (bianco)', 1, 1),
('programove-reporty',                                            'Programový report (2015)', 1, 1),
('zaplnenost-programu-ucastniku',                                 'Zaplněnost programu účastníků (2015)', 1, 1),
('maily-prihlaseni',                                              'Maily – přihlášení na GC (vč. unsubscribed)', 1, 1),
('maily-neprihlaseni',                                            'Maily – nepřihlášení na GC', 1, 1),
('maily-vypraveci',                                               'Maily – vypravěči (vč. unsubscribed)', 1, 1),
('maily-dle-data-ucasti?start=0',                                 'Maily - nedávní účastníci (prvních 2000)', 1, 0),                     
('maily-dle-data-ucasti?start=2000',                              'Maily - dávní účastníci (dalších 2000)', 1, 0),                       
('finance-lide-v-databazi-a-zustatky',                            'Finance: Lidé v databázi + zůstatky', 1, 1),
('finance-aktivity-negenerujici-slevu',                           'Finance: Aktivity negenerující slevu', 1, 1),
('finance-prijmy-a-vydaje-infopultaka',                           'Finance: Příjmy a výdaje infopulťáka', 1, 1),
('zazemi-a-program-drd-historie-ucasti',                          'Zázemí & Program: DrD: Historie účasti', 1, 1),
('zazemi-a-program-drd-seznam-prihlasenych-pro-aktualni-rok',     'Zázemí & Program: DrD: Seznam přihlášených pro aktuální rok', 1, 1),
('zazemi-a-program-zarizeni-mistnosti',                           'Zázemí & Program: Zařízení místností', 1, 1),
('zazemi-a-program-honko-report-pro-aktualni-rok',                'Zázemí & Program: Hoňko report pro aktuální rok', 1, 1),
('zazemi-a-program-emaily-na-vypravece-dle-linii',                'Zázemí & Program: Emaily na vypravěče dle linií', 1, 1),
('zazemi-a-program-emaily-na-ucastniky-dle-linii',                'Zázemí & Program: Emaily na účastníky dle linií', 1, 1),
('zazemi-a-program-aktivity-pro-dotaznik-dle-linii',              'Zázemí & Program: Aktivity pro dotazník dle linií', 1, 1),
('zazemi-a-program-potvrzeni-pro-navstevniky-mladsi-patnacti-let','Zázemí & Program: Potvrzení pro návštěvníky mladší patnácti let', 1, 1),
('zazemi-a-program-casy-a-umisteni-aktivit',                      'Zázemí & Program: Časy a umístění aktivit', 1, 1),
('zazemi-a-program-prehled-mistnosti',                            'Zázemí & Program: Přehled místností', 1, 1),
('zazemi-a-program-seznam-ucastniku-a-tricek',                    'Zázemí & Program: Seznam účastníků a triček', 1, 1),
('zazemi-a-program-seznam-ucastniku-a-tricek-grouped',            'Zázemí & Program: Seznam účastníků a triček (grouped)', 1, 1),
('celkovy-report',                                                '<br>Celkový report {ROK}<br><br>', 1, 1);

CREATE TABLE IF NOT EXISTS log_pouziti_reportu
(
    id SERIAL,
    id_univerzalniho_reportu INT UNSIGNED NOT NULL,
    id_uzivatele INT UNSIGNED NOT NULL,
    cas_pouziti DATETIME DEFAULT NOW(),
    KEY report_uzivatel (id_univerzalniho_reportu, id_uzivatele),
    FOREIGN KEY id_univerzalniho_reportu (id_univerzalniho_reportu) REFERENCES univerzalni_reporty(id) ON UPDATE CASCADE ON DELETE NO ACTION,
    FOREIGN KEY id_uzivatele (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele) ON UPDATE CASCADE ON DELETE NO ACTION
);
SQL
);
