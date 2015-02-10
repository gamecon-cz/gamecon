<?php

require '../zavadec.php';

(new Flee([
  'migrationFolder' =>  __DIR__.'/../migrace',
  'backupFolder'    =>  SPEC.'/db-backup',
  'user'            =>  DBM_USER,
  'password'        =>  DBM_PASS,
  'server'          =>  DBM_SERV,
  'database'        =>  DBM_NAME,
]))
  ->strategy(Flee::DB_VARS_TABLE)
  ->autorollback(false)
  ->promptmigrate('stereo');

echo 'hotovo';
