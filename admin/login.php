<?php
global $systemoveNastaveni;

$login = new \Gamecon\Login\Login(new \Gamecon\Web\Info($systemoveNastaveni));

echo $login->dejHtmlLogin();
