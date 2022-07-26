<?php
function vytvorSouborSkrytehoNastaveniPodleEnv(string $souborVerejnehoNastaveni) {
    $souborSkrytehoNastaveni = souborSkrytehoNastaveniPodleVerejneho($souborVerejnehoNastaveni);
    if (!is_file($souborSkrytehoNastaveni)) {
        $DB_USER                = getenv('DB_USER');
        $DB_PASS                = getenv('DB_PASS');
        $DB_NAME                = getenv('DB_NAME');
        $DB_SERV                = getenv('DB_SERV');
        $DBM_USER               = getenv('DBM_USER');
        $DBM_PASS               = getenv('DBM_PASS');
        $MIGRACE_HESLO          = getenv('MIGRACE_HESLO');
        $SECRET_CRYPTO_KEY      = getenv('SECRET_CRYPTO_KEY');
        $CRON_KEY               = getenv('CRON_KEY');
        $GOOGLE_API_CREDENTIALS = getenv('GOOGLE_API_CREDENTIALS');
        // ENV data viz například .github/workflows/deploy-jakublounek.yml
        file_put_contents($souborSkrytehoNastaveni, <<<PHP
define('DB_USER', '$DB_USER');
define('DB_PASS', '$DB_PASS');
define('DB_NAME', '$DB_NAME');
define('DB_SERV', '$DB_SERV');

// uživatel s přístupem k změnám struktury
define('DBM_USER', '$DBM_USER');
define('DBM_PASS', '$DBM_PASS');

define('MIGRACE_HESLO', '$MIGRACE_HESLO');
define('SECRET_CRYPTO_KEY', '$SECRET_CRYPTO_KEY');

define('CRON_KEY', '$CRON_KEY');
define('GOOGLE_API_CREDENTIALS', '$GOOGLE_API_CREDENTIALS');
PHP
        );
    }
    die(file_get_contents($souborSkrytehoNastaveni));
}

function souborSkrytehoNastaveniPodleVerejneho(string $souborVerejnehoNastaveni): string {
    $basenameVerejne = basename($souborVerejnehoNastaveni);
    $basenameSkryte  = str_replace('verejne-', '', $basenameVerejne);
    return str_replace($basenameVerejne, $basenameSkryte, $souborVerejnehoNastaveni);
}
