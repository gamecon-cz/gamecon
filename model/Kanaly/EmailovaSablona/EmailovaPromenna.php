<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

/**
 * A variable the code fills into the template when sending, such as the recipient name.
 */
readonly class EmailovaPromenna
{
    /**
     * @param string $ukazka value for the preview in the editor
     * @param bool $vPredmetu may also be used in the subject, not only in the text
     * @param bool $html the value is ready-made HTML, so it must not be escaped in an HTML e-mail
     * @param bool $volitelna the default wording does not use it, it is only offered
     */
    public function __construct(
        public string $nazev,
        public string $popis,
        public string $ukazka,
        public bool $vPredmetu = false,
        public bool $html = false,
        public bool $volitelna = false,
    ) {
        if (!RozkladSymbolu::jePromenna($this->nazev)) {
            throw new \LogicException("Proměnná '{$this->nazev}' musí mít jen malá písmena, číslice a podtržítka a nesmí začínat číslicí.");
        }
    }

    public function symbol(): string
    {
        return '%' . $this->nazev . '%';
    }
}
