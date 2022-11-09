<?php

$login = new \Gamecon\Login\Login();

if (!headers_sent()) {
    header('Found', true, 302);
}

echo $login->dejHtmlLogin();
