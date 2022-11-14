<?php

// Flytrap for WordPress hackers

$eatThis = new Gamecon\EatThis\EatThis();

$eatThis->sendError500Header();

$eatThis->writeRandomBytesToOutput();

exit();
