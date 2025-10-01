<?php

// Flytrap for WordPress hackers

ob_end_clean();

$eatThis = new Gamecon\EatThis\EatThis();

$eatThis->sendError500Header();

$eatThis->writeRandomBytesToOutput();

exit();
