<?php

declare(strict_types=1);

namespace Gamecon\Shop;

use App\Discount\DiscountRule;
use App\Discount\DiscountRuleLoader;
use App\Discount\DiscountScope;
use App\Enum\ProductStateEnum;

/**
 * This year's free dice and badge: the product each year's rule names, set from the
 * e-shop import's je_letosni_hlavni column and checked against the catalogue.
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
                    $this->uloz($pravidlo, $kandidati[0]);
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

    private function uloz(DiscountRule $pravidlo, string $kod): void
    {
        dbQuery(
            'UPDATE discount_rule SET parameters = $0 WHERE code = $1 AND year = $2',
            [
                0 => json_encode($pravidlo->parameters->withProductCode($kod)->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                1 => $pravidlo->code,
                2 => $this->rocnik,
            ],
        );
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
