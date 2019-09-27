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
  while($r = mysqli_fetch_row($a)) {
    $o[$r[0]] = $r[1];
  }
  return $o;
}

/**
 * Begins transaction
 * @todo support fake nesting by savepoints
 */
function dbBegin() {
  if($GLOBALS['dbTransactionDepth'] == 0) {
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
  if($GLOBALS['dbTransactionDepth'] == 0) {
    throw new Exception('nothing to commit');
  } elseif($GLOBALS['dbTransactionDepth'] == 1) {
    dbQuery('COMMIT');
  } else {
    dbQuery('RELEASE SAVEPOINT nesttrans' . ($GLOBALS['dbTransactionDepth'] - 1));
  }
  $GLOBALS['dbTransactionDepth']--;
}

/**
 * @param bool $selectDb if database should be selected on connect or not
 */
function dbConnect($selectDb = true) {
  global $spojeni, $dbLastQ, $dbNumQ, $dbExecTime;

  if($spojeni === null) {
    // inicializace glob. nastavení
    $dbhost     = DB_SERV;
    $dbname     = DB_NAME;
    $dbuser     = DB_USER;
    $dbpass     = DB_PASS;
    $spojeni    = null;
    $dbLastQ    = '';   //vztahuje se pouze na dotaz v aktualnim skriptu
    $dbNumQ     = 0;    //počet dotazů do databáze
    $dbExecTime = 0.0;  //délka výpočtu dotazů

    // připojení
    $start = microtime(true);
    $spojeni = @mysqli_connect('p:' . $dbhost, $dbuser, $dbpass, $selectDb ? $dbname : ''); // persistent connection
    if(!$spojeni)
      throw new Exception('Failed to connect to the database, error: "' . mysqli_connect_error() . '".');
    if(!$spojeni->set_charset('utf8'))
      throw new Exception('Failed to set charset to db connection.');
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
  foreach($whereArray as $col => $val) {
    $where[] = dbQi($col).' = '.dbQv($val);
  }
  if(!$where) throw new Exception('DELETE … WHERE caluse must not be empty');
  dbQuery('DELETE FROM ' . dbQi($table) . ' WHERE ' . implode(' AND ', $where));
}

/**
 * Returns 2D array with table structure description
 */
function dbDescribe($table) {
  $a = dbQuery('show full columns from '.dbQi($table));
  $out = [];
  while($r = mysqli_fetch_assoc($a)) $out[] = $r;
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
  $keys = [
    1062 => 'DbDuplicateEntryException',
  ];
  if(isset($keys[mysqli_errno($GLOBALS['spojeni'])])) return $keys[mysqli_errno($GLOBALS['spojeni'])];
  else return 'DbException';
}

/**
 * Inserts values from $valArray as (column => value) into $table
 */
function dbInsert($table, $valArray) {
  global $spojeni, $dbLastQ;
  dbConnect();
  $sloupce='';
  $hodnoty='';
  foreach($valArray as $sloupec => $hodnota) {
    if($hodnota===NULL) {
      $sloupce.=$sloupec.',';
      $hodnoty.='NULL,';
    } else {
      $sloupce.=$sloupec.',';
      if(!get_magic_quotes_gpc()) //vstup není slashován
        $hodnota=addslashes($hodnota);
      $hodnoty.='"'.$hodnota.'",';
    }
  }
  $sloupce=substr($sloupce,0,-1); //useknutí přebytečné čárky na konci
  $hodnoty=substr($hodnoty,0,-1);
  $q='INSERT INTO '.$table.' ('.$sloupce.') VALUES ('.$hodnoty.')';
  $dbLastQ=$q;
  if(!mysqli_query($spojeni, $q)) { $type = dbGetExceptionType(); throw new $type(); }
}

/**
 * Return last AUTO INCREMENT value
 */
function dbInsertId(bool $strict = true) {
  $id = mysqli_insert_id($GLOBALS['spojeni']);
  if($strict && (!is_int($id) || $id == 0)) {
    throw new DbException('no last id');
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
  $update='INSERT INTO '.$table.' SET ';
  $dupl=' ON DUPLICATE KEY UPDATE ';
  $vals='';
  foreach($valArray as $key=>$val) {
    if($val===NULL)
      $vals .= $key.'=NULL, ';
    else
      $vals .= $key.'='.dbQv($val).', ';
  }
  $vals=substr($vals,0,-2); //odstranění čárky na konci
  $q=$update.$vals.$dupl.$vals;
  $dbLastQ=$q;
  $start=microtime(true);
  $r=mysqli_query($GLOBALS['spojeni'], $q);
  $end=microtime(true);
  if(!$r) { $type = dbGetExceptionType(); throw new $type(); }
}

/**
 * Return last query
 */
function dbLastQ() {
  global $dbLastQ;
  return $dbLastQ;
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
function dbNumRows($query) {
  if($query === true) {
    // result of mysqli_query INSERT / UPDATE / DELETE
    return $GLOBALS['spojeni']->affected_rows;
  } elseif($query instanceof mysqli_result) {
    // result of mysqli_query SELECT
    return $query->num_rows;
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
  while(list($v) = mysqli_fetch_row($o)) $a[] = $v;
  return $a;
}

/**
 * For selecting single-line one column values
 */
function dbOneCol($q, $p = null) {
  $a = dbOneLine($q, $p);
  return $a ? current($a) : null;
}

/**
 * Expects one column in select, returns array structured like: col value => true.
 */
function dbOneIndex($q, $p = null) {
  $o = dbQuery($q, $p);
  $a = [];
  while(list($v) = mysqli_fetch_row($o)) $a[$v] = true;
  return $a;
}

/**
 * Intended for selecting single lines from whatever. If no line found, returns
 * false, otherwise returns asociative array with one line. If multiple lines
 * found, causes crash.
 */
function dbOneLine($q, $p = null) {
  $r = dbQueryS($q, $p);
  if(mysqli_num_rows($r)>1) die('multiple lines matched!');
  elseif(mysqli_num_rows($r)<1) return FALSE;
  else return mysqli_fetch_assoc($r);
}

/**
 * Single line selector with substitution
 * @see dbQueryS
 * @deprecated in favour of dbOneLine
 */
function dbOneLineS($q,$array=null)
{
  $r=dbQueryS($q,$array);
  if(mysqli_num_rows($r)>1) die('multiple lines matched!');
  elseif(mysqli_num_rows($r)<1) return FALSE;
  else return mysqli_fetch_assoc($r);
}

/**
 * Executes arbitrary query on database
 * strings $1, $2, ... are replaced with values from $param
 * when $0 exists in, first $params maps to it, otherwise it maps to $1 etc...
 */
function dbQuery($q, $param = null) {
  if($param) return dbQueryS($q, $param);
  dbConnect();
  $GLOBALS['dbLastQ'] = $q;
  $start = microtime(true);
  $r = mysqli_query($GLOBALS['spojeni'], $q);
  $end = microtime(true);
  if(!$r) throw new DbException();
  $GLOBALS['dbNumQ']++;
  $GLOBALS['dbExecTime'] += $end - $start;
  return $r;
}

/**
 * Dotaz s nahrazováním jmen proměnných, pokud je nastaveno pole, tak jen z
 * pole ve forme $0 $1 atd resp $index
 * @deprecated in favour of dbQuery
 */
function dbQueryS($q,$pole=null)
{
  if(isset($pole) && is_array($pole))
  {
    $delta = strpos($q, '$0')===false ? -1 : 0; // povolení číslování $1, $2, $3...
    return dbQuery(
      preg_replace_callback('~\$([0-9]+)~', function($m)use($pole,$delta){
        return dbQv($pole[ $m[1] + $delta ]);
      },$q)
    );
  }
  else
  {
    return dbQuery(
      preg_replace_callback('~\$([a-zA-Z]+)~', function($m){
        // TODO smazat pokud se neprojeví
        throw new Exception('nahrazování globálními proměnnými je deprecated');
        return '"'.addslashes($GLOBALS[$m[1]]).'"';
      },$q)
    );
  }
}

/**
 * Quotes array to be used in IN(1,2,3..N) queries
 * @example 'something IN('.dbQa($array).')'
 */
function dbQa($array) {
  $out = '';
  foreach($array as $v)
    $out .= dbQv($v).',';
  return substr($out, 0, -1);
}

/**
 * Quotes input values for DB. Nulls are passed as real NULLs, other values as
 * strings. Quotes $val as value
 */
function dbQv($val) {
  if(is_array($val))
    return implode(',', array_map(function($val){ return dbQv($val); }, $val));
  elseif($val === null)
    return 'NULL';
  elseif(is_int($val))
    return $val;
  elseif($val instanceof DateTimeInterface)
    return '"'.$val->format('Y-m-d H:i:s').'"';
  else
    return '"'.( get_magic_quotes_gpc() ? $val : addslashes($val) ).'"';
}

/**
 * Quotes $val as identifier
 */
function dbQi($val) {
  return '`'.( get_magic_quotes_gpc() ? $val : addslashes($val) ).'`';
}

/**
 *
 */
function dbRollback() {
  if($GLOBALS['dbTransactionDepth'] == 0) {
    throw new Exception('nothing to rollback');
  } elseif($GLOBALS['dbTransactionDepth'] == 1) {
    dbQuery('ROLLBACK');
  } else {
    dbQuery('ROLLBACK TO SAVEPOINT nesttrans' . ($GLOBALS['dbTransactionDepth'] - 1));
  }
  $GLOBALS['dbTransactionDepth']--;
}

/**
 * Executes update on table $table using associtive array $vals as column=>value
 * pairs and $where as column=>value AND column=>value ... where clause
 */
function dbUpdate($table, $vals, $where) {
  global $dbspojeni, $dbLastQ;
  dbConnect();
  $q='UPDATE '.dbQi($table)." SET \n";
  foreach($vals as $key=>$val)
    $q.=( dbQi($key).'='.dbQv($val).",\n" );
  $q=substr($q,0,-2)."\n"; //odstranění čárky na konci
  // where klauzule
  $q .= 'WHERE 1';
  foreach($where as $k => $v) {
    $q .= ' AND '.dbQi($k).' = '.dbQv($v);
  }
  // query execution
  $dbLastQ=$q;
  $start=microtime(true);
  $r=mysqli_query($GLOBALS['spojeni'], $q);
  $end=microtime(true);
  if(!$r) { $type = dbGetExceptionType(); throw new $type(); };
}

/**
 * Exception thrown when error is generated by database
 */
class DbException extends Exception {

  function __construct() {
    $this->message = mysqli_error($GLOBALS['spojeni']);
  }

}

class DbDuplicateEntryException extends DbException {

  private $key;

  function __construct() {
    parent::__construct();
    preg_match("@Duplicate entry '([^']+)' for key '([^']+)'@", $this->message, $m);
    $this->key = $m[2];
  }

  function key() {
    return $this->key;
  }

}
