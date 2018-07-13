<?php

$this->q("DELETE FROM r_prava_zidle  WHERE id_prava = 200");
$this->q("DELETE FROM r_prava_soupis WHERE id_prava = 200");
$this->q("DELETE FROM r_prava_zidle  WHERE id_prava = 2");
$this->q("DELETE FROM r_prava_soupis WHERE id_prava = 2");
$this->q("DELETE FROM r_prava_zidle  WHERE id_prava = 999");
$this->q("DELETE FROM r_prava_soupis WHERE id_prava = 999");

// v systému již neexistuje
$this->q("DELETE FROM r_prava_zidle  WHERE id_prava = 1");
$this->q("DELETE FROM r_prava_soupis WHERE id_prava = 1");
$this->q("DELETE FROM r_prava_zidle  WHERE id_prava = 3");
$this->q("DELETE FROM r_prava_soupis WHERE id_prava = 3");
