<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Chyba;
use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;
use Imagick;

class UserController
{
    public static function zpracujPotvrzeniRodicu(\Uzivatel $uzivatel): bool
    {
        if (!isset($_FILES['potvrzeniRodicu']) || empty($_FILES['potvrzeniRodicu']['tmp_name'])) {
            return false;
        }
        $f = @fopen($_FILES['potvrzeniRodicu']['tmp_name'], 'rb');
        if (!$f) {
            throw new Chyba("Soubor '{$_FILES['potvrzeniRodicu']['name']}' se nepodařilo načíst");
        }
        if (mime_content_type($f) === 'application/pdf') {
            $targetResource = fopen($uzivatel->cestaKSouboruSPotvrzenimRodicu('pdf'), 'wb');
            if ($targetResource === false) {
                throw new Chyba("Soubor '{$_FILES['potvrzeniRodicu']['name']}' se nepodařilo uložit");
            }
            fwrite($targetResource, fread($f, filesize($_FILES['potvrzeniRodicu']['tmp_name'])));
            fclose($targetResource);
        } else {
            $imagick = new Imagick();

            $imageRead = false;
            try {
                $imageRead = $imagick->readImageFile($f);
            } catch (\Throwable $throwable) {
                trigger_error($throwable->getMessage(), E_USER_WARNING);
            }
            if (!$imageRead) {
                throw new Chyba("Soubor '{$_FILES['potvrzeniRodicu']['name']}' se nepodařilo přečíst. Je to obrázek nebo PDF?");
            }

            try {
                $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $imagick->setImageCompressionQuality(100);
            } catch (\Throwable $throwable) {
                trigger_error($throwable->getMessage(), E_USER_WARNING);
            }
            $imagick->writeImage($uzivatel->cestaKSouboruSPotvrzenimRodicu());
        }
        @fclose($f);

        $ted = new \DateTimeImmutable();
        $uzivatel->ulozPotvrzeniRodicuPridanoKdy($ted);

        return true;
    }

    public static function zpracujPotvrzeniProtiCovidu(\Uzivatel $uzivatel): bool
    {
        if (!isset($_FILES['potvrzeniProtiCovidu']) || empty($_FILES['potvrzeniProtiCovidu']['tmp_name'])) {
            return false;
        }
        $f = @fopen($_FILES['potvrzeniProtiCovidu']['tmp_name'], 'rb');
        if (!$f) {
            throw new Chyba("Soubor '{$_FILES['potvrzeniProtiCovidu']['name']}' se nepodařilo načíst");
        }
        $imagick = new Imagick();
        $imagick->setResolution(120, 120);

        $imageRead = false;
        try {
            $imageRead = $imagick->readImageFile($f);
        } catch (\Throwable $throwable) {
            trigger_error($throwable->getMessage(), E_USER_WARNING);
        }
        if (!$imageRead) {
            throw new Chyba("Soubor '{$_FILES['potvrzeniProtiCovidu']['name']}' se nepodařilo přečíst. Je to obrázek nebo PDF?");
        }

        try {
            $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $imagick->setImageCompressionQuality(100);
        } catch (\Throwable $throwable) {
            trigger_error($throwable->getMessage(), E_USER_WARNING);
        }
        $imagick->writeImage(WWW . '/soubory/systemove/potvrzeni/covid-19-' . $uzivatel->id() . '.png');

        $ted = new \DateTimeImmutable();
        $uzivatel->ulozPotvrzeniProtiCoviduPridanyKdy($ted);

        return true;
    }

    public static function covidFreePotvrzeniHtml(\Uzivatel $uzivatel, int $rok): string
    {
        $x = new XTemplate(__DIR__ . '/Templates/uzivatel-covid-potvrzeni.xtpl');
        $x->assign('a', $uzivatel->koncovkaDlePohlavi());
        if ($uzivatel->maNahranyDokladProtiCoviduProRok($rok)) {
            if ($uzivatel->maOverenePotvrzeniProtiCoviduProRok($rok, true)) {
                $x->assign(
                    'datumOvereniPotvrzeniProtiCovid',
                    (new DateTimeCz($uzivatel->potvrzeniProtiCoviduOverenoKdy()->format(DATE_ATOM)))->rozdilDni(new \DateTimeImmutable()),
                );
                $x->parse('covid.nahrano.overeno');
            } else {
                $x->assign('urlNaSmazaniPotvrzeni', $uzivatel->urlNaSmazaniPotrvrzeniVlastnikem());
                $x->parse('covid.nahrano.smazat');
            }
            $x->assign('urlNaPotvrzeniProtiCoviduProVlastnika', $uzivatel->urlNaPotvrzeniProtiCoviduProVlastnika());
            $x->assign(
                'datumNahraniPotvrzeniProtiCovid',
                (new DateTimeCz($uzivatel->potvrzeniProtiCoviduPridanoKdy()->format(DATE_ATOM)))->relativni(),
            );
            $x->parse('covid.nahrano');
        } else {
            if ($uzivatel->maOverenePotvrzeniProtiCoviduProRok($rok, true)) {
                $x->assign(
                    'datumOvereniPotvrzeniProtiCovid',
                    (new DateTimeCz($uzivatel->potvrzeniProtiCoviduOverenoKdy()->format(DATE_ATOM)))->relativni(),
                );
                $x->parse('covid.overenoBezDokladu');
            }
            $x->parse('covid.nahrat');
        }
        $x->parse('covid');

        return $x->text('covid');
    }
}
