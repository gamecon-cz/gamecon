<?php

/**
 * Přidat novinku
 *
 * nazev: Přidat novinku
 * pravo: 105
 */

$form = ($n = Novinka::zId(get('id'))) ? $n->form() : new DbFormGc('novinky');
$form->processPost();
echo $form->full();
