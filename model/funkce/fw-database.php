<?php

/**
 * Global variables used by certain functions
 * not all of them, see also dbConnect()
 */
$dbTransactionDepth = 0;

/**
 * Load one column into array in $id => $value manner
 */
function dbArrayCol($q, $param = null, mysqli $mysqli = null) {
    $a = dbQueryS($q, $param, $mysqli);
    $o = [];
    while ($r = mysqli_fetch_row($a)) {
        $o[$r[0]] = $r[1];
    }
    return $o;
}

/**
 * Begins transaction
 * @todo support fake nesting by savepoints
 */
function dbBegin() {
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
function dbCommit() {
    if ($GLOBALS['dbTransactionDepth'] == 0) {
        throw new Exception('nothing to commit');
    }
    if ($GLOBALS['dbTransactionDepth'] == 1) {
        dbQuery('COMMIT');
    } else {
        dbQuery('RELEASE SAVEPOINT nesttrans' . ($GLOBALS['dbTransactionDepth'] - 1));
    }
    $GLOBALS['dbTransactionDepth']--;
}

/**
 * Commits transaction
 */
function dbRollback() {
    if ($GLOBALS['dbTransactionDepth'] == 0) {
        return;
    }
    if ($GLOBALS['dbTransactionDepth'] == 1) {
        dbQuery('ROLLBACK');
    } else {
        dbQuery('ROLLBACK TO SAVEPOINT nesttrans' . ($GLOBALS['dbTransactionDepth'] - 1));
    }
    $GLOBALS['dbTransactionDepth']--;
}

/**
 * @throws ConnectionException
 */
function dbConnect($selectDb = true, bool $reconnect = false, int $rocnik = ROCNIK): \mysqli {
    if ($reconnect) {
        dbClose();
    }

    global $spojeni;

    if ($spojeni instanceof mysqli) {
        return $spojeni;
    }

    $stareSpojeni = $spojeni;
    $noveSpojeni  = _dbConnect(
        DB_SERV,
        DB_USER,
        DB_PASS,
        defined('DB_PORT') ? DB_PORT : null,
        $selectDb ? DB_NAME : null,
        $reconnect
    );
    if ($noveSpojeni && $stareSpojeni !== $noveSpojeni) {
        dbQuery('SET @rocnik = IF(@rocnik IS NOT NULL, @rocnik, $0)', $rocnik, $noveSpojeni);
        if ($selectDb) {
            // pro SQL view, který nesnese variable
            dbQuery("UPDATE systemove_nastaveni SET hodnota = $0 WHERE klic = 'ROCNIK'", $rocnik, $noveSpojeni);
        }
    }
    $spojeni = $noveSpojeni;

    return $noveSpojeni;
}

function dbClose() {
    global $spojeni;

    if ($spojeni) {
        mysqli_close($spojeni);
    }
    $spojeni = null;
}

/**
 * @param bool $selectDb if database should be selected on connect or not
 * @throws ConnectionException
 */
function dbConnectForAlterStructure($selectDb = true) {
    return _dbConnect(
        DBM_SERV,
        DBM_USER,
        DBM_PASS,
        defined('DBM_PORT') ? DBM_PORT : null,
        $selectDb ? DBM_NAME : null
    );
}

function dbConnectionAnonymDb(): mysqli {
    $connection = _dbConnect(
        DB_ANONYM_SERV,
        DB_ANONYM_USER,
        DB_ANONYM_PASS,
        defined('DB_ANONYM_PORT') ? (int)DB_ANONYM_PORT : null,
        null
    );
    $dbAnonym   = DB_ANONYM_NAME;
    $result     = mysqli_query(
        $connection,
        <<<SQL
            SHOW DATABASES LIKE '$dbAnonym'
        SQL
    );
    $exists     = mysqli_fetch_column($result);
    if ($exists) {
        mysqli_query(
            $connection,
            <<<SQL
            USE `$dbAnonym`
        SQL
        );
    }
    return $connection;
}

/**
 * @param bool $selectDb if database should be selected on connect or not
 * @throws ConnectionException
 */
function _dbConnect(string $dbHost, string $dbUser, string $dbPass, ?int $dbPort, ?string $dbName, bool $reconnect = false) {
    dbDisconnectOnShutdown();

    try {
        // persistent connection
        $spojeni = @mysqli_connect('p:' . $dbHost, $dbUser, $dbPass, $dbName ?? '', $dbPort);
    } catch (\Throwable $throwable) {
        throw new ConnectionException(
            "Failed to connect to the database, error: '{$throwable->getMessage()}'",
            $throwable->getCode(),
            $throwable
        );
    }
    if (!$spojeni) {
        $spojeni = null; // aby bylo možné zachytit exception a zkusit spojení znovu
        throw new ConnectionException('Failed to connect to the database, error: "' . mysqli_connect_error() . '".');
    }
    if (!$spojeni->set_charset('utf8')) {
        throw new DbException('Failed to set charset utf8 to db connection.');
    }
    dbQuery('SET SESSION group_concat_max_len = 65536', null, $spojeni);

    return $spojeni;
}

function dbDisconnectOnShutdown() {
    if (defined('DB_DISCONNECT_ON_SHUTDOWN_REGISTERED')) {
        return;
    }
    register_shutdown_function(static function () {
        global $spojeni;
        if ($spojeni && (mysqli_get_connection_stats($spojeni)['active_connections'] ?? false)) {
            mysqli_close($spojeni);
            $spojeni = null;
        }
    });
    define('DB_DISCONNECT_ON_SHUTDOWN_REGISTERED', true);
}

/**
 * Deletes from $table where all $whereArray column => value conditions are met
 */
function dbDelete($table, $whereArray) {
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
function dbDescribe($table) {
    $a   = dbQuery('show full columns from ' . dbQi($table));
    $out = [];
    while ($r = mysqli_fetch_assoc($a)) $out[] = $r;
    return $out;
}

/**
 * Returns time spent in database
 */
function dbExecTime() {
    return isset($GLOBALS['dbExecTime']) ? $GLOBALS['dbExecTime'] : 0.0;
}

function throwDbException($spojeni = null) {
    $type    = dbGetExceptionType($spojeni);
    $message = dbGetExceptionMessage($spojeni);
    throw new $type($message);
}

/**
 * Returns instance of concrete DbException based on error message
 */
function dbGetExceptionType($spojeni = null) {
    if (mysqli_errno($spojeni ?? $GLOBALS['spojeni']) === 1062) {
        return DbDuplicateEntryException::class;
    }
    return DbException::class;
}

function dbCreateExceptionFromMysqliException(mysqli_sql_exception $mysqliException): DbException|DbDuplicateEntryException {
    $exceptionClass = $mysqliException->getCode() === 1062
        ? DbDuplicateEntryException::class
        : DbException::class;
    return new $exceptionClass($mysqliException->getMessage(), $mysqliException->getCode(), $mysqliException);
}

/**
 * Returns instance of concrete DbException based on error message
 */
function dbGetExceptionMessage($spojeni = null) {
    return mysqli_error($spojeni ?? $GLOBALS['spojeni']);
}

/**
 * Inserts values from $valArray as (column => value) into $table
 * @throws DbDuplicateEntryException
 * @throws DbException
 */
function dbInsert($table, $valArray, bool $ignore = false) {
    global $dbLastQ;
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
    $dbLastQ   = $q;
    dbMysqliQuery($q);
}

/**
 * @param string $query
 * @throws DbDuplicateEntryException
 * @throws DbException
 */
function dbMysqliQuery(string $query, mysqli $mysqli = null): bool|mysqli_result {
    try {
        if (!$r = mysqli_query($mysqli ?? dbConnect(), $query)) {
            $type = dbGetExceptionType();
            throw new $type();
        }
        return $r;
    } catch (mysqli_sql_exception $mysqliException) {
        throw dbCreateExceptionFromMysqliException($mysqliException);
    }
}

/**
 * @param string $table
 * @param array $valArray
 * @throws DbException
 */
function dbInsertIgnore(string $table, array $valArray) {
    dbInsert($table, $valArray, true);
}

/**
 * @param string $tableName
 * @return string[][]
 * @throws DbException
 */
function getTableUniqueKeysColumns(string $tableName): array {
    static $primaryKeysColumns = [];
    if (!isset($primaryKeysColumns[$tableName])) {
        $uniqueKeysDetails = dbFetchAll(<<<SQL
SHOW INDEXES FROM `$tableName`
WHERE `Non_unique` = 0
SQL
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
function dbInsertId(bool $strict = true) {
    global $dbLastQ;
    $id = mysqli_insert_id($GLOBALS['spojeni']);
    if ($strict && (!is_int($id) || $id == 0)) {
        throw new DbException("No last id. Last known query was '{$dbLastQ}'");
    }
    return $id;
}

function dbRecordExists(string $table, array $values): bool {
    $sqlValuesArray = [];
    foreach ($values as $column => $value) {
        $sqlValuesArray[] = dbQi($column) . '=' . dbQv($value);
    }
    $sqlValues = implode(' AND ', $sqlValuesArray);
    return (bool)dbFetchSingle(<<<SQL
SELECT EXISTS(SELECT * FROM $table WHERE $sqlValues)
SQL
    );
}

/**
 * Insert with actualisation
 * @see dbInsert
 * @return mysqli|bool
 * @throws DbException
 */
function dbInsertUpdate($table, $valArray) {
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
            if (dbNumRows($query) > 0) {
                return $query;
            }
            if (dbRecordExists($table, $completeUniqueKeyValues)) {
                return $query; // no change
            }
        }
    }

    global $dbLastQ;

    $update  = 'INSERT INTO ' . $table . ' SET ';
    $dupl    = ' ON DUPLICATE KEY UPDATE ';
    $sqlVals = [];
    foreach ($valArray as $key => $val) {
        $sqlVals[] = dbQi($key) . '=' . dbQv($val);
    }
    $vals    = implode(',', $sqlVals);
    $q       = $update . $vals . $dupl . $vals;
    $dbLastQ = $q;
    return dbMysqliQuery($q);
}

/**
 * Return last query
 */
function dbLastQ() {
    global $dbLastQ;
    return $dbLastQ;
}

/**
 * If this is used as value in update then column value will be not changed.
 */
function dbNoChange() {
    return new DbNoChange;
}

/**
 * Returns current time in databse compatible datetime format
 * @todo what about changing to 'now' (because of transactions and stuff)
 */
function dbNow() {
    return date('Y-m-d H:i:s');
}

/**
 * Returns number of queries on this connection
 */
function dbNumQ() {
    return isset($GLOBALS['dbNumQ']) ? $GLOBALS['dbNumQ'] : 0;
}

/**
 * @return number of rows affected / returned by query
 */
function dbNumRows($query): int {
    if ($query === true) {
        // result of mysqli_query INSERT / UPDATE / DELETE
        return $GLOBALS['dbAffectedRows'] ?? 0;
    }
    if ($query instanceof mysqli_result) {
        // result of mysqli_query SELECT
        return $query->num_rows ?? 0;
    }
    throw new Exception('query failed or returned unexpected type');
}

/**
 * Expects one column in select. Returns array of selected values.
 */
function dbOneArray($q, $p = null) {
    $o = dbQuery($q, $p);
    $a = [];
    while (list($v) = mysqli_fetch_row($o)) {
        $a[] = $v;
    }
    return $a;
}

/**
 * For selecting single-line one column value
 */
function dbOneCol($q, array $p = null) {
    $a = dbOneLine($q, $p);
    return $a ? current($a) : null;
}

/**
 * Expects one column in select, returns array structured like: col value => true.
 */
function dbOneIndex($q, $p = null) {
    $o = dbQuery($q, $p);
    $a = [];
    while (list($v) = mysqli_fetch_row($o)) {
        $a[$v] = true;
    }
    return $a;
}

/**
 * Intended for selecting single lines from whatever. If no line found, returns
 * false, otherwise returns associative array with one line. If multiple lines
 * found, causes crash.
 */
function dbOneLine($q, $p = null, mysqli $mysqli = null): array {
    $r = dbQueryS($q, $p);
    if (mysqli_num_rows($r) > 1) {
        throw new RuntimeException('Multiple lines matched on query ' . $q);
    }
    if (mysqli_num_rows($r) < 1) {
        return [];
    }
    return mysqli_fetch_assoc($r) ?: [];
}

function dbFetchRow(string $query, array $params = [], mysqli $mysqli = null): array {
    return dbOneLine($query, $params, $mysqli);
}

/**
 * @param string $query
 * @param array $params
 * @param mysqli|null $mysqli
 * @return array
 * @throws DbException
 */
function dbFetchAll(string $query, array $params = [], mysqli $mysqli = null): array {
    $result        = dbQuery($query, $params, $mysqli);
    $resultAsArray = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $resultAsArray[] = $row;
    }
    return $resultAsArray;
}

function dbFetchColumn(string $query, array $params = [], mysqli $connection = null): array {
    $result       = dbQuery($query, $params, $connection);
    $columnValues = [];
    while ($row = mysqli_fetch_array($result)) {
        $columnValues[] = reset($row);
    }
    return $columnValues;
}

function dbFetchPairs(string $query, array $params = [], mysqli $connection = null): array {
    $result = dbQuery($query, $params, $connection);
    $pairs  = [];
    while ($row = mysqli_fetch_array($result)) {
        $pairs[$row[0]] = $row[1];
    }
    return $pairs;
}

function dbFetchSingle(string $query, array $params = []) {
    $result = dbQuery($query, $params);
    $row    = mysqli_fetch_array($result);
    return reset($row);
}

/**
 * Executes arbitrary query on database
 * strings $1, $2, ... are replaced with values from $param
 * when $0 exists in, first $params maps to it, otherwise it maps to $1 etc...
 * @return bool|mysqli_result
 * @throws DbException|DbDuplicateEntryException
 */
function dbQuery($q, $param = null, mysqli $mysqli = null): bool|mysqli_result {
    if ($param) {
        return dbQueryS($q, (array)$param, $mysqli);
    }
    global $dbLastQ, $dbNumQ, $dbExecTime;
    $mysqli  = $mysqli ?? dbConnect();
    $dbLastQ = $q;
    $start   = microtime(true);
    $r       = dbMysqliQuery($q, $mysqli);
    if (!$r) {
        $type = dbGetExceptionType();
        throw new $type();
    }
    // raději si to hned odložíme, protože opakovaný dotaz na mysqli->affected_rows vede k tomu, že první dotaz vrátí správnou hodnotu, ale druhý už -1 ("disk se automaticky zničí po přečtení za pět, čtyři, tři...")
    $GLOBALS['dbAffectedRows'] = $r === true // INSERT, DELETE, UPDATE
        ? $mysqli->affected_rows
        : mysqli_affected_rows($mysqli);
    $end                       = microtime(true);
    $dbNumQ++;
    $dbExecTime += $end - $start;
    return $r;
}

/**
 * Dotaz s nahrazováním jmen proměnných, pokud je nastaveno pole, tak jen z
 * pole ve forme $0 $1 atd resp $index
 */
function dbQueryS($q, array $pole = null, mysqli $mysqli = null) {
    if (!$pole) {
        return dbQuery($q, null, $mysqli);
    }
    $delta = !str_contains($q, '$0')
        ? -1
        : 0; // povolení číslování $1, $2, $3...
    return dbQuery(
        preg_replace_callback(
            '~\$(?<cislo_parametru>\d+)~',
            static function (array $matches) use ($pole, $delta) {
                return dbQv($pole[$matches['cislo_parametru'] + $delta]);
            },
            $q
        ),
        null,
        $mysqli
    );
}

/**
 * Quotes array to be used in IN(1,2,3..N) queries
 * @example 'something IN('.dbQa($array).')'
 */
function dbQa(array $array): string {
    if (count($array) === 0) {
        return 'NULL';
    }
    $out = [];
    foreach ($array as $value) {
        $out[] = dbQv($value);
    }
    return implode(',', $out);
}

/**
 * Quotes input values for DB. Nulls are passed as real NULLs, other values as
 * strings. Quotes $val as value
 */
function dbQv($val): string {
    if (is_array($val)) {
        return implode(',', array_map('dbQv', $val));
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
    return '"' . mysqli_real_escape_string(dbConnect(), $val) . '"';
}

/**
 * Quotes $val as identifier
 */
function dbQi($val) {
    return '`' . mysqli_real_escape_string(dbConnect(), $val) . '`';
}

/**
 * Executes update on table $table using associtive array $vals as column=>value
 * pairs and $where as column=>value AND column=>value ... where clause
 * @return bool|mysqli
 */
function dbUpdate(string $table, array $vals, array $where) {
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
    $r = dbQuery($q);
    if (!$r) {
        $type = dbGetExceptionType();
        throw new $type();
    }
    return $r;
}

class ConnectionException extends DbException
{

}

/**
 * Exception thrown when error is generated by database
 */
class DbException extends RuntimeException
{

    public function __construct($message = null, int $code = null, Throwable $previous = null) {
        parent::__construct(
            $message ?? (mysqli_error($GLOBALS['spojeni']) . ' caused by ' . $GLOBALS['dbLastQ']),
            $code ?? mysqli_errno($GLOBALS['spojeni']),
            $previous
        );
    }

}

class DbDuplicateEntryException extends DbException
{

    private $key;

    public function __construct($message = null, int $code = null, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        preg_match("@Duplicate entry '([^']*)' for key '([^']+)'@", $this->message, $m);
        $this->key = $m[2] ?? '';
    }

    public function key(): string {
        return $this->key;
    }

}

class DbNoChange
{
}
