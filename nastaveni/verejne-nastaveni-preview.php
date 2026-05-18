<?php
require_once __DIR__ . '/nastaveni-preview.php';

// Preview deploys live at <slug>.preview.gamecon.cz with PATH-based routing
// (no admin./cache. subdomains — that's a production-only convention).
// The URL constants therefore mirror the local-dev shape: same host,
// distinct path prefixes.
$previewHost = defined('SERVER_NAME')
    ? constant('SERVER_NAME')
    : ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'preview.gamecon.cz');

define('URL_WEBU', 'https://' . $previewHost);
define('URL_ADMIN', 'https://' . $previewHost . '/admin');
define('URL_CACHE', 'https://' . $previewHost . '/cache/public');

// Previews use production-style data via the auto-restore-from-ostra hook
// (see docs/docker-migration-plan.md §2.5). We deliberately do NOT send
// analytics from previews — they're noise in real metrics — and we DO
// enforce HTTPS because HAProxy terminates TLS for *.preview.gamecon.cz.
define('ANALYTICS', false);
define('HTTPS_ONLY', true);

define('REACT_V_PROHLIZECI', false);
define('AUTOMATICKE_SESTAVENI', false);
define('BABEL_BINARKA', null);

/** aktuální ročník -- při změně roku viz Překlápění ročníku @link PREKLOPENI_ROCNIKU_NAVOD.md */
define('ROCNIK', 2026);

error_reporting(E_ALL); // reportuje se vše, o zobrazení se stará výjimkovač
