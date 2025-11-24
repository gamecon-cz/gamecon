<?php

declare(strict_types=1);

namespace Gamecon\Logger;

class JobResultLogger
{

    /**
     * Výstup do logu
     */
    public function logs(
        string $string,
        bool $zalogovatCas = true,
    ): void {
        $this->writeMessage(($zalogovatCas
                ? date('Y-m-d H:i:s ')
                : '') . "<pre>$string</pre><br>",
        );
    }

    /**
     * Výstup do logu
     */
    public function logsText(string $text): void
    {
        writeMessage("<pre>$text</pre>");
    }

    public function writeMessage(
        string $message,
        string $newLineAfter = "\n",
    ): void {
        echo $message . $newLineAfter;
        flush();
    }

}
