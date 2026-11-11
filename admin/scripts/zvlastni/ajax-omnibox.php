<?php

use Gamecon\Admin\OmniboxAdminu;

/** @var Uzivatel $u přihlášený, kterému admin/index.php omnibox povolil, viz OmniboxAdminu */

$data = omnibox(
    get('term'),
    true,
    get('dataVOdpovedi') ?: [],
    get('labelSlozenZ'),
    [],
    false,
    2,
    OmniboxAdminu::getRoleHledanych($u) ?? (get('jenSRolemi') ?: []),
);

header('Content-Type: application/json');

echo json_encode($data, JSON_THROW_ON_ERROR);
