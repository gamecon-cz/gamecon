<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\Cas\DateTimeCz;
use Gamecon\SystemoveNastaveni\SqlStruktura\SystemoveNastaveniSqlStruktura as Sql;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Which UPPERCASE values a template may substitute. An e-mail text goes to people outside
 * the organisation, so nothing may leak in that is not explicitly public.
 */
class SystemoveHodnotyProSablony
{
    /**
     * Constants from the configuration that are not settings and so have no public
     * flag. ANY defined constant could be substituted, the database password too,
     * so the list must stay explicit.
     *
     * @var list<string>
     */
    public const POVOLENE_KONSTANTY = ['UCET_CZ', 'IBAN'];

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    public function stav(string $nazev): StavSystemoveHodnotyEnum
    {
        if (in_array($nazev, self::POVOLENE_KONSTANTY, true)) {
            return StavSystemoveHodnotyEnum::POVOLENA;
        }

        $zaznam = $this->systemoveNastaveni->dejVsechnyZaznamyNastaveni()[$nazev] ?? null;
        if ($zaznam === null) {
            return StavSystemoveHodnotyEnum::NEZNAMA;
        }

        return $zaznam[Sql::VEREJNE]
            ? StavSystemoveHodnotyEnum::POVOLENA
            : StavSystemoveHodnotyEnum::NEVEREJNA;
    }

    /**
     * @return string|null null when the value must not or cannot be substituted
     */
    public function hodnota(string $nazev): ?string
    {
        if ($this->stav($nazev) !== StavSystemoveHodnotyEnum::POVOLENA) {
            return null;
        }

        if (in_array($nazev, self::POVOLENE_KONSTANTY, true)) {
            return defined($nazev)
                ? (string)constant($nazev)
                : null;
        }

        $datovyTyp = (string)$this->systemoveNastaveni->dejVsechnyZaznamyNastaveni()[$nazev][Sql::DATOVY_TYP];

        return $this->naTextEmailu(
            $this->systemoveNastaveni->dejHodnotu($nazev, false),
            $datovyTyp,
        );
    }

    /**
     * For the editor: everything that can be written as %UPPERCASE%, with a description and the current value.
     *
     * @return array<string, array{popis: string, ukazka: string}>
     */
    public function povoleneProEditor(): array
    {
        $povolene = [];
        foreach (self::POVOLENE_KONSTANTY as $nazevKonstanty) {
            $hodnota = $this->hodnota($nazevKonstanty);
            if ($hodnota !== null) {
                $povolene[$nazevKonstanty] = [
                    'popis'  => 'konstanta z nastavení GameConu',
                    'ukazka' => $hodnota,
                ];
            }
        }

        foreach ($this->systemoveNastaveni->dejVsechnyZaznamyNastaveni() as $klic => $zaznam) {
            if (!$zaznam[Sql::VEREJNE]) {
                continue;
            }
            $hodnota = $this->hodnota((string)$klic);
            if ($hodnota === null) {
                continue;
            }
            $povolene[$klic] = [
                'popis'  => (string)$zaznam[Sql::NAZEV],
                'ukazka' => $hodnota,
            ];
        }
        ksort($povolene);

        return $povolene;
    }

    /**
     * Settings an e-mail must not substitute. Their names are not secret, the editor
     * needs them to tell the author 'it is non-public' instead of 'unknown'.
     *
     * @return list<string>
     */
    public function neverejneKlice(): array
    {
        $neverejne = [];
        foreach ($this->systemoveNastaveni->dejVsechnyZaznamyNastaveni() as $klic => $zaznam) {
            if (!$zaznam[Sql::VEREJNE]) {
                $neverejne[] = (string)$klic;
            }
        }
        sort($neverejne);

        return $neverejne;
    }

    private function naTextEmailu(mixed $hodnota, string $datovyTyp): string
    {
        return match (strtolower($datovyTyp)) {
            'bool', 'boolean' => $hodnota
                ? 'ano'
                : 'ne',
            'date'            => $this->jakoDatum($hodnota, casem: false),
            'datetime'        => $this->jakoDatum($hodnota, casem: true),
            'number', 'float' => str_replace('.', ',', (string)$hodnota),
            default           => (string)$hodnota,
        };
    }

    private function jakoDatum(mixed $hodnota, bool $casem): string
    {
        if ($hodnota === null || $hodnota === '') {
            return '';
        }
        $datum = new DateTimeCz((string)$hodnota);

        return $casem
            ? $datum->formatCasNaMinutyStandard()
            : $datum->formatDatumStandard();
    }
}
