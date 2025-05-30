<?php

if (file_exists(PROJECT_ROOT_DIR . '/cache/private/chyby.sqlite')) {
    rename(PROJECT_ROOT_DIR . '/cache/private/chyby.sqlite', LOGY . '/chyby.sqlite');
}

if (file_exists(PROJECT_ROOT_DIR . '/cache/private/maily.log')) {
    rename(PROJECT_ROOT_DIR . '/cache/private/maily.log', LOGY . '/maily.log');
}

if (file_exists(PROJECT_ROOT_DIR . '/cache/private/platby.sqlite')) {
    rename(PROJECT_ROOT_DIR . '/cache/private/platby.sqlite', LOGY . '/platby.sqlite');
}

if (file_exists(PROJECT_ROOT_DIR . '/cache/private/role.log')) {
    rename(PROJECT_ROOT_DIR . '/cache/private/role.log', LOGY . '/role.log');
}

$filesystem = new \Symfony\Component\Filesystem\Filesystem();

if (is_dir(PROJECT_ROOT_DIR . '/cache/private/fio')) {
    $filesystem->rename(PROJECT_ROOT_DIR . '/cache/private/fio', LOGY . '/fio');
}

if (is_dir(PROJECT_ROOT_DIR . '/cache/private/logs')) {
    $filesystem->rename(PROJECT_ROOT_DIR . '/cache/private/logs', LOGY . '/cron');
}
