<?php

/**
 * Global variables used by certain functions
 * not all of them, see also dbConnect()
 */
global $dbTransactionDepth;
$dbTransactionDepth = 0;

/**
 * Load one column into array in $id => $value manner
 */
function dbArrayCol(
    $q,
    $param = null,
    $pdo = null,
) {
    $a = dbQueryS($q, $param, $pdo);
    $o = [];
    while ($r = $a->fetch(PDO::FETCH_NUM)) {
        $o[$r[0]] = $r[1];
    }

    return $o;
}

/**
 * Begins transaction
 */
function dbBegin()
{
    if (!isset($GLOBALS['dbTransactionDepth']) || $GLOBALS['dbTransactionDepth'] < 0) {
        $GLOBALS['dbTransactionDepth'] = 0;
    }
    if ($GLOBALS['dbTransactionDepth'] == 0) {
        dbQuery('BEGIN');
    } else {
        dbQuery('SAVEPOINT nesttrans' . $GLOBALS['dbTransactionDepth']);
    }
    $GLOBALS['dbTransactionDepth']++;
}

/**
 * Commits transaction
 */
function dbCommit()
{
    if (!isset($GLOBALS['dbTransactionDepth'])) {
        $GLOBALS['dbTransactionDepth'] = 0;
    }
    if ($GLOBALS['dbTransactionDepth'] <= 0) {
        throw new Exception('nothing to commit');
    }
    if ($GLOBALS['dbTransactionDepth'] == 1) {
        dbQuery('COMMIT');
    } else {
        try {
            dbQuery('RELEASE SAVEPOINT nesttrans' . ($GLOBALS['dbTransactionDepth'] - 1));
        } catch (\Throwable $e) {
            // If savepoint doesn't exist, do a full commit and reset depth
            if (str_contains($e->getMessage(), 'SAVEPOINT') && str_contains($e->getMessage(), 'does not exist')) {
                dbQuery('COMMIT'); // just to be sure (executing COMMIT even if there is nothing to commit is safe); missing SAVEPOINT mostly means that an implicit COMMIT happened on CREATE / ALTER / DROP
                $GLOBALS['dbTransactionDepth'] = 0;
                return;
            }
            throw $e;
        }
    }
    $GLOBALS['dbTransactionDepth']--;
}

/**
 * Commits transaction
 */
function dbRollback()
{
    if (!isset($GLOBALS['dbTransactionDepth'])) {
        $GLOBALS['dbTransactionDepth'] = 0;
    }
    if ($GLOBALS['dbTransactionDepth'] <= 0) {
        return;
    }
    if ($GLOBALS['dbTransactionDepth'] == 1) {
        dbQuery('ROLLBACK');
    } else {
        try {
            dbQuery('ROLLBACK TO SAVEPOINT nesttrans' . ($GLOBALS['dbTransactionDepth'] - 1));
        } catch (\Throwable $e) {
            // If savepoint doesn't exist, do a full rollback and reset depth
            if (str_contains($e->getMessage(), 'SAVEPOINT') && str_contains($e->getMessage(), 'does not exist')) {
                dbQuery('ROLLBACK');
                $GLOBALS['dbTransactionDepth'] = 0;
                return;
            }
            throw $e;
        }
    }
    $GLOBALS['dbTransactionDepth']--;
}

function dbConnectTemporary(
    bool $selectDb = true,
    int  $rocnik = ROCNIK,
         $stareSpojeni = null,
): PDO {
    $noveSpojeni = _dbConnect(
        DB_SERV,
        DB_USER,
        DB_PASS,
        defined('DB_PORT')
            ? DB_PORT
            : null,
        $selectDb
            ? DB_NAME
            : null,
        false,
    );
    if ($noveSpojeni && $stareSpojeni !== $noveSpojeni) {
        _nastavRocnikDoSpojeni($rocnik, $noveSpojeni, $selectDb);
    }

    return $noveSpojeni;
}

/**
 * @throws ConnectionException
 */
function dbConnect(
    $selectDb = true,
    bool $reconnect = false,
    int $rocnik = ROCNIK,
): PDO {
    if ($reconnect) {
        dbClose();
    }

    global $spojeni;

    if ($spojeni instanceof PDO) {
        return $spojeni;
    }

    // Try to share Doctrine's PDO connection when Symfony kernel is available
    if (isset($GLOBALS['systemoveNastaveni'])) {
        try {
            $kernel = $GLOBALS['systemoveNastaveni']->kernel();
            $container = $kernel->getContainer();
            /** @var \Doctrine\DBAL\Connection $dbalConnection */
            $dbalConnection = $container->get('doctrine.dbal.default_connection');
            $nativePdo = $dbalConnection->getNativeConnection();
            if ($nativePdo instanceof PDO) {
                // Ensure legacy-compatible settings on Doctrine's PDO
                $nativePdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
                $nativePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $spojeni = $nativePdo;
                _nastavRocnikDoSpojeni($rocnik, $spojeni, true);

                return $spojeni;
            }
        } catch (\Throwable) {
            // Kernel not available, fall through to standalone connection
        }
    }

    $stareSpojeni = $spojeni;
    try {
        $noveSpojeni = _dbConnect(
            DB_SERV,
            DB_USER,
            DB_PASS,
            defined('DB_PORT')
                ? DB_PORT
                : null,
            $selectDb
                ? DB_NAME
                : null,
        );
    } catch (Throwable $throwable) {
        $spojeni = null; // aby bylo možné zachytit exception a zkusit spojení znovu
        throw $throwable;
    }
    if ($noveSpojeni && $stareSpojeni !== $noveSpojeni) {
        _nastavRocnikDoSpojeni($rocnik, $noveSpojeni, $selectDb);
    }
    $spojeni = $noveSpojeni;

    return $noveSpojeni;
}

function _nastavRocnikDoSpojeni(
    int  $rocnik,
    PDO  $spojeni,
    bool $databaseSelected,
) {
    dbQuery('SET @rocnik = IF(@rocnik IS NOT NULL, @rocnik, $0)', $rocnik, $spojeni);
    if ($databaseSelected) {
        try {
            // pro SQL view, který nesnese variable
            dbQuery("UPDATE systemove_nastaveni SET hodnota = $0 WHERE klic = 'ROCNIK' AND hodnota != $0", $rocnik, $spojeni);
        } catch (Throwable $throwable) {
            if ((int)$throwable->getCode() !== 1146) {
                throw $throwable;
            } // else tabulka systemove_nastaveni zatím neexistuje
        }
    }
}

function dbClose()
{
    global $spojeni;

    $spojeni = null;
}

/**
 * @param bool $selectDb if database should be selected on connect or not
 * @throws ConnectionException
 */
function dbConnectForAlterStructure(
    $selectDb = true,
): PDO {
    return _dbConnect(
        DB_SERV,
        DBM_USER,
        DBM_PASS,
        defined('DB_PORT')
            ? constant('DB_PORT')
            : null,
        $selectDb
            ? DB_NAME
            : null,
    );
}

function dbConnectionAnonymDb(): PDO
{
    $connection = _dbConnect(
        DB_ANONYM_SERV,
        DB_ANONYM_USER,
        DB_ANONYM_PASS,
        defined('DB_ANONYM_PORT')
            ? (int)DB_ANONYM_PORT
            : null,
        null,
    );
    $dbAnonym   = DB_ANONYM_NAME;
    $result     = $connection->query("SHOW DATABASES LIKE '$dbAnonym'");
    $exists     = $result->fetchColumn();
    if ($exists) {
        $connection->exec("USE `$dbAnonym`");
    }

    return $connection;
}

/**
 * @throws ConnectionException
 */
function _dbConnect(
    string  $dbServer,
    string  $dbUser,
    string  $dbPass,
    ?int    $dbPort,
    ?string $dbName,
    bool    $persistent = true,
): PDO {
    $dsn = 'mysql:host=' . $dbServer;
    if ($dbPort) {
        $dsn .= ';port=' . $dbPort;
    }
    if ($dbName) {
        $dsn .= ';dbname=' . $dbName;
    }
    $dsn .= ';charset=utf8mb4';

    try {
        $pdo = new PDO(
            $dsn,
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT               => $persistent,
                PDO::MYSQL_ATTR_FOUND_ROWS         => true,  // makes rowCount() work for SELECT
                PDO::ATTR_STRINGIFY_FETCHES        => true,  // match mysqli behavior: all values as strings
                PDO::MYSQL_ATTR_MULTI_STATEMENTS   => true,  // enables multi-statement queries (needed for migrations)
            ],
        );
    } catch (\PDOException $e) {
        throw new ConnectionException(
            sprintf(
                "Failed to connect to the %s, error: '%s'",
                $dbName
                    ? "database '$dbName'"
                    : 'SQL server',
                $e->getMessage(),
            ),
            (int)$e->getCode(),
            $e,
        );
    }
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci');
    $pdo->exec('SET SESSION group_concat_max_len = 65536');

    return $pdo;
}

/**
 * Creates a raw mysqli connection for vendor libraries that require it
 * (e.g. dg/mysql-dump's MySQLImport).
 */
function dbConnectMysqli(
    string  $dbServer = DB_SERV,
    string  $dbUser = DB_USER,
    string  $dbPass = DB_PASS,
    ?int    $dbPort = null,
    ?string $dbName = null,
): \mysqli {
    $dbPort ??= defined('DB_PORT') ? DB_PORT : null;
    $dbName ??= DB_NAME;

    $connection = @mysqli_connect($dbServer, $dbUser, $dbPass, $dbName, $dbPort);
    if (!$connection) {
        throw new ConnectionException('Failed to create mysqli connection: ' . mysqli_connect_error());
    }
    $connection->query('SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci');

    return $connection;
}

function dbDisconnectOnShutdown(
    PDO $spojeni,
) {
    // PDO connections close automatically when the object is unset/garbage collected.
    // No shutdown handler needed.
}

/**
 * Deletes from $table where all $whereArray column => value conditions are met
 */
function dbDelete(
    $table,
    $whereArray,
) {
    $where = [];
    foreach ($whereArray as $col => $val) {
        $where[] = dbQi($col) . ' = ' . dbQv($val);
    }
    if (!$where) throw new Exception('DELETE … WHERE caluse must not be empty');
    dbQuery('DELETE FROM ' . dbQi($table) . ' WHERE ' . implode(' AND ', $where));
}

/**
 * Returns 2D array with table structure description
 */
function dbDescribe(
    $table,
) {
    $a   = dbQuery('show full columns from ' . dbQi($table));
    $out = [];
    while ($r = $a->fetch(PDO::FETCH_ASSOC)) {
        $out[] = $r;
    }

    return $out;
}

/**
 * Returns time spent in database
 */
function dbExecTime()
{
    return $GLOBALS['dbExecTime'] ?? 0.0;
}

function throwDbException(
    $spojeni = null,
) {
    // With PDO, errors are thrown as exceptions directly.
    // This function is kept for backward compat but should not be needed.
    throw new DbException('Database error');
}

/**
 * Returns instance of concrete DbException based on error code
 */
function dbGetExceptionType(
    $errorCode = null,
) {
    return match ((int)$errorCode) {
        1062    => DbDuplicateEntryException::class,
        1927    => DbConnectionKilledException::class,
        2006    => MysqlServerHasGoneAwayException::class,
        default => DbException::class,
    };
}

function dbCreateExceptionFromPdoException(
    PDOException $pdoException,
): DbException | DbDuplicateEntryException {
    // PDO error codes are strings like "23000" (SQLSTATE) but the driver-specific
    // code is in $pdoException->errorInfo[1] or we parse the message
    $driverCode = 0;
    if ($pdoException->errorInfo[1] ?? null) {
        $driverCode = (int)$pdoException->errorInfo[1];
    } elseif (is_numeric($pdoException->getCode())) {
        $driverCode = (int)$pdoException->getCode();
    }

    $exceptionClass = dbGetExceptionType($driverCode);

    return new $exceptionClass($pdoException->getMessage(), $driverCode, $pdoException);
}

/**
 * @deprecated Use dbCreateExceptionFromPdoException instead
 */
function dbCreateExceptionFromMysqliException(
    $exception,
): DbException | DbDuplicateEntryException {
    return dbCreateExceptionFromPdoException($exception);
}

/**
 * Returns error message from last query
 * @deprecated With PDO, errors are thrown as exceptions
 */
function dbGetExceptionMessage(
    $spojeni = null,
): string {
    return $GLOBALS['dbLastError'] ?? '';
}

/**
 * Inserts values from $valArray as (column => value) into $table
 * @throws DbDuplicateEntryException
 * @throws DbException
 */
function dbInsert(
    string $table,
    array  $valArray,
    bool   $ignore = false,
): void {
    $sloupce = '';
    $hodnoty = '';
    foreach ($valArray as $sloupec => $hodnota) {
        $sloupce .= dbQi($sloupec) . ',';
        $hodnoty .= dbQv($hodnota) . ',';
    }
    $sloupce   = substr($sloupce, 0, -1); //useknutí přebytečné čárky na konci
    $hodnoty   = substr($hodnoty, 0, -1);
    $ignoreSql = $ignore
        ? 'IGNORE'
        : '';
    $q         = "INSERT $ignoreSql INTO $table ($sloupce) VALUES ($hodnoty)";
    dbQuery($q);
}

/**
 * @param string $query
 * @throws DbDuplicateEntryException
 * @throws DbException
 * @internal
 */
function _dbPdoQuery(
    string $query,
    ?PDO $pdo = null,
): bool | PDOStatement {
    $pdo ??= dbConnect();
    try {
        // Determine if this is a data-affecting query (INSERT, UPDATE, DELETE, etc.)
        $trimmed = ltrim($query);
        $isSelect = stripos($trimmed, 'SELECT') === 0
            || stripos($trimmed, 'SHOW') === 0
            || stripos($trimmed, 'DESCRIBE') === 0
            || stripos($trimmed, 'EXPLAIN') === 0;

        if ($isSelect) {
            $stmt = $pdo->query($query);
            if ($stmt === false) {
                throw new DbException('Query failed: ' . $query);
            }
            return $stmt;
        }

        $affectedRows = $pdo->exec($query);
        if ($affectedRows === false) {
            throw new DbException('Query failed: ' . $query);
        }
        $GLOBALS['dbAffectedRows'] = $affectedRows;

        return true;
    } catch (PDOException $pdoException) {
        throw dbCreateExceptionFromPdoException($pdoException);
    }
}

/**
 * @deprecated Use _dbPdoQuery instead
 */
function _dbMysqliQuery(
    string $query,
    $pdo = null,
): bool | PDOStatement {
    return _dbPdoQuery($query, $pdo);
}

/**
 * @param string $table
 * @param array $valArray
 * @throws DbException
 */
function dbInsertIgnore(
    string $table,
    array  $valArray,
): void {
    dbInsert(table: $table, valArray: $valArray, ignore: true);
}

/**
 * @param string $tableName
 * @return string[][]
 * @throws DbException
 */
function getTableUniqueKeysColumns(
    string $tableName,
): array {
    static $primaryKeysColumns = [];
    if (!isset($primaryKeysColumns[$tableName])) {
        $uniqueKeysDetails = dbFetchAll(<<<SQL
SHOW INDEXES FROM `$tableName`
WHERE `Non_unique` = 0
SQL,
        );
        foreach ($uniqueKeysDetails as $uniqueKeyDetails) {
            // index can be combined from multiple columns
            $keyName                                    = $uniqueKeyDetails['Key_name'];
            $columnName                                 = $uniqueKeyDetails['Column_name'];
            $primaryKeysColumns[$tableName][$keyName][] = $columnName;
        }
    }

    return $primaryKeysColumns[$tableName];
}

/**
 * Return last AUTO INCREMENT value
 */
function dbInsertId(
    bool $strict = true,
) {
    global $dbLastQ;
    $id = (int)dbConnect()->lastInsertId();
    if ($strict && $id == 0) {
        throw new DbException("No last id. Last known query was '{$dbLastQ}'");
    }

    return $id;
}

function dbRecordExists(
    string $table,
    array  $values,
): bool {
    $sqlValuesArray = [];
    foreach ($values as $column => $value) {
        $sqlValuesArray[] = dbQi($column) . '=' . dbQv($value);
    }
    $sqlValues = implode(' AND ', $sqlValuesArray);

    return (bool)dbFetchSingle(<<<SQL
SELECT EXISTS(SELECT * FROM $table WHERE $sqlValues)
SQL,
    );
}

/**
 * Insert with actualisation
 * @return PDOStatement|bool|null
 * @throws DbException
 * @see dbInsert
 */
function dbInsertUpdate(
    string $table,
           $valArray,
) {
    $uniqueKeysColumns = getTableUniqueKeysColumns($table);
    if ($uniqueKeysColumns) {
        $completeUniqueKeyValues = [];
        foreach ($uniqueKeysColumns as $uniqueKeyColumns) {
            $uniqueKeyValues = array_intersect_key($valArray, array_fill_keys($uniqueKeyColumns, true));
            if (count($uniqueKeyValues) == count($uniqueKeyColumns)) {
                $completeUniqueKeyValues = array_merge($completeUniqueKeyValues, $uniqueKeyValues); // values for unique key are complete
            }
        }
        if ($completeUniqueKeyValues) {
            $query = dbUpdate($table, $valArray, $completeUniqueKeyValues);
            if (dbAffectedOrNumRows($query) > 0) {
                return $query;
            }
            if (dbRecordExists($table, $completeUniqueKeyValues)) {
                return $query; // no change
            }
        }
    }

    $update  = 'INSERT INTO ' . $table . ' SET ';
    $dupl    = ' ON DUPLICATE KEY UPDATE ';
    $sqlVals = [];
    foreach ($valArray as $key => $val) {
        $sqlVals[] = dbQi($key) . '=' . dbQv($val);
    }
    $vals = implode(',', $sqlVals);
    $q    = $update . $vals . $dupl . $vals;

    return dbQuery($q);
}

/**
 * Return last query
 */
function dbLastQ()
{
    global $dbLastQ;

    return $dbLastQ;
}

/**
 * If this is used as value in update then column value will be not changed.
 */
function dbNoChange()
{
    return new DbNoChange;
}

/**
 * Returns current time in databse compatible datetime format
 * @todo what about changing to 'now' (because of transactions and stuff)
 */
function dbNow(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Returns number of queries on this connection
 */
function dbNumQ(): int
{
    return (int)($GLOBALS['dbNumQ'] ?? 0);
}

/**
 * Returns number of queries on this connection
 * @return array<string, array{microtime: float, count: int}>
 */
function dbQueries(): array
{
    return (array)($GLOBALS['dbQueries'] ?? []);
}

/**
 * @param $query
 * @return int
 * @throws Exception
 * @deprecated
 * use @see dbAffectedOrNumRows instead
 */
function dbNumRows(
    $query,
): int {
    return dbAffectedOrNumRows($query);
}

/**
 * @return int of rows affected / returned by query
 * @throws Exception
 */
function dbAffectedOrNumRows(
    $query,
): int {
    if ($query === true) {
        // result of INSERT / UPDATE / DELETE (exec returned affected rows)
        return $GLOBALS['dbAffectedRows'] ?? 0;
    }
    if ($query instanceof PDOStatement) {
        // result of SELECT
        return $query->rowCount();
    }
    if ($query === null) {
        return 0;
    }
    throw new Exception('query failed or returned unexpected type');
}

/**
 * Expects one column in select. Returns array of selected values.
 */
function dbOneArray(
    $q,
    $p = null,
): array {
    $o = dbQuery($q, $p);
    $a = [];
    while ($r = $o->fetch(PDO::FETCH_NUM)) {
        $a[] = $r[0];
    }

    return $a;
}

/**
 * For selecting single-line one column value
 * @return scalar|null
 */
function dbOneCol(
    $q,
    array $p = null,
    $pdo = null,
) {
    $a = dbOneLine($q, $p, $pdo);

    return $a
        ? current($a)
        : null;
}

/**
 * Expects one column in select, returns array structured like: col value => true.
 */
function dbOneIndex(
    $q,
    $p = null,
): array {
    $o = dbQuery($q, $p);
    $a = [];
    while ($r = $o->fetch(PDO::FETCH_NUM)) {
        $a[$r[0]] = true;
    }

    return $a;
}

/**
 * Intended for selecting single lines from whatever. If no line found, returns
 * false, otherwise returns associative array with one line. If multiple lines
 * found, causes crash.
 */
function dbOneLine(
    $q,
    $p = null,
    $pdo = null,
): array {
    $r = dbQueryS($q, $p, $pdo);
    $rows = $r->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 1) {
        throw new RuntimeException('Multiple lines matched on query ' . $q);
    }
    if (count($rows) < 1) {
        return [];
    }

    return $rows[0];
}

function dbFetchRow(
    string $query,
    array  $params = [],
    $pdo = null,
): array {
    return dbOneLine($query, $params, $pdo);
}

/**
 * @param string $query
 * @param array $params
 * @param PDO|null $pdo
 * @return array<int, array<string, string>> rows with items indexed by column names
 * @throws DbException
 */
function dbFetchAll(
    string $query,
    array  $params = [],
    $pdo = null,
): array {
    $result = dbQuery($query, $params, $pdo);

    return $result->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @return array<int, string|int|null> single column values
 */
function dbFetchColumn(
    string $query,
    array  $params = [],
    $connection = null,
): array {
    $result = dbQuery($query, $params, $connection);

    return $result->fetchAll(PDO::FETCH_COLUMN);
}

function dbFetchPairs(
    string $query,
    array  $params = [],
    $connection = null,
): array {
    $result = dbQuery($query, $params, $connection);

    return $result->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * @return scalar|null
 */
function dbFetchSingle(
    string $query,
    array  $params = [],
): float | bool | int | string | null {
    $result = dbQuery($query, $params);
    $row    = $result->fetch(PDO::FETCH_NUM);
    if (!$row) {
        return null;
    }

    return $row[0];
}

/**
 * Executes arbitrary query on database
 * strings $1, $2, ... are replaced with values from $param
 * when $0 exists in, first $params maps to it, otherwise it maps to $1 etc...
 * @return bool|PDOStatement
 * @throws DbException|DbDuplicateEntryException
 */
function dbQuery(
    string $q,
           $param = null,
    ?PDO   $pdo = null,
): bool | PDOStatement {
    if ($param) {
        return dbQueryS($q, (array)$param, $pdo);
    }
    global $dbQueries, $dbLastQ, $dbNumQ, $dbExecTime, $systemoveNastaveni;
    $dbQueries ??= [];
    $pdo       ??= dbConnect();
    $dbLastQ   = $q;
    $start     = microtime(true);
    $r         = _dbPdoQuery($q, $pdo);
    // For data-affecting queries (INSERT/UPDATE/DELETE), dbAffectedRows is set inside _dbPdoQuery
    // For SELECT queries, we don't need affected rows
    $wasDataAffecting = $r === true;
    if (!$wasDataAffecting) {
        // For SELECT, store rowCount for compatibility
        $GLOBALS['dbAffectedRows'] = $r->rowCount();
    }
    $end                        = microtime(true);
    $dbQueries[$q]              ??= [
        'microtime' => 0.0,
        'count'     => 0,
    ];
    $dbQueries[$q]['microtime'] += $end - $start;
    $dbQueries[$q]['count']++;
    $dbNumQ++;
    $dbExecTime += $end - $start;
    if ($wasDataAffecting && ($GLOBALS['dbAffectedRows'] ?? 0) > 0) {
        $systemoveNastaveni?->db()->clearPrefetchedDataVersions();
    }

    return $r;
}

/**
 * Dotaz s nahrazováním jmen proměnných, pokud je nastaveno pole, tak jen z
 * pole ve forme $0 $1 atd resp $index
 */
function dbQueryS(
    string $q,
    array  $pole = null,
    ?PDO   $pdo = null,
): PDOStatement | bool {
    if (!$pole) {
        return dbQuery($q, null, $pdo);
    }

    return dbQuery(
        q: dbQueryAssemble($q, $pole, $pdo),
        pdo: $pdo,
    );
}

/**
 * Assembles a database query by replacing placeholders with escaped values.
 *
 * Supports two types of placeholders:
 * - ? (question mark): Sequential placeholders, replaced in order (0, 1, 2, ...)
 * - $N (dollar-sign numeric): Explicit index placeholders ($0, $1, $2, ...)
 *
 * Examples:
 *   dbQueryAssemble('SELECT * FROM users WHERE id = ?', [123])
 *   dbQueryAssemble('SELECT * FROM users WHERE id = ? AND name = ?', [123, 'John'])
 *   dbQueryAssemble('SELECT * FROM users WHERE id = $0', [123])
 *   dbQueryAssemble('UPDATE users SET name = $1 WHERE id = $0', [123, 'John'])
 *
 * Mixing placeholders:
 *   dbQueryAssemble('SELECT * FROM users WHERE id = ? AND status = $1', [123, 'active'])
 *   // ? consumes index 0, $1 refers to index 1
 *
 * @param string $q Query with placeholders
 * @param array $pole Parameter values
 * @param PDO|null $pdo Database connection for escaping
 * @return string Assembled query with values escaped
 * @throws DbException If not enough parameters provided
 */
function dbQueryAssemble(
    string $q,
    array  $pole,
    ?PDO   $pdo = null,
): string {
    // Phase 1: Replace ? placeholders sequentially
    $consumedParams = 0;
    $q = preg_replace_callback(
        '~\?~',
        function($matches) use ($pole, &$consumedParams, $pdo) {
            if ($consumedParams >= count($pole)) {
                throw new DbException(
                    "Not enough parameters: query has more ? placeholders than provided parameters"
                );
            }
            $value = dbQv($pole[$consumedParams], $pdo);
            $consumedParams++;
            return $value;
        },
        $q
    );

    // Phase 2: Replace $N placeholders (existing logic)
    $delta = array_key_exists(0, $pole) && !str_contains($q, '$0')
        ? -1
        : 0; // povolení číslování $1, $2, $3...

    return preg_replace_callback(
        '~\$(?<cislo_parametru>\d+)~',
        static function (
            array $matches,
        ) use
        (
            $pole,
            $delta,
            $pdo,
            $consumedParams,
        ) {
            $paramIndex = $matches['cislo_parametru'] + $delta;

            // When mixing placeholders, $N refers to params AFTER consumed ones
            if ($consumedParams > 0) {
                $paramIndex += $consumedParams;
            }

            if (!array_key_exists($paramIndex, $pole)) {
                throw new DbException(
                    "Parameter \${$matches['cislo_parametru']} (index $paramIndex) not found in provided parameters"
                );
            }
            return dbQv($pole[$paramIndex], $pdo);
        },
        $q,
    );
}

/**
 * Quotes array to be used in IN(1,2,3..N) queries
 * @example 'something IN('.dbQa($array).')'
 */
function dbQa(
    array $array,
    ?PDO  $pdo = null,
): string {
    if ($array === []) {
        return 'NULL';
    }
    $out = [];
    foreach ($array as $value) {
        $out[] = dbQv($value, $pdo);
    }

    return implode(',', $out);
}

/**
 * Quotes input values for DB. Nulls are passed as real NULLs, other values as
 * strings. Quotes $val as value
 */
function dbQv(
    $val,
    ?PDO $pdo = null,
): string {
    if (is_array($val)) {
        return dbQa($val, $pdo);
    }
    if ($val === null) {
        return 'NULL';
    }
    if (is_int($val) || (is_numeric($val) && (string)(int)$val === $val)) {
        return $val;
    }
    if ($val instanceof DateTimeInterface) {
        return '"' . $val->format('Y-m-d H:i:s') . '"';
    }
    if ($val instanceof BackedEnum) {
        $val = $val->value;
    }

    $pdo ??= dbConnect();

    // PDO::quote() adds surrounding quotes, but we use our own " quotes for consistency
    // Use substr to strip the surrounding ' quotes that PDO adds, then wrap in "
    $quoted = $pdo->quote((string)$val);
    // PDO::quote returns 'value' (with single quotes), we need "value" (double quotes)
    // Strip the surrounding ' and re-wrap with "
    $inner = substr($quoted, 1, -1);
    // Convert \' back to ' since we're using double quotes
    $inner = str_replace("\\'", "'", $inner);

    return '"' . $inner . '"';
}

function dbQRaw(
    $val,
): string {
    if (is_array($val)) {
        throw new LogicException(sprintf('Can not raw escape %s', var_export($val, true)));
    }
    if ($val === null) {
        return 'NULL';
    }
    if (is_int($val) || (is_numeric($val) && (string)(int)$val === $val)) {
        return $val;
    }
    if ($val instanceof DateTimeInterface) {
        return $val->format('Y-m-d H:i:s');
    }

    $pdo = dbConnect();
    $quoted = $pdo->quote((string)$val);
    // Strip surrounding quotes
    $inner = substr($quoted, 1, -1);
    $inner = str_replace("\\'", "'", $inner);

    return $inner;
}

/**
 * Quotes $val as identifier
 */
function dbQi(
    $val,
) {
    return '`' . str_replace('`', '``', $val) . '`';
}

/**
 * Executes update on table $table using associtive array $vals as column=>value
 * pairs and $where as column=>value AND column=>value ... where clause
 * @return bool|PDOStatement|null
 */
function dbUpdate(
    string $table,
    array  $vals,
    array  $where,
) {
    if ($vals === []) {
        return null;
    }
    $setArray = [];
    foreach ($vals as $key => $val) {
        if ($val instanceof DbNoChange) {
            continue;
        }
        $setArray[] = dbQi($key) . '=' . dbQv($val);
    }
    if (!$setArray) {
        return null;
    }
    $q = 'UPDATE ' . dbQi($table) . " SET \n" . implode(',', $setArray);

    $whereArray = [];
    foreach ($where as $k => $v) {
        $whereArray[] = dbQi($k) . '=' . dbQv($v);
    }
    if ($whereArray) {
        $q .= ' WHERE ' . implode("\n\tAND ", $whereArray);
    }

    return dbQuery($q);
}

/**
 * @return array<string>
 */
function dbParseUsedTables(
    string $query,
): array {
    /** https://dev.mysql.com/doc/refman/8.4/en/identifiers.html */
    preg_match_all('~(?:FROM|JOIN|INTO)\s+`?([a-zA-Z0-9$_]+)~i', $query, $matches);

    return array_unique($matches[1]);
}

class ConnectionException extends DbException
{

}

/**
 * Exception thrown when error is generated by database
 */
class DbException extends RuntimeException
{

    public function __construct(
        $message = null,
        int $code = null,
        Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? ($GLOBALS['dbLastError'] ?? 'Database error') . ' caused by ' . ($GLOBALS['dbLastQ'] ?? 'unknown query'),
            $code ?? 0,
            $previous,
        );
    }

}

class DbDuplicateEntryException extends DbException
{

    private $key;

    public function __construct(
        $message = null,
        int $code = null,
        Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        preg_match("@Duplicate entry '([^']*)' for key '([^']+)'@", $this->message, $m);
        $this->key = $m[2] ?? '';
    }

    public function key(): string
    {
        return $this->key;
    }

}

class DbConnectionKilledException extends DbException
{
}

class MysqlServerHasGoneAwayException extends DbException
{
}

class DbNoChange
{
}
