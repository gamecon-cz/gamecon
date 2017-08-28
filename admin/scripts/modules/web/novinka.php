<?php

/**
 * Přidat novinku
 *
 * nazev: Přidat novinku
 * pravo: 105
 */

$form = Novinka::form(get('id'));
$form->processPost();
echo $form->full();
