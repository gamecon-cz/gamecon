<?php

$this->q("
UPDATE `stranky` SET `obsah` = '<!-- pár dobrých rad k editaci: vynechaná mezera za legendou vypadá špatně, velké písmeno na začátku vypadá špatně -->\r\n<div style=\"float:right\" class=\"legenda\">\r\n <hr style=\"background-color: #444\"> otevřené\r\n <hr class=\"vBudoucnu\"> speciální přihlašování (viz popis)\r\n <hr class=\"nahradnik\"> náhradn{n}\r\n <hr class=\"vDalsiVlne\"> otevíráme v další vlně\r\n <hr class=\"prihlasen\"> přihlášen{a}\r\n <hr class=\"organizator\"> organizuji\r\n <hr class=\"plno\"> plno\r\n</div>' WHERE `stranky`.`id_stranky` = 131;");
