<?php

declare(strict_types=1);

namespace Gamecon\Web;

final class LogoUpload
{
    private const POVOLENE_PRIPONY = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    private const ZAKAZANE_SVG_ELEMENTY = [
        'script',
        'foreignObject',
        'iframe',
        'object',
        'embed',
        'audio',
        'video',
        'image',
        'use',
        'a',
        'animate',
        'animateMotion',
        'animateTransform',
        'set',
    ];

    public static function jePodporovanaPripona(string $pripona): bool
    {
        return in_array(mb_strtolower($pripona), self::POVOLENE_PRIPONY, true);
    }

    public static function hostZUrlProNazevSouboru(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        return preg_replace('~[^\w.\-]~u', '', trim($host, " \t."));
    }

    public static function validujSvgSoubor(string $cestaKSouboru): ?string
    {
        $obsah = file_get_contents($cestaKSouboru);
        if (!is_string($obsah) || trim($obsah) === '') {
            return 'SVG soubor je prázdný nebo nečitelný.';
        }

        if (preg_match('~<!(DOCTYPE|ENTITY)\b|<\?xml-stylesheet\b~i', $obsah)) {
            return 'SVG soubor obsahuje nepovolené XML konstrukce.';
        }

        $puvodniLibxmlErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();

        try {
            $nacteno = $dom->loadXML($obsah, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($puvodniLibxmlErrors);
        }

        if (!$nacteno) {
            return 'SVG soubor nemá platný XML formát.';
        }

        $koren = $dom->documentElement;
        if (
            !$koren
            || mb_strtolower($koren->localName) !== 'svg'
            || $koren->namespaceURI !== 'http://www.w3.org/2000/svg'
        ) {
            return 'Soubor není platné SVG.';
        }

        /** @var \DOMElement $element */
        foreach ($dom->getElementsByTagName('*') as $element) {
            $nazevElementu = mb_strtolower($element->localName);
            if (in_array($nazevElementu, self::ZAKAZANE_SVG_ELEMENTY, true)) {
                return "SVG soubor obsahuje nepovolený prvek <{$nazevElementu}>.";
            }

            /** @var \DOMAttr $atribut */
            foreach ($element->attributes as $atribut) {
                $nazevAtributu = mb_strtolower($atribut->localName);
                $plnyNazevAtributu = mb_strtolower($atribut->name);
                $hodnota = trim($atribut->value);

                if (str_starts_with($nazevAtributu, 'on')) {
                    return 'SVG soubor obsahuje nepovolené event atributy.';
                }

                if ($nazevAtributu === 'href' || $plnyNazevAtributu === 'xlink:href') {
                    return 'SVG soubor obsahuje nepovolené odkazy.';
                }

                if (preg_match('~(?:javascript|vbscript|data):~i', $hodnota)) {
                    return 'SVG soubor obsahuje nepovolený odkaz nebo datové URI.';
                }

                if (stripos($hodnota, 'expression(') !== false) {
                    return 'SVG soubor obsahuje nepovolený CSS výraz.';
                }

                if (
                    stripos($hodnota, 'url(') !== false
                    && !preg_match('~url\(\s*[\'"]?#~i', $hodnota)
                ) {
                    return 'SVG soubor obsahuje externí URL reference.';
                }
            }
        }

        return null;
    }
}
