<?php declare(strict_types=1);

namespace Gamecon\Exceptions;

class ChybaKolizeAktivit extends \Chyba
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message === '' ? hlaska('masKoliziAktivit') : $message, $code, $previous);
    }

}
