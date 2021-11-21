<?php

$data = omnibox(
    get('term'),
    true,
    get('dataVOdpovedi') ?: [],
    get('labelSlozenZ')
);

header('Content-Type: application/json');

echo json_encode($data, JSON_THROW_ON_ERROR);
