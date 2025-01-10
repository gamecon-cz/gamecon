<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Admin\Modules\Aktivity\Import\ImporterUcastnikuNaAktivitu;
use Gamecon\Aktivita\Aktivita;

class ExportUcastnikuNaAktivitach
{
    /**
     * @param array<Aktivita> $aktivity
     */
    public function exportuj(
        string  $nazev,
        array   $aktivity,
        ?string $format = 'xlsx',
    ) {
        \Report::zPole($this->extrahujDataUcastnikuNaAktivitach($aktivity))
            ->tFormat($format, $nazev);
    }

    /**
     * @param array<Aktivita> $aktivity
     * @return array<array{id_aktivity: int, aktivita: string, ucastnik: string}>
     */
    private function extrahujDataUcastnikuNaAktivitach(array $aktivity): array
    {
        $data = [];
        foreach ($aktivity as $aktivita) {
            $data = [
                ...$data,
                ...$this->extrahujDataUcastnikuNaJedneAktivite($aktivita),
            ];
        }

        return $data;
    }

    /**
     * @return array<array{id_aktivity: int, aktivita: string, ucastnik: string}>
     */
    private function extrahujDataUcastnikuNaJedneAktivite(Aktivita $aktivita): array
    {
        $data = [];
        foreach ($aktivita->seznamPrihlasenychNeboDorazivsich() as $ucastnik) {
            $data[] = [
                'id_aktivity' => $aktivita->id(),
                'aktivita' => $aktivita->nazev(),
                'ucastnik' => $ucastnik->jmenoNick(),
            ];
        }
        if ($aktivita->id() === 5414) {
            $foo = 1;
        }
        if ($data === []) {
            $data[] = [
                'id_aktivity' => $aktivita->id(),
                'aktivita' => $aktivita->nazev(),
                'ucastnik' => '',
            ];
        }

        return $data;
    }
}
