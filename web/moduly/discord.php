<?php

function redirect($url, $statusCode = 303)
{
    header('Location: ' . $url, true, $statusCode);
    die();
}

redirect('https://discord.gg/wT6c6vcXez');
