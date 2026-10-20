<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EmailoveSablonyKatalog;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Kanaly\EmailovaSablona\VysledekZkusebnihoEmailuEnum;
use Gamecon\Kanaly\EmailovaSablona\ZkusebniEmail;
use Gamecon\Kanaly\MailLogger;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

class ZkusebniEmailTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function odesleTextSUkazkovymiHodnotamiNaZadanouAdresuSPredmetemOznacenymJakoTest(): void
    {
        $adresat = 'zkusebni.' . uniqid('', true) . '@example.test';
        $definice = EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO);
        $aktivita = $definice->promenna('aktivita')->ukazka;
        $cas = $definice->promenna('cas')->ukazka;

        $vysledek = $this->zkusebniEmail()->odesli($definice, 'Volno na %aktivita%', 'Přijď na %aktivita% v %cas%.', $adresat);

        self::assertNotSame(VysledekZkusebnihoEmailuEnum::NEODESLANO, $vysledek);
        $mail = $this->posledniMailPro($adresat);
        self::assertStringContainsString('[TEST] Volno na ' . $aktivita, $mail['predmet']);
        // GcMail may put an environment marker at the start of the body, hence the .*
        self::assertMatchesRegularExpression(
            '~^<html><body>.*Přijď na ' . preg_quote($aktivita, '~') . ' v ' . preg_quote($cas, '~') . '\.</body></html>$~su',
            $mail['telo_html'],
            'HTML e-mail se pošle jako HTML dokument',
        );
        self::assertSame([$adresat], json_decode($mail['adresati'], true));
    }

    /**
     * @test
     */
    public function textovyEmailSeNeobalujeDoHtml(): void
    {
        $adresat = 'zkusebni.text.' . uniqid('', true) . '@example.test';

        $this->zkusebniEmail()->odesli(EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::ODHLASENI_Z_GAMECONU), 'Zkouška', 'Jen text, bez symbolů.', $adresat);

        $mail = $this->posledniMailPro($adresat);
        self::assertSame('text', $mail['format']);
        self::assertNull($mail['telo_html']);
    }

    /**
     * @test
     */
    public function zkusebniEmailSeNezapocitaDoOdeslanychZTextu(): void
    {
        $adresat = 'zkusebni.pocet.' . uniqid('', true) . '@example.test';

        $this->zkusebniEmail()->odesli(EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO), 'Zkouška', 'Zkouška.', $adresat);

        $mail = $this->posledniMailPro($adresat);
        self::assertNull($mail['sablona'], 'Sloupec Odesláno v seznamu e-mailů počítá jen e-maily, které opravdu odešly účastníkům');
    }

    /**
     * @test
     */
    public function naProstrediKdeSeEmailyJenZapisujiDoSouboruToVysledekPrizna(): void
    {
        // Tests define MAILY_DO_SOUBORU and no MAILY_ROLIM, so on this environment every recipient only goes to the file
        $adresat = 'zkusebni.soubor.' . uniqid('', true) . '@example.test';

        $vysledek = $this->zkusebniEmail()->odesli(EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO), 'Zkouška', 'Zkouška.', $adresat);

        self::assertSame(VysledekZkusebnihoEmailuEnum::JEN_DO_SOUBORU, $vysledek, 'Admin se nesmí dozvědět, že test odešel, když jen skončil v souboru');
    }

    /**
     * @test
     */
    public function textSeUpravujeJakoPriUlozeni(): void
    {
        $adresat = 'zkusebni.upravy.' . uniqid('', true) . '@example.test';
        $definice = EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO);
        $aktivita = $definice->promenna('aktivita')->ukazka;

        $this->zkusebniEmail()->odesli($definice, "  Zkouška\r\n", "Přijď na %aktivita%.\r\n\r\n", $adresat);

        $mail = $this->posledniMailPro($adresat);
        self::assertMatchesRegularExpression(
            '~Přijď na ' . preg_quote($aktivita, '~') . '\.</body></html>$~u',
            $mail['telo_html'],
            'Prázdné řádky na konci se při ukládání ořežou, takže ani zkušební e-mail nemá končit odřádkováním',
        );
        self::assertSame('[TEST] Zkouška', $this->bezPrefixuProstredi($mail['predmet']));
    }

    private function bezPrefixuProstredi(string $predmet): string
    {
        $prefix = SystemoveNastaveni::zGlobals()->prefixPodleProstredi();

        return $prefix !== '' && str_starts_with($predmet, $prefix . ' ')
            ? substr($predmet, strlen($prefix) + 1)
            : $predmet;
    }

    private function zkusebniEmail(): ZkusebniEmail
    {
        return ZkusebniEmail::zGlobals();
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
}
