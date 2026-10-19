<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\Exceptions\EmailovaSablonaNeniVyplnena;
use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Entry point for code that sends an e-mail: name the template and get a ready
 * {@see GcMail} with the subject and text as the admin has set them right now.
 */
class EmailoveSablony
{
    public static function zGlobals(): self
    {
        return new self(SystemoveNastaveni::zGlobals());
    }

    private readonly EmailovaSablonaVykreslovac $vykreslovac;

    public function __construct(
        private readonly SystemoveNastaveni         $systemoveNastaveni,
        private readonly EmailovaSablonaRepository $repository = new EmailovaSablonaRepository(),
    ) {
        $this->vykreslovac = new EmailovaSablonaVykreslovac(new SystemoveHodnotyProSablony($systemoveNastaveni));
    }

    /**
     * @param array<string, string|int|float|\Stringable> $promenne exactly those the template declares
     * @throws EmailovaSablonaNeniVyplnena
     */
    public function vykresli(string $kod, array $promenne): VykreslenaSablona
    {
        $definice = EmailoveSablonyKatalog::dej($kod);
        $sablona  = $this->vyplnenaSablona($kod);

        return $this->vykreslovac->vykresli($definice, $sablona->predmet, $sablona->text, $promenne);
    }

    private function vyplnenaSablona(string $kod): UlozenaEmailovaSablona
    {
        $sablona = $this->repository->dej($kod);
        if ($sablona === null || !$sablona->jeVyplnena()) {
            throw new EmailovaSablonaNeniVyplnena(
                "Šablona e-mailu '$kod' chybí nebo má prázdný předmět či text, e-mail se neodešle.",
            );
        }

        return $sablona;
    }

    /**
     * Subject only, without values for the text. May receive only variables the template allows in the subject.
     *
     * @param array<string, string|int|float|\Stringable> $promenne
     * @throws EmailovaSablonaNeniVyplnena
     */
    public function vykresliPredmet(string $kod, array $promenne = []): string
    {
        $definice = EmailoveSablonyKatalog::dej($kod);

        return $this->vykreslovac->vykresliPredmet($definice, $this->vyplnenaSablona($kod)->predmet, $promenne);
    }

    /**
     * The caller adds recipients and any attachments itself.
     *
     * @param array<string, string|int|float|\Stringable> $promenne
     * @throws EmailovaSablonaNeniVyplnena
     */
    public function vytvorMail(string $kod, array $promenne): GcMail
    {
        $vykresleno = $this->vykresli($kod, $promenne);

        return (new GcMail($this->systemoveNastaveni))
            ->sablona($kod)
            ->predmet($vykresleno->predmet)
            ->text(
                $vykresleno->format === GcMail::FORMAT_HTML
                    ? '<html><body>' . $vykresleno->text . '</body></html>'
                    : $vykresleno->text,
            )
            ->format($vykresleno->format);
    }
}
