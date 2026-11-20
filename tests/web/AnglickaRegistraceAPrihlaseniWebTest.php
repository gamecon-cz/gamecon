<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

/**
 * Registrace účtu, přihlášení a obnova hesla pod /en/ přes skutečný Apache: anglicky, bez českých zbytků,
 * a česká verze beze změny.
 */
class AnglickaRegistraceAPrihlaseniWebTest extends AbstractTestWeb
{
    // AbstractTestWeb zakládá role mimo transakci, další webový test by je zakládal znovu
    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    public function testRegistracePodAnglictinouJeAnglicky(): void
    {
        $odpoved = $this->stahniVerejnouStranku('web/en/registrace');

        self::assertSame(200, $odpoved['status']);
        $telo = $odpoved['telo'];
        self::assertStringContainsString('<html lang="en">', $telo);
        self::assertStringNotContainsString('<div lang="cs">', $telo, 'Registrace je přeložená, obsah není česky.');
        self::assertStringContainsString('hreflang="cs" href="' . URL_WEBU . '/registrace"', $telo);
        self::assertMatchesRegularExpression('~<title>[^<]*Sign up~', $telo);
        $text = $this->textObsahu($telo);
        foreach ([
            'Sign up',
            'Already have an account? log in',
            'Why do we need your personal details?',
            'E-mail address',
            'Permanent address',
            'Street and house number',
            'Postcode',
            '(other country)',
            'Valid identity document',
            'Nickname only',
            'I agree to the processing of my personal data',
            'You can find the full legal text here.',
        ] as $anglicky) {
            self::assertStringContainsString($anglicky, $text);
        }
        self::assertStringContainsString('placeholder="e.g. +44 20 7946 0958"', $telo);
        self::assertStringContainsString('value="Just create an account"', $telo);
        $this->assertBezCeskehoTextu($text);
    }

    public function testPrihlaseniPodAnglictinouJeAnglicky(): void
    {
        $odpoved = $this->stahniVerejnouStranku('web/en/prihlaseni');

        self::assertSame(200, $odpoved['status']);
        $telo = $odpoved['telo'];
        self::assertStringContainsString('<html lang="en">', $telo);
        self::assertStringNotContainsString('<div lang="cs">', $telo);
        $text = $this->textObsahu($telo);
        foreach (['Log in', 'E-mail address', 'forgotten password', 'Password', 'Stay logged in', 'Log in with a QR code', 'Don’t have an account? sign up'] as $anglicky) {
            self::assertStringContainsString($anglicky, $text);
        }
        self::assertStringContainsString('<input type="submit" value="Log in"', $telo);
        $this->assertBezCeskehoTextu($text);
    }

    public function testPrihlaseniQrKodemPodAnglictinouJeAnglicky(): void
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
        self::assertNotFalse($cookieFile);
        try {
            // tlačítko Přihlásit QR kódem, Altchu nepotřebuje
            $zalozeni = $this->vRelaci($cookieFile, 'web/en/prihlaseni', [
                'qrKod' => '1',
            ]);
            self::assertSame(303, $zalozeni['kod'], 'Formulář musí projít ochranou proti CSRF, jinak test nic neověří.');

            $stranka = $this->vRelaci($cookieFile, 'web/en/prihlaseni');
        } finally {
            unlink($cookieFile);
        }

        self::assertSame(200, $stranka['kod']);
        self::assertStringContainsString('data-prihlaseni-qr', $stranka['telo'], 'Stránka má ukazovat QR kód.');
        self::assertStringContainsString('data-zprava-vyprselo="The code has expired, generate a new one."', $stranka['telo']);
        self::assertStringContainsString('data-po-prihlaseni="' . URL_WEBU . '/en/prihlaska"', $stranka['telo']);
        $text = $this->textObsahu($stranka['telo']);
        foreach (['Logging in with a QR code', 'use the Scan QR code button in Settings', 'On the phone, choose this number:', 'The code is valid for 2 minutes.', 'New code', 'Log in with password'] as $anglicky) {
            self::assertStringContainsString($anglicky, $text);
        }
        $this->assertBezCeskehoTextu($text);
    }

    public function testSchvaleniPrihlaseniZarizeniPodAnglictinouJeAnglicky(): void
    {
        $odpoved = $this->stahniVerejnouStranku('web/en/prihlasit-zarizeni?kod=neznamy');

        self::assertSame(200, $odpoved['status']);
        $text = $this->textObsahu($odpoved['telo']);
        self::assertStringContainsString('To approve it, you need to be logged in to the GameCon website on this phone.', $text);
        self::assertStringContainsString('The code is only valid for 2 minutes.', $text);
        $this->assertBezCeskehoTextu($text);
    }

    public function testObnovaHeslaPodAnglictinouJeAnglicky(): void
    {
        $zadost = $this->stahniVerejnouStranku('web/en/zapomenute-heslo');

        self::assertSame(200, $zadost['status']);
        self::assertStringNotContainsString('<div lang="cs">', $zadost['telo']);
        self::assertStringContainsString('<title>Forgotten password – GameCon</title>', $zadost['telo']);
        $text = $this->textObsahu($zadost['telo']);
        self::assertStringContainsString('Enter your e-mail address. We’ll send you a link where you can set a new password.', $text);
        self::assertStringContainsString('My e-mail', $text);
        self::assertStringContainsString('value="Send link"', $zadost['telo']);
        $this->assertBezCeskehoTextu($text);

        $neplatnyOdkaz = $this->stahniVerejnouStranku('web/en/obnova-hesla?token=neplatny');

        self::assertSame(400, $neplatnyOdkaz['status']);
        $text = $this->textObsahu($neplatnyOdkaz['telo']);
        self::assertStringContainsString('Invalid link', $text);
        self::assertStringContainsString('Send a new link', $text);
        $this->assertBezCeskehoTextu($text);
    }

    public function testCeskeStrankyZustavajiCesky(): void
    {
        $ocekavane = [
            'web/registrace'           => ['Registrace', 'Již mám účet přihlásit se', 'Adresa trvalého pobytu', '(jiný stát)', 'Souhlasím se zpracováním osobních údajů'],
            'web/prihlaseni'           => ['Přihlášení', 'zapomenuté heslo', 'Trvale přihlásit', 'Přihlásit QR kódem', 'Nemám účet registrovat'],
            'web/zapomenute-heslo'     => ['Zapomenuté heslo', 'Zadej svůj e-mail. Pošleme ti odkaz, na kterém si nastavíš nové heslo.', 'Můj e-mail'],
            'web/obnova-hesla?token=x' => ['Neplatný odkaz', 'Poslat nový odkaz'],
        ];
        foreach ($ocekavane as $cesta => $texty) {
            $odpoved = $this->stahniVerejnouStranku($cesta);
            self::assertStringContainsString('<html lang="cs">', $odpoved['telo'], $cesta);
            $text = $this->textObsahu($odpoved['telo']);
            foreach ($texty as $cesky) {
                self::assertStringContainsString($cesky, $text, $cesta);
            }
        }
    }

    /**
     * Text od formuláře do konce stránky bez značek, skriptů, komentářů, patičky a lišty profileru, mezery sloučené.
     */
    private function textObsahu(string $telo): string
    {
        $trida = strpos($telo, 'formular_stranka');
        self::assertNotFalse($trida, 'Na stránce chybí formulář.');
        // od začátku značky s třídou, uprostřed značky by strip_tags() zmátly uvozovky
        $zacatek = strrpos(substr($telo, 0, $trida), '<');
        self::assertNotFalse($zacatek);
        $obsah = substr($telo, $zacatek);
        foreach (['<div class="paticka">', '<div class="profilInfo"', '</body>'] as $konec) {
            $pozice = strpos($obsah, $konec);
            if ($pozice !== false) {
                $obsah = substr($obsah, 0, $pozice);
            }
        }
        $obsah = (string) preg_replace(['~<script\b.*?</script>~s', '~<style\b.*?</style>~s', '~<!--.*?-->~s'], '', $obsah);

        return trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($obsah))));
    }

    /**
     * Požadavek s cookies relace v souboru, s $post jako odeslaný formulář stránky.
     *
     * @param array<string, string>|null $post
     *
     * @return array{kod: int, telo: string}
     */
    private function vRelaci(string $cookieFile, string $cesta, ?array $post = null): array
    {
        self::vyresDnsPredPrvniZadosti();
        $curl = curl_init('http://localhost/' . $cesta);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieFile);
        $this->setLocalServerCookieWithGameconTestDb($curl);
        if ($post !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
            // ochrana proti CSRF (omezCsrf()) chce odkazujícího ze stejného webu
            curl_setopt($curl, CURLOPT_REFERER, 'http://localhost/' . $cesta);
        }
        $telo = (string) curl_exec($curl);
        $kod = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        unset($curl); // až teď curl zapíše cookies do souboru

        return [
            'kod'  => $kod,
            'telo' => $telo,
        ];
    }

    private function assertBezCeskehoTextu(string $text): void
    {
        self::assertDoesNotMatchRegularExpression(
            '~[ěščřžýáíéóúůťďňĚŠČŘŽÝÁÍÉÓÚŮŤĎŇ]~u',
            // adresa ubytovatele (koleje VŠB) je vlastní jméno
            str_replace(['Studentská', 'Ostrava-Poruba'], '', $text),
            'Na anglické stránce nezůstal český text.',
        );
    }
}
