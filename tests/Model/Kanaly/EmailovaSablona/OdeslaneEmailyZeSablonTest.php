<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaRepository;
use Gamecon\Kanaly\MailLogger;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;

/**
 * The whole path, as production code walks it: from the place where the e-mail is sent to the log of sent mails.
 */
class OdeslaneEmailyZeSablonTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function rychlaRegistraceOdesleOdkazNaPotvrzeniSKodemUzivatele(): void
    {
        $email = 'rychloreg.' . uniqid('', true) . '@example.test';

        $id = \Uzivatel::rychloregistrace(
            SystemoveNastaveni::zGlobals(),
            [
                Sql::EMAIL1_UZIVATELE => $email,
            ],
            [
                'informovat' => true,
            ],
        );

        $kod = \Uzivatel::zId($id)->rawDb()[Sql::RANDOM];
        $mail = $this->posledniMailPro($email);

        self::assertSame('Registrace na GameCon.cz', $this->bezPrefixuProstredi($mail['predmet']));
        self::assertStringContainsString(
            '<a href="' . URL_WEBU . '/potvrzeni-registrace/' . $kod . '">',
            $mail['telo_html'],
        );
    }

    /**
     * @test
     */
    public function uzivatelskyUpravenyTextSeOdesleSeStejnymiHodnotami(): void
    {
        (new EmailovaSablonaRepository())->uloz(
            'rychla_registrace',
            'Vítej na %ROCNIK%',
            'Potvrď: %odkaz_k_potvrzeni% (a %%)',
            \Uzivatel::SYSTEM,
        );
        $email = 'upraveny.' . uniqid('', true) . '@example.test';

        $id = \Uzivatel::rychloregistrace(
            SystemoveNastaveni::zGlobals(),
            [
                Sql::EMAIL1_UZIVATELE => $email,
            ],
            [
                'informovat' => true,
            ],
        );

        $kod = \Uzivatel::zId($id)->rawDb()[Sql::RANDOM];
        $mail = $this->posledniMailPro($email);

        self::assertSame('Vítej na ' . ROCNIK, $this->bezPrefixuProstredi($mail['predmet']));
        self::assertStringContainsString(
            'Potvrď: ' . URL_WEBU . '/potvrzeni-registrace/' . $kod . ' (a %)',
            $mail['telo_html'],
            'Odeslat se musí to, co je v databázi, ne původní znění z kódu',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function posledniMailPro(string $email): array
    {
        $logger = new MailLogger(LOGY . '/maily.sqlite');
        $nalezene = $logger->najdi($email, limit: 1);
        self::assertNotEmpty($nalezene, "Mail pro {$email} se nezalogoval");

        return $logger->detail((int) $nalezene[0]['id']);
    }

    /**
     * In tests the subject may start with an environment marker that GcMail adds.
     */
    private function bezPrefixuProstredi(string $predmet): string
    {
        $prefix = SystemoveNastaveni::zGlobals()->prefixPodleProstredi();

        return $prefix !== '' && str_starts_with($predmet, $prefix . ' ')
            ? substr($predmet, strlen($prefix) + 1)
            : $predmet;
    }
}
