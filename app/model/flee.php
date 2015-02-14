<?php

/**
 * Simple database migration
 * depends on dg/mysql-dump
 */
class Flee {

  private
    $autorollback = false,
    $db,
    $folderMigration,
    $folderBackup,
    $settings,
    $strategy = self::DB_VARS_TABLE;

  const
    DB_VARS_TABLE = 1,
    POST_VAR = 'cFleeVar'; // name of post variable for prompts

  /**
   * Needed:
   *  database access (host, dbname, pass, user or some connection)
   *  migration folder
   *  backup folder
   *  ...?
   */
  function __construct($a) {
    $this->folderMigration = $a['migrationFolder'];
    $this->folderBackup = $a['backupFolder'];
    $this->settings = $a;
  }

  /**
   * Applies given migration file to database
   */
  private function apply($file) {
    $this->log("Applying $file");
    $bf = $this->backupFileBefore($file);
    if($this->isRollback($file)) {
      $this->log('Using as rollback');
      $this->log("Restoring database from $bf");
      $this->restore($bf);
    } else {
      $this->log('Using as standard migration (non-rollback)');
      $this->log("Dumping database to $bf");
      $this->backup($bf);
    }
    $this->log('Running migration script');
    $this->runMigration($file);
    $this->log('Done');
  }

  /**
   * Automatically migrates database if needed & possible
   */
  function automigrate() {
    $migrations = $this->unappliedMigrations();
    if(!$migrations) return;
    $this->checkWritable();
    foreach($migrations as $m) {
      $this->apply($m);
    }
    die();
  }

  /**
   * Get/set if rollback migrations should be applied.
   *
   * Rollback is migration, where database has same version number as last
   * migration script, but migration script seems to be modified since it was
   * applied. Database contents are automatically restored from backup and
   * migration script is then applied again.
   *
   * This will DESTROY data in database, do not ever use in production.
   *
   * Auto rollbacks are convinient way to write & debug migration scripts. You
   * just modify them and see how they work until you're satisfied with
   * the results.
   */
  function autorollback($set) {
    $this->autorollback = $set;
    return $this;
  }

  /**
   * Stores backup (dump) of database to give file
   */
  private function backup($file) {
    $dump = new MySQLDump(new mysqli(
      $this->settings['server'],
      $this->settings['user'],
      $this->settings['password'],
      $this->settings['database']
    ));
    $dump->save($file);
  }

  /**
   * Return filename of backup before given migration script
   */
  private function backupFileBefore($scriptFile) {
    return $this->folderBackup.'/pre-'.basename($scriptFile).'.sql';
  }

  /**
   * Checks if database and backup folder are writable, creates if possible
   */
  private function checkWritable() {
    if(!is_dir($this->folderBackup)) mkdir($this->folderBackup);
    if(!is_writable($this->folderBackup)) throw new Exception('Backup folder missing or not writable');
    try {
      $this->q('CREATE TABLE __test ( t int )');
      $this->q('DROP TABLE __test');
    } catch(Exception $e) {
      throw new Exception('Database seems to be not writable, got exception: '.$e->getMessage());
    }
  }

  /**
   *
   */
  private function db() {
    if(!$this->db) {
      $this->db = new PDO(
        'mysql:dbname='.$this->settings['database'].';'.
        'host='.$this->settings['server'],
        $this->settings['user'],
        $this->settings['password']
      );
    }
    return $this->db;
  }

  /**
   * Returns object representing schema version
   */
  private function dbSchema() {
    if(!isset($this->dbSchema)) {
      try {
        $a = dbQuery('SELECT * FROM _vars WHERE name LIKE "flee_%"');
        $r = array();
        $this->dbSchema = new stdClass();
        while(list($name, $value) = mysql_fetch_row($a)) {
          $name = str_replace('flee_', '', $name);
          $this->dbSchema->$name = $value;
        }
      } catch(DbException $e) {
        dbQuery('
          CREATE TABLE _vars (
            name varchar(64) NOT NULL,
            value varchar(4096)
          )
        ');
        dbQuery('
          INSERT INTO _vars(name, value) VALUES
            ("flee_version", 0),
            ("flee_timestamp", 0),
            ("flee_hash", 0)
        ');
        return $this->dbSchema();
      }
    }
    return $this->dbSchema;
  }

  /**
   * Determines if give file is rollback file according to current database
   * state
   */
  private function isRollback($file) {
    return $this->dbSchema()->version == self::version($file);
  }

  /**
   * Prints logging information
   */
  private function log($msg) {
    echo htmlspecialchars($msg).'<br>';
    ob_flush();
    flush();
    usleep(200000);
  }

  /**
   * Returns array of migration files. Key = version number, value = file with
   * path. Sorted from lowest to highest.
   */
  private function migrationFiles() {
    if(!isset($this->migrationFiles)) {
      $files = glob($this->folderMigration.'/*.php');
      $versions = preg_filter('@.*/0*(\d*)\.php$@', '$1', $files);
      $out = array();
      foreach($versions as $i => $v) {
        $out[$v] = $files[$i];
      }
      $this->migrationFiles = $out;
    }
    return $this->migrationFiles;
  }

  /**
   * Returns html code of prompt form
   */
  function prompt() {
    ?>
    <form method="post">
      There&#39;s migration pending. Confirm migration with password.<br>
      <input type="password" name="<?=self::POST_VAR?>"><br>
      <input type="submit" value="start migration"><br>
    </form>
    <?php
  }

  /**
   * Displays prompt and halts if database migration needed - when submitted
   * with right password, migrates database.
   */
  function promptmigrate($pass) {
    $migrations = $this->unappliedMigrations();
    if($migrations) {
      $this->checkWritable();
      if(!$this->promptPassed($pass)) {
        echo $this->prompt();
        die();
      }
      $this->automigrate();
    }
  }

  /**
   * True if post submitted with right password
   */
  private function promptPassed($pass) {
    return isset($_POST[self::POST_VAR]) && $_POST[self::POST_VAR] === $pass;
  }

  /**
   * Helper function for queries in migration scripts
   */
  private function q($q) {
    $this->db()->exec($q);
    if((int)$this->db()->errorCode()) {
      var_dump($this->db()->errorInfo());
      var_dump($q);
      throw new Exception($this->db()->errorInfo()[2]);
    }
  }

  /**
   * Restores database from $file
   */
  private function restore($file) {
    if(!$this->autorollback) throw new Exception('Tried to restore DB from backup but autorollback is disabled!');
    $num = $this->db()->exec(file_get_contents($file));
    $this->log("Affected $num rows");
  }

  /**
   * Returns array of pending rollback migrations (currently just one or empty
   * array)
   * @todo check hashes
   */
  private function rollbackMigrations() {
    $todos = array();
    $last = end((array_values($this->migrationFiles()))); // pass by reference hack
    if(self::version($last) == $this->dbSchema()->version && filemtime($last) > $this->dbSchema()->timestamp) {
      $todos[] = $last;
    }
    return $todos;
  }

  /**
   *
   */
  private function runMigration($file) {
    // update version to ensure further rollbacks even if migration script fails (for example exec time limit)
    $this->db()->query('UPDATE _vars SET value = '.(int)self::version($file).' WHERE name = "flee_version"');
    include $file;
    $this->db()->query('UPDATE _vars SET value = '.filemtime($file).' WHERE name = "flee_timestamp"');
  }

  /**
   * Get/set how to determine database schema version.
   * DB_VARS_TABLE => use extra table _vars in database
   * DB_COMMENT => use json in comment for database
   * BACKUP_EXISTS => apply all versions for which theres no backup yet
   * @todo other strategies are not implemented, just idea
   */
  function strategy($s = null) {
    return $this;
  }

  /**
   * Returns filenames of pending (unapplied) migration scripts for current
   * database version
   */
  private function unappliedMigrations() {
    $dbVersion = $this->dbSchema()->version;
    $todos = array(); // migrations to be done
    foreach($this->migrationFiles() as $fileVersion => $file) {
      if($fileVersion > $dbVersion) {
        $todos[] = $file;
      }
    }
    if($this->autorollback) {
      $todos = array_merge($todos, $this->rollbackMigrations());
    }
    return $todos;
  }

  /**
   * Returns version number based on filename
   */
  private static function version($filename) {
    return (int)preg_filter('@.*/0*(\d+)\.php$@', '$1', $filename);
  }

}
