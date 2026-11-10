<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

/**
 * Archive keeps only the id of a YouTube video, the addresses are put together from it.
 * The player loads from youtube-nocookie.com only after a click, the thumbnail comes straight from YouTube.
 */
final class YoutubeOdkaz
{
    /**
     * \z, not $: $ matches also before a trailing newline, and parse_str() decodes %0A from the query
     */
    private const string VZOR_ID = '~^[A-Za-z0-9_-]{11}\z~';

    /**
     * Id of the video from any usual form of the address (youtu.be, watch?v=, embed, shorts, live, nocookie)
     * or a bare id; tracking parameters such as ?si= and whitespace are dropped.
     *
     * @return string|null null when the text is not a YouTube video
     */
    public static function getIdZOdkazu(string $odkaz): ?string
    {
        // an address has no whitespace, the spreadsheet has some around and inside the links
        $odkaz = (string) preg_replace('~[\s\x{00A0}]+~u', '', $odkaz);
        if ($odkaz === '') {
            return null;
        }
        if (preg_match(self::VZOR_ID, $odkaz)) {
            return $odkaz;
        }
        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $odkaz)) {
            $odkaz = 'https://' . $odkaz;
        }
        $casti = parse_url($odkaz);
        if ($casti === false || empty($casti['host'])) {
            return null;
        }
        $host = (string) preg_replace('~^(www|m|music)\.~', '', strtolower($casti['host']));
        $cesta = $casti['path'] ?? '';

        $id = null;
        if ($host === 'youtu.be') {
            $id = explode('/', trim($cesta, '/'))[0];
        } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
            if (preg_match('~^/(?:embed|shorts|live|v)/([^/]+)~', $cesta, $shoda)) {
                $id = $shoda[1];
            } elseif (rtrim($cesta, '/') === '/watch') {
                parse_str($casti['query'] ?? '', $parametry);
                $id = is_string($parametry['v'] ?? null)
                    ? $parametry['v']
                    : null;
            }
        }

        return $id !== null && preg_match(self::VZOR_ID, $id)
            ? $id
            : null;
    }

    public static function getUrlVidea(string $youtubeId): string
    {
        return 'https://www.youtube.com/watch?v=' . rawurlencode($youtubeId);
    }

    public static function getUrlPrehravace(string $youtubeId): string
    {
        return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($youtubeId) . '?autoplay=1';
    }

    public static function getUrlNahledu(string $youtubeId): string
    {
        return 'https://i.ytimg.com/vi/' . rawurlencode($youtubeId) . '/hqdefault.jpg';
    }
}
