<?php

namespace Godric\DbTest;

/**
 * Wrapper for calling database operations.
 *
 * Raw connection cannot be used, because tests need nested transactions
 * mechanism, which has to be the same mechanism as used for transactions in
 * actual implementations.
 */
abstract class DbWrapper {

    abstract function begin();

    abstract function escape($value);

    function import(Dataset $dataset) {
        foreach ($dataset->getTables() as $table) {
            $tableName = $table->getName();
            $columnNames = implode(',', $table->getColumns());

            $sql = "INSERT INTO $tableName ($columnNames) VALUES";
            foreach ($table->getRows() as $row) {
                $escapedValues = array_map([$this, 'escape'], $row);
                $escapedValues = implode(',', $escapedValues);
                $sql .= "\n($escapedValues),";
            }
            $sql[strlen($sql) - 1] = ';';

            $this->query($sql);
        }
    }

    abstract function query($sql);

    abstract function rollback();

}
