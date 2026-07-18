<?php
/** @var \Godric\DbMigrations\Migration $this */

// Tabulka uložených QR kódů generovaných v modulu /qrka.
$this->q(<<<SQL
CREATE TABLE `qr_kody` (
  `id`                    int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nazev`                 varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `obsah`                 text          COLLATE utf8_czech_ci NOT NULL
      COMMENT 'zakódovaný text / URL',
  `logo_soubor`           varchar(255) COLLATE utf8_czech_ci DEFAULT NULL
      COMMENT 'název souboru loga ve složce qrka/ (NULL = bez loga)',
  `soubor`                varchar(255) COLLATE utf8_czech_ci DEFAULT NULL
      COMMENT 'relativní cesta k vygenerovanému PNG (NULL = ještě nevygenerováno)',
  `vytvoril_id_uzivatele` int(11) DEFAULT NULL,
  `vytvoreno`             datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb3
  COLLATE=utf8mb3_czech_ci
SQL,
);

// Seed: stávajících 5 napevno zadaných QR kódů (dosud generovaných za běhu).
// Loga jsou jejich per-síťová PNG ve složce qrka/. `soubor` necháme NULL —
// modul PNG dogeneruje a uloží při prvním zobrazení.
$seed = [
    ['nazev' => 'instagram',       'obsah' => 'https://gamecon.cz/instagram', 'logo_soubor' => 'instagram.png'],
    ['nazev' => 'facebook',        'obsah' => 'https://gamecon.cz/facebook',  'logo_soubor' => 'facebook.png'],
    ['nazev' => 'discord',         'obsah' => 'https://gamecon.cz/discord',   'logo_soubor' => 'discord.png'],
    ['nazev' => 'youtube',         'obsah' => 'https://gamecon.cz/youtube',   'logo_soubor' => 'youtube.png'],
    ['nazev' => 'tiskova_kronika', 'obsah' => 'https://gamecon.cz/soubory/obsah/download/tiskova_kronika.pdf', 'logo_soubor' => null],
];

foreach ($seed as $radek) {
    $nazev = $this->connection->real_escape_string($radek['nazev']);
    $obsah = $this->connection->real_escape_string($radek['obsah']);
    $logo  = $radek['logo_soubor'] === null
        ? 'NULL'
        : "'" . $this->connection->real_escape_string($radek['logo_soubor']) . "'";
    $this->q(<<<SQL
INSERT INTO `qr_kody` (`nazev`, `obsah`, `logo_soubor`)
VALUES ('{$nazev}', '{$obsah}', {$logo})
SQL,
    );
}
