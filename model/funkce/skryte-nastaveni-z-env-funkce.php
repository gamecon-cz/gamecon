<?php
function vytvorSouborSkrytehoNastaveniPodleEnv(
    string $souborVerejnehoNastaveni,
) {
    $souborSkrytehoNastaveni = souborSkrytehoNastaveniPodleVerejneho($souborVerejnehoNastaveni);
    if (!is_file($souborSkrytehoNastaveni)) {
        // ENV názvy a hodnoty viz například .github/workflows/deploy-ostra.yml
        $DB_USER = getenv('DB_USER');
        $DB_PASS = getenv('DB_PASS');
        $DB_NAME = getenv('DB_NAME');
        $DB_SERV = getenv('DB_SERV');
        $DB_PORT = getenv('DB_PORT')
            ?: '3306';
        $DBM_USER = getenv('DBM_USER');
        $DBM_PASS = getenv('DBM_PASS');
        $DB_ANONYM_SERV = getenv('DB_ANONYM_SERV');
        $DB_ANONYM_USER = getenv('DB_ANONYM_USER');
        $DB_ANONYM_PASS = getenv('DB_ANONYM_PASS');
        $DB_ANONYM_NAME = getenv('DB_ANONYM_NAME');
        $MIGRACE_HESLO = getenv('MIGRACE_HESLO');
        $SECRET_CRYPTO_KEY = getenv('SECRET_CRYPTO_KEY');
        $CRON_KEY = getenv('CRON_KEY'); // pozor změnu je nutné provést i v https://console.cron-job.org
        $GOOGLE_API_CREDENTIALS = getenv('GOOGLE_API_CREDENTIALS')
            ?: '{}';
        $FIO_TOKEN = getenv('FIO_TOKEN');
        $MAILER_DSN = getenv('MAILER_DSN');
        $APP_ENV = getenv('APP_ENV');
        $APP_DEBUG = getenv('APP_DEBUG') ? 'true' : 'false';
        $APP_SECRET = getenv('APP_SECRET');

        // Basic-auth pro Caddy bránu před preview / archive prostředími.
        // Admin rozcestník u odkazů ukazuje tyto údaje jako kopírovatelný text
        // (prohlížeč se zeptá při prvním otevření).
        $PREVIEW_BASIC_AUTH_USER = getenv('PREVIEW_BASIC_AUTH_USER');
        $PREVIEW_BASIC_AUTH_PASSWORD = getenv('PREVIEW_BASIC_AUTH_PASSWORD');
        $ARCHIVE_BASIC_AUTH_USER = getenv('ARCHIVE_BASIC_AUTH_USER');
        $ARCHIVE_BASIC_AUTH_PASSWORD = getenv('ARCHIVE_BASIC_AUTH_PASSWORD');

        // Tajemství pro podpis „gate" tokenu, kterým admin rozcestník připojí
        // ?gate=<podpis> k odkazům na preview / archive. Musí být shodné s tím,
        // co drží gate-validator (ansible repo, role gate_validator), aby
        // podepsaný token prošel ověřením. Dvě oddělená tajemství = izolace
        // preview vs. archive (dvě instance validatoru). Viz GateLink.
        $PREVIEW_GATE_SECRET = getenv('PREVIEW_GATE_SECRET');
        $ARCHIVE_GATE_SECRET = getenv('ARCHIVE_GATE_SECRET');

        $ted = date(DATE_ATOM);
        $nazevTetoFunkce = __FUNCTION__;

        file_put_contents($souborSkrytehoNastaveni, <<<PHP
            <?php
            // vygenerováno $ted v $nazevTetoFunkce
            
            // uživatel se základním přístupem
            define('DB_USER', '$DB_USER');
            define('DB_PASS', '$DB_PASS');
            define('DB_NAME', '$DB_NAME');
            define('DB_SERV', '$DB_SERV');
            define('DB_PORT', '$DB_PORT');
            
            // uživatel s přístupem k změnám struktury
            define('DBM_USER', '$DBM_USER');
            define('DBM_PASS', '$DBM_PASS');
            
            define('DB_ANONYM_SERV', '$DB_ANONYM_SERV');
            define('DB_ANONYM_USER', '$DB_ANONYM_USER');
            define('DB_ANONYM_PASS', '$DB_ANONYM_PASS');
            define('DB_ANONYM_NAME', '$DB_ANONYM_NAME');
            
            define('MIGRACE_HESLO', '$MIGRACE_HESLO');
            define('SECRET_CRYPTO_KEY', '$SECRET_CRYPTO_KEY');
            
            define('CRON_KEY', '$CRON_KEY'); // pozor změnu CRON_KEY je nutné provést i v https://console.cron-job.org
            
            define('GOOGLE_API_CREDENTIALS', json_decode('$GOOGLE_API_CREDENTIALS', true));
            
            define('FIO_TOKEN', '$FIO_TOKEN'); // platnost do 11.9.2030
            
            define('MAILER_DSN', '$MAILER_DSN');
            
            // Symfony
            define('APP_ENV', '$APP_ENV');
            define('APP_DEBUG', $APP_DEBUG);
            define('APP_SECRET', '$APP_SECRET');

            // Basic-auth pro Caddy bránu před preview / archive prostředími
            define('PREVIEW_BASIC_AUTH_USER', '$PREVIEW_BASIC_AUTH_USER');
            define('PREVIEW_BASIC_AUTH_PASSWORD', '$PREVIEW_BASIC_AUTH_PASSWORD');
            define('ARCHIVE_BASIC_AUTH_USER', '$ARCHIVE_BASIC_AUTH_USER');
            define('ARCHIVE_BASIC_AUTH_PASSWORD', '$ARCHIVE_BASIC_AUTH_PASSWORD');

            // Tajemství pro podpis gate tokenu (one-click přístup přes bránu)
            define('PREVIEW_GATE_SECRET', '$PREVIEW_GATE_SECRET');
            define('ARCHIVE_GATE_SECRET', '$ARCHIVE_GATE_SECRET');
            PHP,
        );
    }
}

function souborSkrytehoNastaveniPodleVerejneho(
    string $souborVerejnehoNastaveni,
): string {
    $basenameVerejne = basename($souborVerejnehoNastaveni);
    $basenameSkryte = str_replace('verejne-', '', $basenameVerejne);

    return str_replace($basenameVerejne, $basenameSkryte, $souborVerejnehoNastaveni);
}

function souborServerNastaveniPodleEnv(
    string $souborVerejnehoNastaveni,
): string {
    $basenameVerejne = basename($souborVerejnehoNastaveni);
    $basenameServer = 'nastaveni-server.php';

    return str_replace($basenameVerejne, $basenameServer, $souborVerejnehoNastaveni);
}

function vytvorSouborServerNastaveniPodleEnv(
    string $souborVerejnehoNastaveni,
) {
    $souborServerNastaveniPodleEnv = souborServerNastaveniPodleEnv($souborVerejnehoNastaveni);
    if (!is_file($souborServerNastaveniPodleEnv)) {
        // ENV názvy a hodnoty viz například .github/workflows/deploy-ostra.yml
        $SERVER_NAME = getenv('SERVER_NAME');

        $ted = date(DATE_ATOM);
        $nazevTetoFunkce = __FUNCTION__;

        file_put_contents($souborServerNastaveniPodleEnv, <<<PHP
            <?php
            // vygenerováno $ted v $nazevTetoFunkce
            
            define('SERVER_NAME', '$SERVER_NAME');
            PHP,
        );
    }
}
