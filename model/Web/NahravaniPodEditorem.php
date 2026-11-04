<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Obrázky a soubory ke stažení pod editorem stránky nebo novinky v administraci. Editor textu (@see \DbForm) se
 * odesílá AJAXem, takže obojí má vlastní formuláře, které stránku načtou znovu. Skript pro nahrávání po částech
 * a upozornění na neuložený text editoru má stránka jednou, ne v každém z bloků.
 */
final class NahravaniPodEditorem
{
    private const SKRIPT_NEULOZENEHO_TEXTU = __DIR__ . '/../../admin/files/neulozeny-text-editoru.js';

    public function __construct(
        private readonly ObrazkyObsahu $obrazky,
        private readonly SouboryObsahu $soubory,
    ) {
    }

    public static function proStranku(\Stranka $stranka, NahravaniPoCastech $poCastech): self
    {
        return self::zKontextu(KontextTextu::proStranku($stranka), $poCastech);
    }

    public static function proNovinku(\Novinka $novinka, NahravaniPoCastech $poCastech): self
    {
        return self::zKontextu(KontextTextu::proNovinku($novinka), $poCastech);
    }

    private static function zKontextu(KontextTextu $kontext, NahravaniPoCastech $poCastech): self
    {
        return new self(
            new ObrazkyObsahu($kontext->text, $kontext->adresarProNove, $kontext->kCemu),
            new SouboryObsahu($kontext->text, $kontext->adresarProNove, $kontext->kCemu, poCastech: $poCastech),
        );
    }

    /**
     * Obslouží formuláře z @see html(). Po akci přesměruje zpět a skončí, jinak nedělá nic.
     */
    public function zpracujPost(): void
    {
        $this->obrazky->zpracujPost();
        $this->soubory->zpracujPost();
    }

    public function html(): string
    {
        return $this->obrazky->html()
            . $this->soubory->html()
            . $this->soubory->getSkript()
            . '<script src="files/neulozeny-text-editoru.js?v=' . (string) md5_file(self::SKRIPT_NEULOZENEHO_TEXTU) . '"></script>';
    }
}
