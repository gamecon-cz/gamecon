<?php

use Gamecon\XTemplate\XTemplate;

require __DIR__ . '/sdilene-hlavicky.php';

$t = new XTemplate(__DIR__ . '/stravenky.xtpl');

$res = [
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Snídaně čtvrtek', 'poradi_dne' => '2', 'poradi_jidla' => '1'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Oběd čtvrtek', 'poradi_dne' => '2', 'poradi_jidla' => '2'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Večeře čtvrtek', 'poradi_dne' => '2', 'poradi_jidla' => '3'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Snídaně pátek', 'poradi_dne' => '3', 'poradi_jidla' => '1'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Oběd pátek', 'poradi_dne' => '3', 'poradi_jidla' => '2'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Večeře pátek', 'poradi_dne' => '3', 'poradi_jidla' => '3'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Snídaně sobota', 'poradi_dne' => '4', 'poradi_jidla' => '1'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Oběd sobota', 'poradi_dne' => '4', 'poradi_jidla' => '2'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Večeře sobota', 'poradi_dne' => '4', 'poradi_jidla' => '3'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Snídaně neděle', 'poradi_dne' => '5', 'poradi_jidla' => '1'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Oběd neděle', 'poradi_dne' => '5', 'poradi_jidla' => '2'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Večeře neděle', 'poradi_dne' => '5', 'poradi_jidla' => '3'],

    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Snídaně čtvrtek', 'poradi_dne' => '2', 'poradi_jidla' => '1'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Oběd čtvrtek', 'poradi_dne' => '2', 'poradi_jidla' => '2'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Večeře čtvrtek', 'poradi_dne' => '2', 'poradi_jidla' => '3'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Snídaně pátek', 'poradi_dne' => '3', 'poradi_jidla' => '1'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Oběd pátek', 'poradi_dne' => '3', 'poradi_jidla' => '2'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Večeře pátek', 'poradi_dne' => '3', 'poradi_jidla' => '3'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Snídaně sobota', 'poradi_dne' => '4', 'poradi_jidla' => '1'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Oběd sobota', 'poradi_dne' => '4', 'poradi_jidla' => '2'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Večeře sobota', 'poradi_dne' => '4', 'poradi_jidla' => '3'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Snídaně neděle', 'poradi_dne' => '5', 'poradi_jidla' => '1'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Oběd neděle', 'poradi_dne' => '5', 'poradi_jidla' => '2'],
    ['id_uzivatele' => '1', 'login_uzivatele' => 'Bianco stravenka', 'nazev' => 'Večeře neděle', 'poradi_dne' => '5', 'poradi_jidla' => '3'],
];

$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$t->assign("data", json_encode($res, $config));
$t->parse('stravenky');
$t->out('stravenky');
