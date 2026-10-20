<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\UpominkaVlastniZneni;

/**
 * Looks again at texts that are already saved. They passed validation on save, but a setting
 * may stop being public later and the value would silently drop out of the e-mail.
 * A missing or blank template is reported too, because its e-mail cannot be sent at all.
 *
 * @see EmailovaSablonaValidator::chybySystemovychHodnot()
 */
class EmailovaSablonaKontrola
{
    public function __construct(
        private readonly EmailovaSablonaRepository $repository,
        private readonly EmailovaSablonaValidator  $validator,
        private readonly UpominkaVlastniZneni      $upominkaVlastniZneni,
        private readonly int                       $rocnik,
    ) {
    }

    public static function zNastaveni(SystemoveNastaveni $systemoveNastaveni): self
    {
        return new self(
            new EmailovaSablonaRepository(),
            new EmailovaSablonaValidator(new SystemoveHodnotyProSablony($systemoveNastaveni)),
            new UpominkaVlastniZneni(),
            $systemoveNastaveni->rocnik(),
        );
    }

    /**
     * @return list<ProblemSablony>
     */
    public function najdiProblemy(): array
    {
        $problemy = [];

        foreach (EmailoveSablonyKatalog::vsechny() as $definice) {
            $kod     = $definice->kod;
            $sablona = $this->repository->dej($kod);
            if ($sablona === null || !$sablona->jeVyplnena()) {
                $problemy[] = new ProblemSablony(
                    kod: $kod,
                    nazev: $definice->nazev,
                    odkaz: self::odkazNaEditaci($kod),
                    chyby: ['Předmět nebo text chybí.'],
                    zabranujeOdeslani: true,
                );
                continue;
            }
            $chyby = $this->validator->chybySystemovychHodnot($sablona->predmet . "\n" . $sablona->text);
            if ($chyby !== []) {
                $problemy[] = new ProblemSablony(
                    kod: $kod,
                    nazev: $definice->nazev,
                    odkaz: self::odkazNaEditaci($kod),
                    chyby: $chyby,
                );
            }
        }

        $vlastniZneni = $this->upominkaVlastniZneni->dejZneni($this->rocnik);
        if ($vlastniZneni !== null) {
            $chyby = $this->validator->chybySystemovychHodnot($vlastniZneni->predmet . "\n" . $vlastniZneni->text);
            if ($chyby !== []) {
                $problemy[] = new ProblemSablony(
                    kod: KodEmailoveSablonyEnum::UPOMINKA_VLASTNI,
                    nazev: "Upomínky dlužníkům, vlastní znění pro ročník {$this->rocnik}",
                    odkaz: 'finance/upominky-dluzniku?zneni',
                    chyby: $chyby,
                );
            }
        }

        return $problemy;
    }

    public static function odkazNaEditaci(KodEmailoveSablonyEnum $kod): string
    {
        return 'web/emailove-sablony?kod=' . rawurlencode($kod->value);
    }
}
