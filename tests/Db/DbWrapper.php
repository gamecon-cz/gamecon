<?php

namespace Gamecon\Tests\Db;

/**
 * Wrapper for calling database operations.
 *
 * Raw connection cannot be used, because tests need nested transactions
 * mechanism, which has to be the same mechanism as used for transactions in
 * actual implementations.
 */
class DbWrapper
{

  public function begin()
  {
    dbBegin();
  }

  public function escape($value): string
  {
    return dbQv($value);
  }

  function query(string $sql, array $params = null)
  {
    return dbQuery($sql, $params);
  }

  public function rollback()
  {
    dbRollback();
  }

  public function import(Dataset $dataset)
  {
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

}
