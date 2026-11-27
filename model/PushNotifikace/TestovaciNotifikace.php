<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Předpřipravené notifikace pro zkoušení z adminu (stránka Testovací notifikace): jedno tlačítko, jedna notifikace.
 * Texty jsou vzorky s vymyšlenou aktivitou a každá má v nadpisu „(test)“, aby ji skutečný účastník nevzal vážně.
 */
enum TestovaciNotifikace: string
{
    case PRIPOMINKA_AKTIVITY = 'pripominka-aktivity';
    case ZRUSENI_AKTIVITY = 'zruseni-aktivity';
    case ZMENA_AKTIVITY = 'zmena-aktivity';
    case OZNAMENI_PORADATELU = 'oznameni-poradatelu';
    case UVOLNENE_MISTO = 'uvolnene-misto';
    case BEZ_ODKAZU = 'bez-odkazu';
    case DLOUHY_TEXT = 'dlouhy-text';
    case NIZKA_URGENCE = 'nizka-urgence';

    public const string OZNACENI = '(test)';

    public function getPopisek(): string
    {
        return match ($this) {
            self::PRIPOMINKA_AKTIVITY => 'Připomínka aktivity',
            self::ZRUSENI_AKTIVITY    => 'Zrušení aktivity',
            self::ZMENA_AKTIVITY      => 'Změna aktivity',
            self::OZNAMENI_PORADATELU => 'Oznámení pořadatelů',
            self::UVOLNENE_MISTO      => 'Uvolněné místo',
            self::BEZ_ODKAZU          => 'Bez odkazu',
            self::DLOUHY_TEXT         => 'Dlouhý text',
            self::NIZKA_URGENCE       => 'Nízká urgence',
        };
    }

    /**
     * Co se tlačítkem ověřuje, pro toho, kdo zkouší.
     */
    public function getCoOveruje(): string
    {
        return match ($this) {
            self::PRIPOMINKA_AKTIVITY => 'Hlavní scénář: kliknutí otevře Můj program. Po čtvrthodině bez spojení už nedorazí.',
            self::ZRUSENI_AKTIVITY    => 'Důležitá zpráva, nahradí připomínku téže aktivity.',
            self::ZMENA_AKTIVITY      => 'Nahrazení starší notifikace: pošli po připomínce, na zařízení má zůstat jen tahle.',
            self::OZNAMENI_PORADATELU => 'Běžná zpráva, kliknutí otevře úvodní stránku. Čeká na zařízení až den.',
            self::UVOLNENE_MISTO      => 'Kliknutí otevře program.',
            self::BEZ_ODKAZU          => 'Notifikace bez odkazu: kliknutí otevře úvodní stránku webu.',
            self::DLOUHY_TEXT         => 'Nejdelší povolený text: jak ho zařízení ořízne.',
            self::NIZKA_URGENCE       => 'Zpráva, která nespěchá: v úsporném režimu a na zamčeném telefonu může dorazit později.',
        };
    }

    /**
     * @throws ChybaPushNotifikace
     */
    public function getZprava(): ZpravaNotifikace
    {
        return match ($this) {
            self::PRIPOMINKA_AKTIVITY => ZpravaNotifikace::vytvor(
                TypNotifikace::PRIPOMINKA_AKTIVITY,
                self::oznacNadpis('GameCon – připomínka aktivity'),
                'Za 15 minut ti začíná LARP Poslední Pelikán. Vyraž na sraziště.',
                'program/muj',
            ),
            self::ZRUSENI_AKTIVITY => ZpravaNotifikace::vytvor(
                TypNotifikace::ZRUSENI_AKTIVITY,
                self::oznacNadpis('GameCon – aktivita zrušena'),
                'LARP Poslední Pelikán se dnes bohužel nekoná. Podívej se, co si vybrat místo něj.',
                'program/muj',
            ),
            self::ZMENA_AKTIVITY => ZpravaNotifikace::vytvor(
                TypNotifikace::ZMENA_AKTIVITY,
                self::oznacNadpis('GameCon – změna aktivity'),
                'LARP Poslední Pelikán začíná až v 16:00 a přesouvá se do místnosti B12.',
                'program/muj',
            ),
            self::OZNAMENI_PORADATELU => ZpravaNotifikace::vytvor(
                TypNotifikace::OZNAMENI_PORADATELU,
                self::oznacNadpis('GameCon – oznámení'),
                'Večerní program v hlavním sále začíná ve 20:00. Těšíme se na tebe.',
            ),
            self::UVOLNENE_MISTO => ZpravaNotifikace::vytvor(
                TypNotifikace::UVOLNENE_MISTO,
                self::oznacNadpis('GameCon – uvolnilo se místo'),
                'Na aktivitě LARP Poslední Pelikán, kterou sleduješ, se uvolnilo místo.',
                'program',
            ),
            self::BEZ_ODKAZU => ZpravaNotifikace::vytvor(
                TypNotifikace::OBECNA,
                self::oznacNadpis('GameCon – bez odkazu'),
                'Kliknutí na tuhle notifikaci otevře úvodní stránku webu.',
            ),
            self::DLOUHY_TEXT => ZpravaNotifikace::vytvor(
                TypNotifikace::OBECNA,
                self::oznacNadpis('GameCon – dlouhý text'),
                self::getDlouhyText(),
                'program/muj',
            ),
            self::NIZKA_URGENCE => ZpravaNotifikace::vytvor(
                TypNotifikace::OBECNA,
                self::oznacNadpis('GameCon – nespěchá'),
                'Tahle zpráva nespěchá. Telefon v úsporném režimu ji může ukázat později.',
                'program/muj',
                ttl: 60 * 60,
                urgence: Urgence::NIZKA,
            ),
        };
    }

    private static function oznacNadpis(string $nadpis): string
    {
        return $nadpis . ' ' . self::OZNACENI;
    }

    private static function getDlouhyText(): string
    {
        $veta = 'Tohle je schválně dlouhá zpráva, na které je vidět, kolik textu zařízení ukáže a kde ho ořízne. ';

        return rtrim(mb_substr(str_repeat($veta, 10), 0, ZpravaNotifikace::MAX_DELKA_TEXTU));
    }
}
