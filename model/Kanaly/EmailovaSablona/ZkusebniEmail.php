<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * A test of an e-mail text for the admin who is editing it: the text as it stands in the editor, saved or not,
 * as it would be saved, with the example values the preview shows.
 */
class ZkusebniEmail
{
    public const PREFIX_PREDMETU = '[TEST] ';

    public static function zGlobals(): self
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();

        return new self(
            $systemoveNastaveni,
            new EmailovaSablonaVykreslovac(new SystemoveHodnotyProSablony($systemoveNastaveni)),
            new EmailovaSablonaRepository(),
        );
    }

    public function __construct(
        private readonly SystemoveNastaveni         $systemoveNastaveni,
        private readonly EmailovaSablonaVykreslovac $vykreslovac,
        private readonly EmailovaSablonaRepository  $repository,
    ) {
    }

    /**
     * The message carries no template code, so the Odesláno column of the e-mail list does not count it
     * among what went out to the participants.
     */
    public function odesli(
        EmailovaSablonaDefinice $definice,
        string                  $predmet,
        string                  $text,
        string                  $adresat,
    ): VysledekZkusebnihoEmailuEnum {
        [$predmet, $text] = $this->repository->normalizuj($predmet, $text);
        $vykresleno       = $this->vykreslovac->vykresli($definice, $predmet, $text, $definice->ukazky());

        $mail = (new GcMail($this->systemoveNastaveni))
            ->adresat($adresat)
            ->predmet(self::PREFIX_PREDMETU . $vykresleno->predmet)
            ->text($vykresleno->textProEmail())
            ->format($vykresleno->format);
        $jenDoSouboru = $mail->adresatiDoSouboru() !== [];

        if (!$mail->odeslat()) {
            return VysledekZkusebnihoEmailuEnum::NEODESLANO;
        }

        return $jenDoSouboru
            ? VysledekZkusebnihoEmailuEnum::JEN_DO_SOUBORU
            : VysledekZkusebnihoEmailuEnum::ODESLANO;
    }
}
