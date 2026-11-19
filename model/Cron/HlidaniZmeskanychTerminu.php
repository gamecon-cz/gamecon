<?php

declare(strict_types=1);

namespace Gamecon\Cron;

use Gamecon\Command\FioStazeniNovychPlateb;
use Gamecon\Kanaly\GcMail;
use Gamecon\Logger\JobResultLogger;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\Enum\TypVarovaniPromlceni;
use Gamecon\Uzivatel\PromlceniZustatku;
use Gamecon\Uzivatel\UpominaniDluzniku;
use Uzivatel;

/**
 * Hlídá, že automatické upomínky dlužníkům a varování o promlčení proběhly ve svém termínu.
 *
 * Joby s ?job= (dluznici, promlceni) plánuje ručně cron-job.org. Když tam job chybí nebo je vypnutý,
 * okno uplyne a nestane se nic: po GC 2026 tak automatická upomínka nepřišla žádnému dlužníkovi
 * a nikdo si toho nevšiml. Hodinový cron (admin/cron.php) proto po konci každého okna ověří, že
 * job zapsal svůj běh do hromadne_akce_log, a když záznam chybí, pošle CFO a IT upozornění.
 * Upozornění si zapíše taky, takže na každý termín upozorní jen jednou.
 */
final class HlidaniZmeskanychTerminu
{
    use LogHomadnychAkciTrait;

    private const string SKUPINA = 'hlidani-zmeskanych-terminu';
    /** Job spuštěný těsně před koncem okna může ještě dobíhat, kontrola mu dá čas se zapsat. */
    private const string REZERVA_PO_KONCI_OKNA = '+1 hour';

    public static function zNastaveni(SystemoveNastaveni $systemoveNastaveni): self
    {
        $jobResultLogger = new JobResultLogger();

        return new self(
            $systemoveNastaveni,
            new UpominaniDluzniku(
                $systemoveNastaveni,
                $jobResultLogger,
                new FioStazeniNovychPlateb($systemoveNastaveni, $jobResultLogger),
            ),
            new PromlceniZustatku($systemoveNastaveni, $jobResultLogger),
        );
    }

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly UpominaniDluzniku  $upominaniDluzniku,
        private readonly PromlceniZustatku  $promlceniZustatku,
    ) {
    }

    /**
     * @return list<HlidanyTermin> zmeškané termíny, na které právě upozornila
     */
    public function zkontroluj(): array
    {
        $ted      = $this->systemoveNastaveni->ted();
        $uplynule = array_filter(
            $this->dejHlidaneTerminy(),
            static fn (HlidanyTermin $termin) => $termin->okno->do->modifyStrict(self::REZERVA_PO_KONCI_OKNA) < $ted,
        );
        if ($uplynule === []) {
            // před koncem prvního okna v ročníku se nesahá ani do databáze
            return [];
        }

        $uzUpozorneno = array_flip($this->dejUzUpozorneneAkce($uplynule));
        $zmeskane     = [];
        foreach ($uplynule as $termin) {
            if (isset($uzUpozorneno[$this->nazevAkce($termin)])) {
                continue;
            }
            if (($termin->probehloKdy)() !== null) {
                continue;
            }
            $zmeskane[] = $termin;
        }

        if ($zmeskane === []) {
            return [];
        }

        // Zapisuje se až po odeslání: když mail neodejde (výpadek SMTP), zkusí to příští hodinový běh.
        $this->odesliUpozorneni($zmeskane);

        $system = Uzivatel::zId(Uzivatel::SYSTEM, true);
        foreach ($zmeskane as $termin) {
            $this->zalogujHromadnouAkci(self::SKUPINA, $this->nazevAkce($termin), 'upozorněno', $system, $ted);
        }

        return $zmeskane;
    }

    /**
     * @return list<HlidanyTermin>
     */
    public function dejHlidaneTerminy(): array
    {
        $rocnik = $this->systemoveNastaveni->rocnik();

        $rucniUpominky = 'Automaticky už upomínka neodejde, okno jobu skončilo. Rozešli ji ručně v adminu: Finance → Upomínky dlužníkům.';
        $varovani      = 'Automaticky už varování neodejde, okno jobu skončilo. Pozor, automatické promlčení po GameConu'
                         . ' (také ?job=promlceni) promlčí zůstatky i těm, kdo varování nedostali: před ním rozhodni,'
                         . ' jestli je varovat jinak (seznam je v adminu: Finance → Promlčení zůstatků).';

        return [
            new HlidanyTermin(
                klic: "upominani-tyden-$rocnik",
                nazev: "upomínka dlužníkům týden po GameConu $rocnik",
                okno: $this->upominaniDluzniku->dejOknoUpominky(TypUpominky::TYDEN),
                job: 'dluznici',
                coDal: $rucniUpominky,
                probehloKdy: fn () => $this->upominaniDluzniku->automatickaUpominkaOdeslanaKdy(TypUpominky::TYDEN, $rocnik),
            ),
            new HlidanyTermin(
                klic: "upominani-mesic-$rocnik",
                nazev: "upomínka dlužníkům měsíc po GameConu $rocnik",
                okno: $this->upominaniDluzniku->dejOknoUpominky(TypUpominky::MESIC),
                job: 'dluznici',
                coDal: $rucniUpominky,
                probehloKdy: fn () => $this->upominaniDluzniku->automatickaUpominkaOdeslanaKdy(TypUpominky::MESIC, $rocnik),
            ),
            new HlidanyTermin(
                klic: "varovani-promlceni-mesic-$rocnik",
                nazev: "varování o promlčení zůstatků měsíc před registracemi $rocnik",
                okno: $this->promlceniZustatku->dejOknoVarovani(TypVarovaniPromlceni::MESIC),
                job: 'promlceni',
                coDal: $varovani,
                probehloKdy: fn () => $this->promlceniZustatku->varovaniOdeslanoKdy(TypVarovaniPromlceni::MESIC, $rocnik),
            ),
            new HlidanyTermin(
                klic: "varovani-promlceni-tyden-$rocnik",
                nazev: "varování o promlčení zůstatků týden před registracemi $rocnik",
                okno: $this->promlceniZustatku->dejOknoVarovani(TypVarovaniPromlceni::TYDEN),
                job: 'promlceni',
                coDal: $varovani,
                probehloKdy: fn () => $this->promlceniZustatku->varovaniOdeslanoKdy(TypVarovaniPromlceni::TYDEN, $rocnik),
            ),
            // Promlčení horní mez nemá, proběhne při kterémkoli dalším běhu jobu. Okno je tu jen
            // proto, aby se na chybějící běh upozornilo stejně brzy jako u ostatních jobů.
            new HlidanyTermin(
                klic: "automaticke-promlceni-$rocnik",
                nazev: "automatické promlčení zůstatků po GameConu $rocnik",
                okno: OknoSpusteni::odZacatku($this->promlceniZustatku->automatickePromlceniOd()),
                job: 'promlceni',
                coDal: 'Promlčení proběhne při nejbližším běhu ?job=promlceni, i ručním. Před ním zkontroluj seznam'
                       . ' v adminu: Finance → Promlčení zůstatků (a jestli lidé dostali varování).',
                probehloKdy: fn () => $this->promlceniZustatku->automatickaPromlceniProvedenaKdy($rocnik),
            ),
        ];
    }

    private function nazevAkce(HlidanyTermin $termin): string
    {
        return 'zmeskano-' . $termin->klic;
    }

    /**
     * @param array<HlidanyTermin> $terminy
     * @return list<string>
     */
    private function dejUzUpozorneneAkce(array $terminy): array
    {
        return dbFetchColumn(<<<'SQL'
SELECT akce
FROM hromadne_akce_log
WHERE skupina = $0
    AND akce IN ($1)
SQL,
            [
                0 => self::SKUPINA,
                1 => array_values(array_map(fn (HlidanyTermin $termin) => $this->nazevAkce($termin), $terminy)),
            ],
        );
    }

    /**
     * @param non-empty-list<HlidanyTermin> $zmeskane
     */
    private function odesliUpozorneni(array $zmeskane): void
    {
        $polozky = [];
        $joby    = [];
        $logy    = [];
        foreach ($zmeskane as $termin) {
            $polozky[] = sprintf(
                "- %s (?job=%s), okno %s až %s\n  %s",
                $termin->nazev,
                $termin->job,
                $termin->okno->od->format('d.m.Y H:i'),
                $termin->okno->do->format('d.m.Y H:i'),
                $termin->coDal,
            );
            $joby[$termin->job] = '?job=' . $termin->job;
            // stejný název jako v admin/cron.php, měsíc podle začátku okna
            $log        = sprintf('logy/cron/cron-%s-%s.log', $termin->job, $termin->okno->od->format('Y-m'));
            $logy[$log] = $log;
        }
        $seznam     = implode("\n\n", $polozky);
        $seznamJobu = implode(' a ', $joby);
        $seznamLogu = implode(', ', $logy);
        $skupina    = self::SKUPINA;

        // Text mail jde přes strip_tags(), nic v něm proto nesmí vypadat jako HTML značka (třeba <job>).
        $text = <<<TEXT
            Automatický job ve svém termínu neproběhl, nebo nedoběhl do konce:

            {$seznam}

            Joby s ?job= plánuje ručně cron-job.org (https://console.cron-job.org). Zkontroluj, že je tam {$seznamJobu} (s klíčem key a cas=now) a spouští se aspoň jednou denně, jinak job neproběhne ani příští rok.
            Když tam job je, spadl nebo nedoběhl: měl přijít mail o pádu cronu a stopa bude v logu cronu ({$seznamLogu}).

            Na každý zmeškaný termín upozorní hodinový cron jen jednou (hromadne_akce_log, skupina {$skupina}).
            TEXT;

        $adresati = array_values(array_unique([
            ...(Uzivatel::cfosEmaily() ?: ['info@gamecon.cz']),
            'it@gamecon.cz',
        ]));

        (new GcMail($this->systemoveNastaveni))
            ->adresati($adresati)
            ->predmet('!!IMPORTANT!! cron zmeškal termín: ' . implode(', ', array_map(
                static fn (HlidanyTermin $termin) => $termin->nazev,
                $zmeskane,
            )))
            ->text($text)
            ->odeslat(GcMail::FORMAT_TEXT);
    }
}
