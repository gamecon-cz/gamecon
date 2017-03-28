<?php

require __DIR__ . '/../nastaveni/zavadec.php';

if(HTTPS_ONLY) httpsOnly();

(new Flee([
  'migrationFolder' =>  __DIR__ . '/../migrace',
  'backupFolder'    =>  SPEC . '/db-backup',
  'user'            =>  DBM_USER,
  'password'        =>  DBM_PASS,
  'server'          =>  DBM_SERV,
  'database'        =>  DBM_NAME,
]))
  ->strategy(Flee::DB_VARS_TABLE)
  ->autorollback(false)
  ->promptmigrate(MIGRACE_HESLO);

echo "Zadne zmeny<br>\n"; // bez diakritiky kvůli výpisům např. v git-gui
