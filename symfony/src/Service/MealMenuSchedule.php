<?php

declare(strict_types=1);

namespace App\Service;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Kdy je jídelníček vidět a do kdy si v něm účastník vybírá.
 *
 * Jídelní lístek přichází 14 dní před GameConem (`JIDELNICEK_ZVEREJNIT_OD`). Vybírat jde do
 * konce prodeje jídla: catering dostane počty konkrétních jídel ve stejnou chvíli jako počty
 * porcí, takže termín je jeden (`JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE`).
 *
 * Termíny platí jen pro účastníka. Pult vybírá i po nich, stejně jako po termínu doobjednává.
 */
class MealMenuSchedule
{
    public function isPublished(): bool
    {
        return $this->nastaveni()->jeJidelnicekZverejnen();
    }

    public function getPublishedFrom(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->nastaveni()->jidelnicekZverejnitOd());
    }

    public function isChoiceClosed(): bool
    {
        return $this->nastaveni()->prodejJidlaUkoncen();
    }

    public function getChoiceDeadline(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->nastaveni()->prodejJidlaDo());
    }

    private function nastaveni(): SystemoveNastaveni
    {
        return SystemoveNastaveni::zGlobals();
    }
}
