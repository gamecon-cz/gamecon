<?php
function vytvorSouborSkrytehoNastaveniPodleEnv(string $souborVerejnehoNastaveni) {
    $souborSkrytehoNastaveni = souborSkrytehoNastaveniPodleVerejneho($souborVerejnehoNastaveni);
    if (!is_file($souborSkrytehoNastaveni)) {
        // ENV názvy a hodnoty viz například .github/workflows/deploy-jakublounek.yml
        $DB_USER                = getenv('DB_USER');
        $DB_PASS                = getenv('DB_PASS');
        $DB_NAME                = getenv('DB_NAME');
        $DB_SERV                = getenv('DB_SERV');
        $DBM_USER               = getenv('DBM_USER');
        $DBM_PASS               = getenv('DBM_PASS');
        $DB_ANONYM_SERV         = getenv('DB_ANONYM_SERV');
        $DB_ANONYM_USER         = getenv('DB_ANONYM_USER');
        $DB_ANONYM_PASS         = getenv('DB_ANONYM_PASS');
        $DB_ANONYM_NAME         = getenv('DB_ANONYM_NAME');
        $MIGRACE_HESLO          = getenv('MIGRACE_HESLO');
        $SECRET_CRYPTO_KEY      = getenv('SECRET_CRYPTO_KEY');
        $CRON_KEY               = getenv('CRON_KEY');
        $GOOGLE_API_CREDENTIALS = getenv('GOOGLE_API_CREDENTIALS') ?: '{}';
        $FIO_TOKEN              = getenv('FIO_TOKEN');
        $MAILER_DSN             = getenv('MAILER_DSN');

        $ted             = date(DATE_ATOM);
        $nazevTetoFunkce = __FUNCTION__;

        file_put_contents($souborSkrytehoNastaveni, <<<PHP
<?php
// vygenerováno $ted v $nazevTetoFunkce

// uživatel se základním přístupem
define('DB_USER', '$DB_USER');
define('DB_PASS', '$DB_PASS');
define('DB_NAME', '$DB_NAME');
define('DB_SERV', '$DB_SERV');

// uživatel s přístupem k změnám struktury
define('DBM_USER', '$DBM_USER');
define('DBM_PASS', '$DBM_PASS');

define('DB_ANONYM_SERV', '$DB_ANONYM_SERV');
define('DB_ANONYM_USER', '$DB_ANONYM_USER');
define('DB_ANONYM_PASS', '$DB_ANONYM_PASS');
define('DB_ANONYM_NAME', '$DB_ANONYM_NAME');

define('MIGRACE_HESLO', '$MIGRACE_HESLO');
define('SECRET_CRYPTO_KEY', '$SECRET_CRYPTO_KEY');

define('CRON_KEY', '$CRON_KEY');
define('GOOGLE_API_CREDENTIALS', json_decode('$GOOGLE_API_CREDENTIALS', true));

define('FIO_TOKEN', '$FIO_TOKEN'); // platnost do 11.9.2030

define('MAILER_DSN', '$MAILER_DSN');
PHP
        );
    }
}

function souborSkrytehoNastaveniPodleVerejneho(string $souborVerejnehoNastaveni): string {
    $basenameVerejne = basename($souborVerejnehoNastaveni);
    $basenameSkryte  = str_replace('verejne-', '', $basenameVerejne);
    return str_replace($basenameVerejne, $basenameSkryte, $souborVerejnehoNastaveni);
}
