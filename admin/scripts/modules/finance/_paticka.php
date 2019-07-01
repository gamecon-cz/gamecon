<?php
$x=new XTemplate('_paticka.xtpl');
$x->assign(['kurzEura' => KURZ_EURO]);

$x->parse('paticka.kurz');
$x->parse('paticka');
$x->out('paticka');
