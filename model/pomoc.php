<?php

declare(strict_types=1);

use Gamecon\XTemplate\XTemplate;

/**
 * Sekce přihlášky „Chceš se zapojit do organizace?“. Je to jen text, který každý druh pomoci posílá jinam:
 * dobrovolnické aktivity v programu, vedoucí programové sekce, stránka pro sponzory, celoroční organizace.
 * Nic se z ní neukládá; proč tu už není formulář, viz docs/generated/prihlaska-odeslani-objednavky.md.
 */
class Pomoc
{
    public function html(): string
    {
        $t = new XTemplate(__DIR__ . '/pomoc.xtpl');
        $t->parse('pomoc');

        return $t->text('pomoc');
    }
}
