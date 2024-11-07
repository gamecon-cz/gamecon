<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class StavyUctuUcastniku
{
    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    public function exportuj(
        ?string $format,
        string  $doSouboru = null,
    ) {
        $rocnik          = $this->systemoveNastaveni->rocnik();
        $predchoziRocnik = $rocnik - 1;
        $obsah           = [];
        foreach (\Uzivatel::vsichni() as $uzivatel) {
            $obsah[] = [
                'id_uzivatele'                                       => $uzivatel->id(),
                'jmeno'                                              => $uzivatel->jmenoNick(),
                'konecny_zustatek_' . $rocnik                        => $uzivatel->finance()->stav(),
                'suma_plateb_' . $rocnik                             => $uzivatel->finance()->sumaPlateb($rocnik),
                'zustatek_z_predchoziho_rocniku_' . $predchoziRocnik => $uzivatel->finance()->zustatekZPredchozichRocniku(),
            ];
        }

        $konfiguraceReportu = (new KonfiguraceReportu())
            ->setRowToFreeze(1)
            ->setMaxGenericColumnWidth(50);

        if ($doSouboru) {
            $konfiguraceReportu->setDestinationFile($doSouboru);
        }

        \Report::zPole($obsah)
            ->tFormat($format, null, $konfiguraceReportu);
    }
}
