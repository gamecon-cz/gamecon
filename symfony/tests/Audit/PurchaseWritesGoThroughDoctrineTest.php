<?php

declare(strict_types=1);

namespace App\Tests\Audit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * The purchase log is written by a Doctrine listener, so a raw write to shop_nakupy would change
 * a purchase and leave no trace. Migrations are exempt: they run once.
 *
 * Catches a statement or helper call that names the table, its structure constant or the entity.
 * Missed: a table name held in a variable, a repository's own query builder, a joined multi-table UPDATE.
 *
 * @see \App\EventListener\OrderItemAuditListener
 */
class PurchaseWritesGoThroughDoctrineTest extends TestCase
{
    private const string ENTITY = '\\\\?(?:App\\\\{1,2}Entity\\\\{1,2})?OrderItem';

    private const string TABLE_CONSTANT = '[\w\\\\]*OrderItemSqlStructure::_table\b|[\w\\\\]*::SHOP_NAKUPY_TABULKA\b';

    private const string RAW_WRITE = '/'
        // SQL naming the table
        . '\b(?:INSERT(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY))?(?:\s+IGNORE)?(?:\s+INTO)?|REPLACE(?:\s+INTO)?|UPDATE(?:\s+(?:LOW_PRIORITY|IGNORE))*|DELETE(?:\s+\w+)?\s+FROM)\s+(?:`?\w+`?\.)?`?shop_nakupy\b'
        // legacy dbInsert() / dbUpdate() / dbDelete() family and DBAL's insert() / update() / delete(), which take the table apart from the SQL
        . '|(?:\bdb(?:Insert|Update|Delete)\w*|->(?:insert|update|delete))\(\s*(?:table:\s*)?(?:[\'"]shop_nakupy[\'"]|' . self::TABLE_CONSTANT . ')'
        // SQL assembled around the structure constant
        . '|\b(?:INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s*[\'"]\s*\.\s*(?:' . self::TABLE_CONSTANT . ')'
        // DQL and the ORM query builder naming the entity
        . '|\b(?:UPDATE|DELETE(?:\s+FROM)?)\s+' . self::ENTITY . '\b'
        . '|->(?:update|delete)\(\s*[\'"]?' . self::ENTITY . '(?:::class\b|[\'"])'
        . '/i';

    public function testNothingWritesToPurchasesBehindDoctrine(): void
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];

        $files = (new Finder())
            ->files()
            ->name('*.php')
            ->in([$root . '/model', $root . '/admin', $root . '/web', $root . '/symfony/src']);
        foreach ($files as $file) {
            if (self::writesToPurchases($file->getContents())) {
                $offenders[] = substr($file->getRealPath(), strlen($root) + 1);
            }
        }

        self::assertSame(
            [],
            $offenders,
            'A raw write to shop_nakupy is invisible to the purchase log; go through the entity manager.',
        );
    }

    #[DataProvider('writes')]
    public function testRecognisesAWriteToPurchases(string $code): void
    {
        self::assertTrue(self::writesToPurchases($code));
    }

    #[DataProvider('notWrites')]
    public function testLeavesAloneWhatDoesNotWriteToPurchases(string $code): void
    {
        self::assertFalse(self::writesToPurchases($code));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function writes(): array
    {
        return [
            'insert'                                   => ['INSERT INTO shop_nakupy (id_uzivatele) VALUES (1)'],
            'insert without INTO'                      => ['INSERT shop_nakupy SET rok = 2026'],
            'update ignore, quoted name'               => ['UPDATE IGNORE `shop_nakupy` SET id_uzivatele = 2'],
            'schema-qualified delete'                  => ['DELETE FROM `gamecon`.`shop_nakupy` WHERE id_nakupu = 1'],
            'multi-table delete'                       => ["DELETE nakup\nFROM shop_nakupy AS nakup"],
            'dbInsert helper'                          => ["dbInsert('shop_nakupy', ['rok' => 2026])"],
            'dbUpdate helper'                          => ['dbUpdate("shop_nakupy", $data, $where)'],
            'dbDelete helper'                          => ["dbDelete('shop_nakupy', ['id_nakupu' => 1])"],
            'helper with a named argument'             => ["dbInsert(table: 'shop_nakupy', valArray: \$row)"],
            'helper with the legacy table constant'    => ['dbUpdate(NakupySql::SHOP_NAKUPY_TABULKA, $data, $where)'],
            'DBAL insert'                              => ["\$connection->insert('shop_nakupy', \$row)"],
            'DBAL delete with the structure constant'  => ['$connection->delete(OrderItemSqlStructure::_table, $where)'],
            'SQL built from the structure constant'    => ["\$connection->executeStatement('DELETE FROM ' . OrderItemSqlStructure::_table)"],
            'DQL update'                               => ['UPDATE App\Entity\OrderItem orderItem SET orderItem.year = 1'],
            'DQL delete'                               => ['DELETE FROM OrderItem orderItem'],
            'DQL delete without FROM'                  => ['DELETE App\Entity\OrderItem orderItem'],
            'query builder delete'                     => ['$builder->delete(\App\Entity\OrderItem::class, \'orderItem\')'],
            'query builder update'                     => ['$builder->update(OrderItem::class, \'orderItem\')'],
            'query builder with the class as a string' => ['$builder->delete(\'App\\\\Entity\\\\OrderItem\', \'orderItem\')'],
            'code after a comment'                     => ["<?php // a note\n\$connection->delete('shop_nakupy', \$where);"],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notWrites(): array
    {
        return [
            'select'                                 => ['SELECT * FROM shop_nakupy WHERE id_uzivatele = 1'],
            'the log itself'                         => ['INSERT INTO shop_order_item_log (order_item_id) VALUES (1)'],
            'the log through its structure constant' => ['$connection->update(OrderItemLogSqlStructure::_table, $data, $where)'],
            'the cancellation archive'               => ['UPDATE shop_nakupy_zrusene SET zdroj_zruseni = NULL'],
            'a column named like it'                 => ['dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, $data, $where)'],
            'another table through its constant'     => ['dbDelete(UzivateleHodnotySqlStruktura::UZIVATELE_HODNOTY_TABULKA, $where)'],
            'the entity manager'                     => ['$this->entityManager->remove($orderItem)'],
            'DQL select'                             => ['SELECT orderItem FROM App\Entity\OrderItem orderItem'],
            'a docblock that mentions the entity'    => ["<?php\n/** Update OrderItem pricing when the role changes. */"],
        ];
    }

    private static function writesToPurchases(string $source): bool
    {
        if (preg_match(self::RAW_WRITE, $source) !== 1) {
            return false;
        }

        // A docblock that talks about "update OrderItem" is prose, not a query.
        $code = '';
        foreach (\PhpToken::tokenize($source) as $token) {
            if (! $token->is([T_COMMENT, T_DOC_COMMENT])) {
                $code .= $token->text;
            }
        }

        return preg_match(self::RAW_WRITE, $code) === 1;
    }
}
