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
}
