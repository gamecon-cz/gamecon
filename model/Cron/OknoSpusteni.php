<?php

declare(strict_types=1);

namespace Gamecon\Cron;

use Gamecon\Cas\DateTimeImmutableStrict;

/**
 * Kdy smí automatický job s ?job= udělat svou práci (upomínky dlužníkům, varování o promlčení).
 *
 * Joby plánuje cron-job.org jednou denně, okno proto trvá skoro den. Mimo okno job jen zaloguje,
 * že není správný čas. Stejné okno používá hodinový cron k hlídání zmeškaných termínů
 * (HlidaniZmeskanychTerminu), proto je na jednom místě.
 */
final readonly class OknoSpusteni
{
    private const string DELKA = '+23 hours';

    public static function odZacatku(\DateTimeInterface $od): self
    {
        $od = DateTimeImmutableStrict::createFromInterface($od);

        return new self($od, $od->modifyStrict(self::DELKA));
    }

    private function __construct(
        public DateTimeImmutableStrict $od,
        public DateTimeImmutableStrict $do,
    ) {
    }

    public function obsahuje(\DateTimeInterface $kdy): bool
    {
        return $kdy >= $this->od && $kdy <= $this->do;
    }
}
