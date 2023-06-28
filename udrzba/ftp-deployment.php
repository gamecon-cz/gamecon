#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Deployment\CliRunner;

if (!class_exists(CliRunner::class)) {
  throw new \RuntimeException("Chybí FTP deployment knihovna dg/ftp-deployment");
}

$runner = new CliRunner;
die($runner->run());

// can not use original vendor/dg/ftp-deployment/deployment as it does not use autoload and has a bug with a missing required file with class Deployment\JobRunner
