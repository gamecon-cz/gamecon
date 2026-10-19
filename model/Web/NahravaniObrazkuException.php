<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Nahrání obrázku se nepovedlo. Zpráva je určená přímo organizátorovi v administraci,
 * je česky a neobsahuje cesty na serveru. Pozor, není escapovaná pro HTML.
 */
class NahravaniObrazkuException extends \RuntimeException
{
}
