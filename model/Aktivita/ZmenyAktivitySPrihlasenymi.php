<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Cas\DateTimeCz;

class ZmenyAktivitySPrihlasenymi
{
    public const SLEDOVANA_POLE = [
        'den'      => ['den'],
        'čas'      => [Sql::ZACATEK, Sql::KONEC],
        'cenu'     => [Sql::CENA],
        'kapacitu' => [Sql::TEAMOVA, Sql::TEAM_MIN, Sql::TEAM_MAX, Sql::KAPACITA, Sql::KAPACITA_F, Sql::KAPACITA_M],
    ];

    /**
     * Sledovaná pole, jejichž změna se u rodiny instancí propaguje na všechny instance
     * (viz Aktivita::editorZpracuj, větev `$zmenyVse`). Změna takového pole se proto týká
     * i hráčů na sourozeneckých instancích. Ostatní sledovaná pole (den, čas) jsou per-instance.
     */
    public const POLE_PROPAGOVANA_NA_RODINU = ['cenu', 'kapacitu'];

    /**
     * @var string[]
     */
    private array $zmeneneUdaje;

    /**
     * @param array<string, mixed> $dataZFormulare
     */
    public function __construct(
        private readonly ?Aktivita $puvodniAktivita,
        private readonly array $dataZFormulare,
    ) {
        $this->zmeneneUdaje = $this->vypocitejZmeneneUdaje();
    }

    public function maZmenySPrihlasenymi(): bool
    {
        return count($this->zmeneneUdaje) > 0;
    }

    public function dejTextPotvrzeniZmenyUdajuSPrihlasenymi(): string
    {
        if (! $this->puvodniAktivita) {
            throw new \LogicException('Nelze sestavit text potvrzení bez původní aktivity');
        }

        return sprintf(
            'Tato změna se dotkne přihlášených hráčů (%d). Opravdu chcete změnit %s?',
            $this->pocetDotcenychPrihlasenych(),
            implode(' / ', $this->zmeneneUdaje),
        );
    }

    /**
     * Počet přihlášených, kterých se chystaná změna reálně dotkne. Pokud se mění aspoň jedno
     * pole propagované na celou rodinu instancí, jde o všechny hráče v rodině; jinak jen o hráče
     * na této instanci.
     */
    private function pocetDotcenychPrihlasenych(): int
    {
        if (! $this->puvodniAktivita) {
            return 0;
        }
        foreach ($this->zmeneneUdaje as $stitek) {
            if (in_array($stitek, self::POLE_PROPAGOVANA_NA_RODINU, true)) {
                return $this->puvodniAktivita->pocetPrihlasenychVcetneInstanci();
            }
        }

        return $this->puvodniAktivita->pocetPrihlasenych();
    }

    /**
     * @return string[]
     */
    private function vypocitejZmeneneUdaje(): array
    {
        if (! $this->puvodniAktivita || $this->puvodniAktivita->pocetPrihlasenychVcetneInstanci() <= 0) {
            return [];
        }

        $pocetNaInstanci = $this->puvodniAktivita->pocetPrihlasenych();
        $pocetVRodine = $this->puvodniAktivita->pocetPrihlasenychVcetneInstanci();

        $detekce = [
            'den'      => fn () => $this->zmenenDen(),
            'čas'      => fn () => $this->zmenenCas(),
            'cenu'     => fn () => $this->zmenenaCena(),
            'kapacitu' => fn () => $this->zmenenaKapacita(),
        ];

        $zmeneneUdaje = [];
        foreach (self::SLEDOVANA_POLE as $stitek => $_pole) {
            if (! $detekce[$stitek]()) {
                continue;
            }
            // Pole propagovaná na celou rodinu varují, pokud má hráče kdokoli z rodiny;
            // per-instance pole (den, čas) varují jen pokud má hráče právě tato instance.
            $pocetDotcenych = in_array($stitek, self::POLE_PROPAGOVANA_NA_RODINU, true)
                ? $pocetVRodine
                : $pocetNaInstanci;
            if ($pocetDotcenych > 0) {
                $zmeneneUdaje[] = $stitek;
            }
        }

        return $zmeneneUdaje;
    }

    private function zmenenDen(): bool
    {
        $puvodniDen = $this->puvodniAktivita->denProgramu()?->format(DateTimeCz::FORMAT_DATUM_DB)
            ?? '0';
        $novyDen = trim((string) ($this->dataZFormulare['den'] ?? ''));
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
            $puvodniZacatekHodina = (int) $puvodniZacatek->format('G');
            $puvodniZacatekVUpravach = (string) ($puvodniZacatekHodina === 0
                ? 24
                : $puvodniZacatekHodina);
        }
        $puvodniKonec = $this->puvodniAktivita->konec();
        $puvodniKonecVUpravach = '';
        if ($puvodniKonec) {
            $puvodniKonecHodina = (int) (clone $puvodniKonec)->sub(new \DateInterval('PT1H'))->format('G');
            $puvodniKonecVUpravach = (string) ($puvodniKonecHodina + 1);
        }
        $novyZacatek = trim((string) ($this->dataZFormulare[Sql::ZACATEK] ?? ''));
        $novyKonec = trim((string) ($this->dataZFormulare[Sql::KONEC] ?? ''));

        return $puvodniZacatekVUpravach !== $novyZacatek || $puvodniKonecVUpravach !== $novyKonec;
    }

    private function zmenenaCena(): bool
    {
        $puvodniCena = (int) $this->puvodniAktivita->rawDb()[Sql::CENA];
        $novaCena = (int) ($this->dataZFormulare[Sql::CENA] ?? 0);

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
     *
     * @return array<string, int>
     */
    private function normalizovanaKapacitaProPotvrzeni(array $data): array
    {
        $teamova = ! empty($data[Sql::TEAMOVA]);
        if ($teamova) {
            return [
                Sql::TEAMOVA    => 1,
                Sql::KAPACITA   => (int) ($data[Sql::TEAM_MAX] ?? $data[Sql::KAPACITA] ?? 0),
                Sql::KAPACITA_F => 0,
                Sql::KAPACITA_M => 0,
                Sql::TEAM_MIN   => (int) ($data[Sql::TEAM_MIN] ?? 0),
            ];
        }

        return [
            Sql::TEAMOVA    => 0,
            Sql::KAPACITA   => (int) ($data[Sql::KAPACITA] ?? 0),
            Sql::KAPACITA_F => (int) ($data[Sql::KAPACITA_F] ?? 0),
            Sql::KAPACITA_M => (int) ($data[Sql::KAPACITA_M] ?? 0),
            Sql::TEAM_MIN   => 0,
        ];
    }
}
