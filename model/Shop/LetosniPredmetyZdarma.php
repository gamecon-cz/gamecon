<?php

declare(strict_types=1);

namespace Gamecon\Shop;

use App\Discount\DiscountRule;
use App\Discount\DiscountRuleLoader;
use App\Discount\DiscountScope;
use App\Enum\ProductStateEnum;

/**
 * This year's free dice and badge: the product the rule of each year names, set from the
 * e-shop import's je_letosni_hlavni column and checked against the catalogue. The generic row
 * of the rule names none; the first import of a year clones it into a row of that year.
 */
class LetosniPredmetyZdarma
{
    public function __construct(
        private readonly int $rocnik,
    ) {
    }

    /**
     * @return list<array{pravidlo: string, druh: string, kod: ?string, nazevPredmetu: ?string}>
     */
    public function stav(): array
    {
        return array_map(
            fn (DiscountRule $pravidlo): array => [
                'pravidlo'      => $pravidlo->name,
                'druh'          => (string) $pravidlo->parameters->codeFragment,
                'kod'           => $pravidlo->parameters->productCode,
                'nazevPredmetu' => $this->nazevVNabidce($pravidlo->parameters->productCode),
            ],
            $this->pravidla(),
        );
    }

    /**
     * @param string[]|null $oznaceneKody items the sheet flags as this year's; null when the
     *                                    sheet has no such column, which leaves the rules alone
     *
     * @return string[] warnings for the admin
     */
    public function nastavZImportu(?array $oznaceneKody): array
    {
        $varovani = [];
        if ($oznaceneKody !== null) {
            foreach ($this->pravidla() as $pravidlo) {
                $druh = (string) $pravidlo->parameters->codeFragment;
                $kandidati = array_values(array_filter(
                    $oznaceneKody,
                    static fn (string $kod): bool => mb_stripos($kod, $druh) !== false,
                ));
                if (count($kandidati) === 1) {
                    if (! $this->uloz($pravidlo, $kandidati[0])) {
                        $varovani[] = sprintf('„%s": kód „%s" se do pravidla pro ročník %d nepodařilo zapsat, protože chybí obecný řádek pravidla (year = %d). Pravidlo zůstává beze změny.', $pravidlo->name, $kandidati[0], $this->rocnik, DiscountRuleLoader::ANY_YEAR);
                    }
                } elseif ($kandidati === []) {
                    $varovani[] = sprintf('„%s": v listu není jako letošní (je_letosni_hlavni) označený žádný předmět s „%s" v kódu, pravidlo zůstává beze změny.', $pravidlo->name, $druh);
                } else {
                    $varovani[] = sprintf('„%s": v listu je jako letošní označeno víc předmětů (%s), pravidlo zůstává beze změny.', $pravidlo->name, implode(', ', $kandidati));
                }
            }
        }

        foreach ($this->stav() as $radek) {
            if ($radek['kod'] === null) {
                $varovani[] = sprintf('„%s": letošní předmět není určený, nikdo ho nedostane zdarma.', $radek['pravidlo']);
            } elseif ($radek['nazevPredmetu'] === null) {
                $varovani[] = sprintf('„%s": předmět „%s" v letošní nabídce není, nikdo ho nedostane zdarma.', $radek['pravidlo'], $radek['kod']);
            }
        }

        return array_values(array_unique($varovani));
    }

    /**
     * @return DiscountRule[]
     */
    private function pravidla(): array
    {
        return array_values(array_filter(
            (new DiscountRuleLoader('dbFetchAll'))->rulesForYear($this->rocnik),
            static fn (DiscountRule $pravidlo): bool => $pravidlo->parameters->scope === DiscountScope::PRODUCT_CODE,
        ));
    }

    private function uloz(DiscountRule $pravidlo, string $kod): bool
    {
        dbExecute(
            <<<'SQL'
INSERT INTO discount_rule (code, name, description, required_right, priority, parameters, year, active)
SELECT code, name, description, required_right, priority, $0, $1, active
FROM discount_rule
WHERE code = $2 AND year = $3
ON DUPLICATE KEY UPDATE parameters = VALUES(parameters)
SQL,
            [
                0 => json_encode($pravidlo->parameters->withProductCode($kod)->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                1 => $this->rocnik,
                2 => $pravidlo->code,
                3 => DiscountRuleLoader::ANY_YEAR,
            ],
        );
        $zapsano = dbOneCol(
            <<<'SQL'
                SELECT JSON_VALUE(parameters, '$.productCode')
                FROM discount_rule
                WHERE code = $0 AND year = $1
                SQL,
            [
                0 => $pravidlo->code,
                1 => $this->rocnik,
            ],
        );

        return $zapsano === $kod;
    }

    private function nazevVNabidce(?string $kod): ?string
    {
        if ($kod === null) {
            return null;
        }
        $nazev = dbOneCol(
            'SELECT nazev FROM shop_predmety WHERE kod_predmetu = $0 AND archived_at IS NULL AND stav <> $1',
            [
                0 => $kod,
                1 => ProductStateEnum::RETIRED->value,
            ],
        );

        return $nazev === null || $nazev === false ? null : (string) $nazev;
    }
}
