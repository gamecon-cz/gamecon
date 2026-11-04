<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Version of a static file for its URL (?version=…), so that the browser caches it until it changes.
 * It is a hash of the content, not the modification time: a deploy rewrites every file, but the browser
 * should download again only those that changed. Hashing the few files of a page takes a fraction
 * of a millisecond.
 */
class VerzeSouboru
{
    /**
     * @var string
     */
    private $adresar;
    /**
     * @var string
     */
    private $pripona;

    public function __construct(string $adresar, string $pripona)
    {
        $this->adresar = rtrim($adresar, '/');
        $this->pripona = ltrim($pripona, '.');
    }

    /**
     * For templates, {cssVersions.styl} is the version of styl.css.
     *
     * @param array<mixed> $arguments not used
     */
    public function __call(string $nazev, array $arguments): string
    {
        return $this->getVerze($nazev);
    }

    /**
     * @param string $soubor name of a file in the directory, with or without the extension; a path is cut to the name
     */
    public function getVerze(string $soubor): string
    {
        $nazev = basename($soubor, '.' . $this->pripona);
        $cesta = $this->adresar . '/' . $nazev . '.' . $this->pripona;
        if (file_exists($cesta)) {
            return md5_file($cesta);
        }

        return uniqid('neznamySoubor-' . basename($cesta) . '-', true);
    }
}
