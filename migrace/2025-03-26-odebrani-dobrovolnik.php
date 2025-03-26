<?php

$this->q(<<<SQL
    DELETE
    FROM role_seznam
    WHERE id_role = -202500017;
SQL,
);
