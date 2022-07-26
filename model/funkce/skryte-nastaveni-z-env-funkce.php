<?php
function vytvorSouborSkrytehoNastaveniPodleEnv(string $absolutniCestaKSouboruVerejnehoNastaveni) {
    $souborSkrytehoNastaveni = souborSkrytehoNastaveniPodleVerejneho($absolutniCestaKSouboruVerejnehoNastaveni);
    if (!is_file($souborSkrytehoNastaveni)) {
        // ENV data viz například .github/workflows/deploy-jakublounek.yml
        file_put_contents($souborSkrytehoNastaveni, <<<PHP
define('DB_USER', '{$_ENV['DB_USER']}');
define('DB_PASS', '{$_ENV['DB_PASS']}');
define('DB_NAME', '{$_ENV['DB_NAME']}');
define('DB_SERV', '{$_ENV['DB_SERV']}');

// uživatel s přístupem k změnám struktury
define('DBM_USER', '{$_ENV['DBM_USER']}');
define('DBM_PASS', '{$_ENV['DB_PASS']}');

define('MIGRACE_HESLO', '{$_ENV['MIGRACE_HESLO']}');
define('SECRET_CRYPTO_KEY', '{$_ENV['SECRET_CRYPTO_KEY']}');

define('CRON_KEY', '{$_ENV['CRON_KEY']}');
define('GOOGLE_API_CREDENTIALS', '{$_ENV['GOOGLE_API_CREDENTIALS']}');
PHP
        );
    }
}

function souborSkrytehoNastaveniPodleVerejneho(string $souborVerejnehoNastaveni): string {
    $basenameVerejne = basename($souborVerejnehoNastaveni);
    $basenameSkryte  = str_replace('verejne-', '', $basenameVerejne);
    return str_replace($basenameVerejne, $basenameSkryte, $souborVerejnehoNastaveni);
}
