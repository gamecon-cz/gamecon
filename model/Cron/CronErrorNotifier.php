<?php

declare(strict_types=1);

namespace Gamecon\Cron;

use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Rozešle upozornění na chybu, která spadla během běhu cronu.
 *
 * Sjednocuje logiku, která byla dřív duplikovaná ve dvou souborech
 * (admin/cron.php a admin/cron/_cron_job.php).
 */
class CronErrorNotifier
{
    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    public function ohlas(\Throwable $chyba): void
    {
        $traceString = $chyba->getTraceAsString();

        if (str_contains($chyba->getTrace()[0]['file'], 'Fio')) {
            (new GcMail($this->systemoveNastaveni))
                ->adresati(['it@gamecon.cz'])
                ->predmet('Selhala komunikace s Fio, pravděpodobně jenom dočasný výpadek')
                ->text(<<<TEXT
                    V rámci běhu Cron selhala komunikace s Fio. Pokud se neopakuje dlouhodobě, lze pravděpodobně ignorovat.

                    {$traceString}
                    TEXT)
                ->odeslat(GcMail::FORMAT_TEXT);
        } else {
            (new GcMail($this->systemoveNastaveni))
                ->adresati(['it@gamecon.cz', 'info@gamecon.cz'])
                ->predmet('!!IMPORTANT!! spadnulo odhlašování neplatičů')
                ->text(<<<TEXT
                    {$traceString}
                    TEXT)
                ->odeslat(GcMail::FORMAT_TEXT);
        }
    }
}
