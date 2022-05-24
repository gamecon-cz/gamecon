<?php

/**
 * Global variables used by certain functions
 * not all of them, see also dbConnect()
 */
$dbTransactionDepth = 0;

/**
 * Load one column into array in $id => $value manner
 */
function dbArrayCol($q, $param = null) {
    $a = dbQueryS($q, $param);
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
 * @param bool $selectDb if database should be selected on connect or not
 */
function dbConnect($selectDb = true) {
    global $spojeni, $dbLastQ, $dbNumQ, $dbExecTime;

    if ($spojeni === null) {
        // inicializace glob. nastavení
        $dbhost = DB_SERV;
        $dbname = DB_NAME;
        $dbuser = DB_USER;
        $dbpass = DB_PASS;
        $dbPort = defined('DB_PORT') ? DB_PORT : null;
        $spojeni = null;
        $dbLastQ = '';   //vztahuje se pouze na dotaz v aktualnim skriptu
        $dbNumQ = 0;    //počet dotazů do databáze
        $dbExecTime = 0.0;  //délka výpočtu dotazů

        // připojení
        $start = microtime(true);
        $spojeni = @mysqli_connect('p:' . $dbhost, $dbuser, $dbpass, $selectDb ? $dbname : '', $dbPort); // persistent connection
        if (!$spojeni) {
            $spojeni = null; // aby bylo možné zachytit exception a zkusit spojení znovu
            throw new ConnectionException('Failed to connect to the database, error: "' . mysqli_connect_error() . '".');
        }
        if (!$spojeni->set_charset('utf8')) {
            throw new Exception('Failed to set charset to db connection.');
        }
        $end = microtime(true);
        $GLOBALS['dbExecTime'] += $end - $start;
        dbQuery('SET SESSION group_concat_max_len = 65536');
    }

    return $spojeni;
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
    $a = dbQuery('show full columns from ' . dbQi($table));
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

/**
 * Returns instance of concrete DbException based on error message
 */
function dbGetExceptionType() {
    if (mysqli_errno($GLOBALS['spojeni']) === 1062) {
        return DbDuplicateEntryException::class;
    }
    return DbException::class;
}

/**
 * Inserts values from $valArray as (column => value) into $table
 */
function dbInsert($table, $valArray) {
    global $spojeni, $dbLastQ;
    dbConnect();
    $sloupce = '';
    $hodnoty = '';
    foreach ($valArray as $sloupec => $hodnota) {
        $sloupce .= dbQi($sloupec) . ',';
        $hodnoty .= dbQv($hodnota) . ',';
    }
    $sloupce = substr($sloupce, 0, -1); //useknutí přebytečné čárky na konci
    $hodnoty = substr($hodnoty, 0, -1);
    $q = 'INSERT INTO ' . $table . ' (' . $sloupce . ') VALUES (' . $hodnoty . ')';
    $dbLastQ = $q;
    if (!mysqli_query($spojeni, $q)) {
        $type = dbGetExceptionType();
        throw new $type();
    }
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

/**
 * Insert with actualisation
 * @see dbInsert
 */
function dbInsertUpdate($table, $valArray) {
    global $dbspojeni, $dbLastQ;
    dbConnect();
    $update = 'INSERT INTO ' . $table . ' SET ';
    $dupl = ' ON DUPLICATE KEY UPDATE ';
    $vals = '';
    foreach ($valArray as $key => $val) {
        $vals .= $key . '=' . dbQv($val) . ', ';
    }
    $vals = substr($vals, 0, -2); //odstranění čárky na konci
    $q = $update . $vals . $dupl . $vals;
    $dbLastQ = $q;
    $start = microtime(true);
    $r = mysqli_query($GLOBALS['spojeni'], $q);
    $end = microtime(true);
    if (!$r) {
        $type = dbGetExceptionType();
        throw new $type();
    }
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
        return $GLOBALS['spojeni']->affected_rows ?? 0;
    } elseif ($query instanceof mysqli_result) {
        // result of mysqli_query SELECT
        return $query->num_rows ?? 0;
    } else {
        throw new Exception('query failed or returned unexpected type');
    }
}

/**
 * Expects one column in select. Returns array of selected values.
 */
function dbOneArray($q, $p = null) {
    $o = dbQuery($q, $p);
    $a = [];
    while (list($v) = mysqli_fetch_row($o)) $a[] = $v;
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
    while (list($v) = mysqli_fetch_row($o)) $a[$v] = true;
    return $a;
}

/**
 * Intended for selecting single lines from whatever. If no line found, returns
 * false, otherwise returns asociative array with one line. If multiple lines
 * found, causes crash.
 */
function dbOneLine($q, $p = null) {
    $r = dbQueryS($q, $p);
    if (mysqli_num_rows($r) > 1) {
        throw new RuntimeException('Multiple lines matched on query ' . $q);
    }
    if (mysqli_num_rows($r) < 1) {
        return FALSE;
    }
    return mysqli_fetch_assoc($r);
}

/**
 * @param string $query
 * @param array $params
 * @return array
 * @throws DbException
 */
function dbFetchAll(string $query, array $params = []): array {
    $result = dbQuery($query, $params);
    $resultAsArray = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $resultAsArray[] = $row;
    }
    return $resultAsArray;
}

/**
 * Executes arbitrary query on database
 * strings $1, $2, ... are replaced with values from $param
 * when $0 exists in, first $params maps to it, otherwise it maps to $1 etc...
 */
function dbQuery($q, $param = null) {
    if ($param) {
        return dbQueryS($q, $param);
    }
    dbConnect();
    $GLOBALS['dbLastQ'] = $q;
    $start = microtime(true);
    $r = mysqli_query($GLOBALS['spojeni'], $q);
    $end = microtime(true);
    if (!$r) {
        throw new DbException();
    }
    $GLOBALS['dbNumQ']++;
    $GLOBALS['dbExecTime'] += $end - $start;
    return $r;
}

/**
 * Dotaz s nahrazováním jmen proměnných, pokud je nastaveno pole, tak jen z
 * pole ve forme $0 $1 atd resp $index
 */
function dbQueryS($q, array $pole = null) {
    if (!$pole) {
        return dbQuery($q);
    }
    $delta = !str_contains($q, '$0')
        ? -1
        : 0; // povolení číslování $1, $2, $3...
    return dbQuery(
        preg_replace_callback(
            '~\$(\d+)~',
            static function (array $matches) use ($pole, $delta) {
                return dbQv($pole[$matches[1] + $delta]);
            },
            $q
        )
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
    if (is_int($val)) {
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
 */
function dbUpdate($table, $vals, $where) {
    global $dbspojeni, $dbLastQ;

    if ($vals === []) return;

    dbConnect();
    $q = 'UPDATE ' . dbQi($table) . " SET \n";
    foreach ($vals as $key => $val) {
        if ($val instanceof DbNoChange) continue;
        $q .= (dbQi($key) . '=' . dbQv($val) . ",\n");
    }
    $q = substr($q, 0, -2) . "\n"; //odstranění čárky na konci
    // where klauzule
    $q .= 'WHERE 1';
    foreach ($where as $k => $v) {
        $q .= ' AND ' . dbQi($k) . ' = ' . dbQv($v);
    }
    // query execution
    $dbLastQ = $q;
    $start = microtime(true);
    $r = mysqli_query($GLOBALS['spojeni'], $q);
    $end = microtime(true);
    if (!$r) {
        $type = dbGetExceptionType();
        throw new $type();
    };
    return $r;
}

class ConnectionException extends RuntimeException
{

}

/**
 * Exception thrown when error is generated by database
 */
class DbException extends Exception
{

    public function __construct($message = null) {
        parent::__construct(($message ?? '') . ' ' . mysqli_error($GLOBALS['spojeni']) . ' caused by ' . $GLOBALS['dbLastQ'], mysqli_errno($GLOBALS['spojeni']));
    }

}

class DbDuplicateEntryException extends DbException
{

    private $key;

    public function __construct() {
        parent::__construct();
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
