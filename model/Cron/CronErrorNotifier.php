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

    /**
     * @param string $nazevJobu lidsky čitelný název jobu, který spadl (kvůli přesnému předmětu mailu)
     */
    public function ohlas(\Throwable $chyba, string $nazevJobu): void
    {
        try {
            $this->odesliUpozorneni($chyba, $nazevJobu);
        } catch (\Throwable $chybaOdeslani) {
            // Odeslání upozornění samo selhalo (např. nedostupné SMTP). Nesmíme
            // shodit zbytek cronu - jen zalogujeme a běžíme dál.
            logs('Nepodařilo se odeslat upozornění na chybu cronu: ' . $chybaOdeslani->getMessage());
        }
    }

    private function odesliUpozorneni(\Throwable $chyba, string $nazevJobu): void
    {
        $traceString = $chyba->getTraceAsString();

        if ($this->jeVypadekFio($chyba)) {
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
                ->predmet("!!IMPORTANT!! spadl cron job: {$nazevJobu}")
                ->text(<<<TEXT
                    {$traceString}
                    TEXT)
                ->odeslat(GcMail::FORMAT_TEXT);
        }
    }

    /**
     * Fio chyby vznikají v souborech, jejichž cesta obsahuje "Fio"
     * (model/Finance/FioPlatba.php, model/Command/FioStazeniNovychPlateb.php).
     * Prohledáme celý zásobník i řetěz příčin, ne jen vrcholový rámec - ten může
     * být prázdný nebo bez klíče "file" (a přímé indexování getTrace()[0]["file"]
     * pak vyhodí "Undefined array key" a str_contains dostane null).
     */
    private function jeVypadekFio(\Throwable $chyba): bool
    {
        for ($aktualni = $chyba; $aktualni !== null; $aktualni = $aktualni->getPrevious()) {
            if (str_contains($aktualni->getFile(), 'Fio')) {
                return true;
            }
            foreach ($aktualni->getTrace() as $ramec) {
                if (isset($ramec['file']) && str_contains($ramec['file'], 'Fio')) {
                    return true;
                }
            }
        }

        return false;
    }
}
