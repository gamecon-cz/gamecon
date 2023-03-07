<?php

declare(strict_types=1);

namespace Gamecon\Logger;

use Gamecon\Kanaly\GcMail;

class Zaznamnik
{
    private array $zpravy;

    public function uchovejZEmailu(GcMail $mail) {
        $this->zpravy[] = <<<TEXT
            {$mail->dejPredmet()}:
            {$mail->dejText()}
        TEXT;
    }

    public function pridejZpravu(string $zprava) {
        $this->zpravy[] = $zprava;
    }

    /**
     * @return string[]
     */
    public function zpravy(): array {
        return $this->zpravy;
    }
}
