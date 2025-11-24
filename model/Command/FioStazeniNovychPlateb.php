<?php

declare(strict_types=1);

namespace Gamecon\Command;

use Gamecon\Logger\JobResultLogger;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Platby;

class FioStazeniNovychPlateb
{
    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly JobResultLogger    $jobResultLogger,
    ) {
    }

    public function stahniNoveFioPlatby(): void
    {
        if (!defined('FIO_TOKEN') || FIO_TOKEN === '') {
            throw new \RuntimeException('FIO_TOKEN není nastaven.');
        }

        $platbyService = new Platby($this->systemoveNastaveni);
        if ($platbyService->platbyBylyAktualizovanyPredChvili()) {
            $this->jobResultLogger->logs('Platby byly aktualizovány před chvílí, přeskakuji.');

            return;
        }

        $this->jobResultLogger->logsText('Zpracovávám platby z Fio API...');
        $fioPlatby = $platbyService->nactiZPoslednichDni();
        $platbyService->nastavPosledniAktulizaciPlatebBehemSessionKdy($this->systemoveNastaveni->ted());

        if (!$fioPlatby) {
            $this->jobResultLogger->logsText('...žádné zaúčtovatelné platby.', false);

            return;
        }

        foreach ($fioPlatby as $fioPlatba) {
            $this->jobResultLogger->logs(
                sprintf(
                    ' - platba %s (%sKč, VS: %s%s%s)',
                    $fioPlatba->id(),
                    $fioPlatba->castka(),
                    $fioPlatba->variabilniSymbol(),
                    $fioPlatba->zpravaProPrijemce()
                        ? ', zpráva: ' . $fioPlatba->zpravaProPrijemce()
                        : '',
                    $fioPlatba->skrytaPoznamka()
                        ? ', poznámka: ' . $fioPlatba->skrytaPoznamka()
                        : '',
                ),
                false,
            );
        }
    }
}
