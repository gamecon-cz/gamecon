<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Cas\DateTimeCz;

class ZmenyAktivitySPrihlasenymi
{
    /** @var string[] */
    private array $zmeneneUdaje;

    /**
     * @param array<string, mixed> $dataZFormulare
     */
    public function __construct(
        private readonly ?Aktivita $puvodniAktivita,
        private readonly array     $dataZFormulare,
    ) {
        $this->zmeneneUdaje = $this->vypocitejZmeneneUdaje();
    }

    public function maZmenySPrihlasenymi(): bool
    {
        return count($this->zmeneneUdaje) > 0;
    }

    public function dejTextPotvrzeniZmenyUdajuSPrihlasenymi(): string
    {
        return sprintf(
            'Tato aktivita už má přihlášené hráče. Opravdu chcete změnit %s?',
            implode(' / ', $this->zmeneneUdaje),
        );
    }

    /**
     * @return string[]
     */
    private function vypocitejZmeneneUdaje(): array
    {
        if (!$this->puvodniAktivita || $this->puvodniAktivita->pocetPrihlasenych() <= 0) {
            return [];
        }

        $zmeneneUdaje = [];
        if ($this->zmenenDen()) {
            $zmeneneUdaje[] = 'den';
        }
        if ($this->zmenenCas()) {
            $zmeneneUdaje[] = 'čas';
        }
        if ($this->zmenenaCena()) {
            $zmeneneUdaje[] = 'cenu';
        }
        if ($this->zmenenaKapacita()) {
            $zmeneneUdaje[] = 'kapacitu';
        }

        return $zmeneneUdaje;
    }

    private function zmenenDen(): bool
    {
        $puvodniDen = $this->puvodniAktivita->denProgramu()?->format(DateTimeCz::FORMAT_DATUM_DB)
            ?? '0';
        $novyDen = trim((string)($this->dataZFormulare['den'] ?? ''));
        if ($novyDen === '') {
            $novyDen = '0';
        }

        return $puvodniDen !== $novyDen;
    }

    private function zmenenCas(): bool
    {
        $puvodniZacatek = $this->puvodniAktivita->zacatek();
        $puvodniZacatekVUpravach = '';
        if ($puvodniZacatek) {
            $puvodniZacatekHodina = (int)$puvodniZacatek->format('G');
            $puvodniZacatekVUpravach = (string)($puvodniZacatekHodina === 0
                ? 24
                : $puvodniZacatekHodina);
        }
        $puvodniKonec = $this->puvodniAktivita->konec();
        $puvodniKonecVUpravach = '';
        if ($puvodniKonec) {
            $puvodniKonecHodina = (int)(clone $puvodniKonec)->sub(new \DateInterval('PT1H'))->format('G');
            $puvodniKonecVUpravach = (string)($puvodniKonecHodina + 1);
        }
        $novyZacatek = trim((string)($this->dataZFormulare[Sql::ZACATEK] ?? ''));
        $novyKonec = trim((string)($this->dataZFormulare[Sql::KONEC] ?? ''));

        return $puvodniZacatekVUpravach !== $novyZacatek || $puvodniKonecVUpravach !== $novyKonec;
    }

    private function zmenenaCena(): bool
    {
        $puvodniCena = (int)$this->puvodniAktivita->rawDb()[Sql::CENA];
        $novaCena = (int)($this->dataZFormulare[Sql::CENA] ?? 0);

        return $puvodniCena !== $novaCena;
    }

    private function zmenenaKapacita(): bool
    {
        $puvodniKapacity = $this->normalizovanaKapacitaProPotvrzeni($this->puvodniAktivita->rawDb());
        $noveKapacity = $this->normalizovanaKapacitaProPotvrzeni($this->dataZFormulare);

        return $puvodniKapacity !== $noveKapacity;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, int>
     */
    private function normalizovanaKapacitaProPotvrzeni(array $data): array
    {
        $teamova = !empty($data[Sql::TEAMOVA]);
        if ($teamova) {
            return [
                Sql::TEAMOVA => 1,
                Sql::KAPACITA => (int)($data[Sql::TEAM_MAX] ?? $data[Sql::KAPACITA] ?? 0),
                Sql::KAPACITA_F => 0,
                Sql::KAPACITA_M => 0,
                Sql::TEAM_MIN => (int)($data[Sql::TEAM_MIN] ?? 0),
            ];
        }

        return [
            Sql::TEAMOVA => 0,
            Sql::KAPACITA => (int)($data[Sql::KAPACITA] ?? 0),
            Sql::KAPACITA_F => (int)($data[Sql::KAPACITA_F] ?? 0),
            Sql::KAPACITA_M => (int)($data[Sql::KAPACITA_M] ?? 0),
            Sql::TEAM_MIN => 0,
        ];
    }
}
