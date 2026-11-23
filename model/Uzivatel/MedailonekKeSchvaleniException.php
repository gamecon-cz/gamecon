<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

/**
 * Text medailonku nejde schválit: už je schválený, nebo vypravěč mezitím poslal nový. Zpráva je pro organizátora.
 */
final class MedailonekKeSchvaleniException extends \RuntimeException
{
}
