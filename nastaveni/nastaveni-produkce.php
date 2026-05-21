<?php

// Archive 2024 production settings — committed to archive/2024.
//
// In the main-line gamecon repo this file is .gitignored and an operator
// writes it by hand at year-flip time (see PREKLOPENI_ROCNIKU_NAVOD.md),
// holding hardcoded DB credentials read from the team password store.
//
// On archive/YYYY branches we commit the file with env-driven reads
// instead. The host's deploy-year-archive.sh sets DB_USER / DB_PASS /
// DB_NAME via `docker run -e`, derived deterministically from
// HMAC(year, /etc/year-archive-deployer/secret). See
// docs/year-archive-phase0-recon.md §3 in the ansible repo for the
// audit + reasoning.

// User with basic access. DB_SERV defaults to the docker0 bridge IP
// because that's where the host MariaDB is reachable from inside an
// archive container; the deploy script also sets this explicitly, but
// the fallback keeps `docker run` without -e DB_SERV from breaking.
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER')
    ?: 'gamecon_2024');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS')
    ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME')
    ?: 'gamecon_2024');
if (!defined('DB_SERV')) define('DB_SERV', getenv('DB_SERV')
    ?: '172.17.0.1');
if (!defined('DB_PORT')) define('DB_PORT', (int)(getenv('DB_PORT')
    ?: '3306'));

// User with schema-change rights. Archives don't run migrations at
// runtime, so this defaults to the same as DB_USER (the deploy script
// passes DBM_USER/DBM_PASS too for completeness with code that
// references them).
if (!defined('DBM_USER')) define('DBM_USER', getenv('DBM_USER')
    ?: DB_USER);
if (!defined('DBM_PASS')) define('DBM_PASS', getenv('DBM_PASS')
    ?: DB_PASS);

// Production-only constants needed by the legacy code paths. Archives
// don't accept payments, don't run cron, don't call Google APIs —
// these stay empty/default to keep the code happy without exposing
// any live credentials.
if (!defined('FIO_TOKEN')) define('FIO_TOKEN', '');
if (!defined('CRON_KEY')) define('CRON_KEY', '');
if (!defined('GOOGLE_API_CREDENTIALS')) define('GOOGLE_API_CREDENTIALS', '');
if (!defined('VAROVAT_O_ZASEKLE_SYNCHRONIZACI_PLATEB')) define('VAROVAT_O_ZASEKLE_SYNCHRONIZACI_PLATEB', false);

// SECRET_CRYPTO_KEY is required by Defuse\Crypto helpers — used by
// session crypto helpers in the codebase. A frozen archive can reuse
// a stable per-year key without security implications (no new sessions
// of consequence are minted on an archive). Falls back to a dummy if
// env doesn't supply one; in practice the deploy script can pass a
// stable value derived from the same HMAC secret if we ever need
// cross-deploy session continuity.
if (!defined('SECRET_CRYPTO_KEY')) define('SECRET_CRYPTO_KEY', getenv('SECRET_CRYPTO_KEY')
    ?: 'def0000066cba9ae32fdda839a143276cc0646b3880920c93876ecc1bbaca96ee6ed251559516b1804f4742c2165e4c7eb3ed5c7a5abe857c6db8608e3b5fe97a8cdf15a');
