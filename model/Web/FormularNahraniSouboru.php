<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Formulář pro nahrání souboru, stejný na stránce Soubory a v panelu pod editorem stránky: soubor přijde celý v
 * `$_FILES`, nebo ho prohlížeč poslal po částech (@see NahravaniPoCastech) a formulář místo něj pošle `nahranoId`.
 */
final class FormularNahraniSouboru
{
    public const ZPRAVA_NAHRANO = 'Soubor nahrán.';

    private const POST_ID_NAHRANI = 'nahranoId';

    /**
     * @param string $postSoubor název pole se souborem, když přichází celý
     * @param NahravaniPoCastech|null $poCastech s ním se velký soubor pošle po částech; bez něj se posílá celý
     */
    public function __construct(
        private readonly string $postSoubor,
        private readonly ?NahravaniPoCastech $poCastech = null,
    ) {
    }

    public static function textZPostu(string $nazev): string
    {
        $hodnota = post($nazev);

        return is_string($hodnota)
            ? trim($hodnota)
            : '';
    }

    /**
     * Cesta se ve formuláři posílá jako text, název souboru, který se v textu nezachová (kódování z FTP,
     * mezera na okraji), by se po cestě zpátky nenašel.
     */
    public static function nazevPrezijeFormular(string $cesta): bool
    {
        return mb_check_encoding($cesta, 'UTF-8') && $cesta === trim($cesta);
    }

    public static function zpravaONahrazeni(string $cesta): string
    {
        return sprintf(
            'Soubor %s nahrán. Kdo ho už viděl, může mít chvíli v prohlížeči ještě starou verzi.',
            htmlspecialchars(basename($cesta)),
        );
    }

    /**
     * Atributy formuláře, které zapnou posílání po částech.
     */
    public function getAtributy(): string
    {
        return $this->poCastech === null
            ? ''
            : ' data-nahravani-po-castech data-nahravani-prah="' . $this->poCastech->getPrahBajtu() . '"';
    }

    /**
     * Skript, který velký soubor pošle po částech; stránka ho potřebuje jednou, ať má formulářů kolik chce.
     */
    public function getSkript(): string
    {
        return $this->poCastech === null
            ? ''
            : '<script src="files/nahravani-po-castech.js?v='
                . (string) md5_file(__DIR__ . '/../../admin/files/nahravani-po-castech.js')
                . '"></script>';
    }

    /**
     * Mezisoubor poslaný po částech se smaže po každé akci, i odmítnuté dřív, než na něj přišla řada (špatná složka,
     * soubor, který mezitím zmizel): `id` je na jedno použití a nesmazaný by zabíral jedno z rozpracovaných nahrávání.
     *
     * @template T
     * @param callable(): T $akce
     * @return T
     */
    public function sUklidem(callable $akce): mixed
    {
        $id = self::textZPostu(self::POST_ID_NAHRANI);

        return $id === '' || $this->poCastech === null
            ? $akce()
            : $this->poCastech->sUklidem($id, $akce);
    }

    /**
     * Soubor z formuláře, který prošel kontrolou služby: celý z `$_FILES`, nebo poslaný po částech, to pozná podle `nahranoId`.
     */
    public function nahranySoubor(NahravaniObrazku $nahravani): NahranyObrazek
    {
        $id = self::textZPostu(self::POST_ID_NAHRANI);
        if ($id === '') {
            return $nahravani->overNahranySoubor($_FILES[$this->postSoubor] ?? null);
        }

        return ($this->poCastech ?? throw new NahravaniObrazkuException('Nahrávání po částech tu není k dispozici.'))
            ->over($id, $nahravani);
    }
}
