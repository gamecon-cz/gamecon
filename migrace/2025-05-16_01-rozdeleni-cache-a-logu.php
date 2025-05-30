<?php

if (file_exists(PROJECT_ROOT_DIR . '/cache/private/chyby.sqlite')) {
    rename(PROJECT_ROOT_DIR . '/cache/private/chyby.sqlite', LOGY . '/chyby.sqlite');
}

if (file_exists(PROJECT_ROOT_DIR . '/cache/private/platby.sqlite')) {
    rename(PROJECT_ROOT_DIR . '/cache/private/platby.sqlite', LOGY . '/platby.sqlite');
}

foreach (glob(PROJECT_ROOT_DIR . '/cache/private/*.log') as $file) {
    rename($file, LOGY . '/' . basename($file));
}

$filesystem = new \Symfony\Component\Filesystem\Filesystem();

if (is_dir(PROJECT_ROOT_DIR . '/cache/private/fio')) {
    $filesystem->rename(PROJECT_ROOT_DIR . '/cache/private/fio', LOGY . '/fio');
}

if (is_dir(PROJECT_ROOT_DIR . '/cache/private/logs')) {
    $filesystem->rename(PROJECT_ROOT_DIR . '/cache/private/logs', LOGY . '/cron');
}
