<?php

use Gamecon\Uzivatel\Platby;

/**
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 * @var \Gamecon\XTemplate\XTemplate $x
 * @var string $templateRoot
 */

$platby = new Platby($systemoveNastaveni);
if ($platby->platbyNaposledyAktualizovanyKdy() < new DateTimeImmutable('-1 day')) {
    $x->parse("{$x->root()}.varovaniOZasekleSynchronizaciFio");
}
