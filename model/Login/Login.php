<?php declare(strict_types=1);

namespace Gamecon\Login;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\PrihlaseniQrKodem;
use Gamecon\Web\Info;
use Gamecon\XTemplate\XTemplate;

class Login
{
    public const LOGIN_INPUT_NAME    = 'loginNAdm';
    public const PASSWORD_INPUT_NAME = 'hesloNAdm';
    /**
     * Zaškrtnuté = trvalé přihlášení, viz docs/generated/prihlaseni-na-vice-zarizenich.md. Na rozdíl od webu
     * schválně nezaškrtnuté: admin se používá i na sdílených počítačích infopultu.
     */
    public const TRVALE_INPUT_NAME = 'trvaleNAdm';
    /** Přihlášení QR kódem, viz {@see PrihlaseniQrKodem} */
    public const QR_INPUT_NAME        = 'qrNAdm';
    public const QR_ZRUSIT_INPUT_NAME = 'qrZrusitNAdm';

    public function __construct(
        private readonly Info                        $info,
        private readonly SystemoveNastaveni $systemoveNastaveni,
    )
    {
        $this->info->nazev('Login', 'Administrace');
    }

    public function htmlLogin(): string
    {
        $loginTemplate = $this->loginTemplate();
        $loginTemplate->assign('headerPageInfo', $this->info->html());

        // Všechny typy: varování o zneužité nebo zastaralé cookie trvalého přihlášení vzniká v tomtéž požadavku
        // a radí, co dělat (Gamecon\Uzivatel\TrvalePrihlaseni), oznámení třeba po odhlášení.
        $tridyPodleTypu = [
            'chyby'    => 'alert-danger',
            'varovani' => 'alert-warning',
            'oznameni' => 'alert-success',
        ];
        foreach (\Chyba::vyzvedniPodleTypu() as $typ => $hlasky) {
            foreach ($hlasky as $hlaska) {
                $loginTemplate->assign([
                    'hlaska'      => $hlaska,
                    'tridaHlasky' => $tridyPodleTypu[$typ],
                ]);
                $loginTemplate->parse('login.hlaska');
            }
        }

        $ribbonLabel = $this->systemoveNastaveni->prostredi()->ribbonLabel();
        if ($ribbonLabel !== '') {
            $loginTemplate->assign('ribbonLabel', $ribbonLabel);
            $loginTemplate->parse('login.ribbon');
        }

        // Kdo zvolil přihlášení QR kódem, vidí kód, dokud ho nezruší (admin/scripts/prihlaseni.php) nebo dokud
        // kód neprošel. Jen žádost založenou v adminu, žádost webu má vlastní volbu „Trvale přihlásit“.
        $prihlaseniQrKodem = PrihlaseniQrKodem::zSession(doAdminu: true);
        $zadostQr = $prihlaseniQrKodem->getZivouZadost();
        if ($zadostQr !== null) {
            $skriptQr = __DIR__ . '/../../web/soubory/prihlaseni-qr.js';
            $loginTemplate->assign([
                'qrKod'          => $prihlaseniQrKodem->getQrKodSvg(),
                'cislo'          => $zadostQr['cislo'],
                'stavUrl'        => URL_ADMIN . '/api/prihlaseni-qr',
                'platnostMinut'  => intdiv(PrihlaseniQrKodem::PLATNOST_SEKUND, 60),
                // stejný skript jako přihlášení na webu, odtamtud
                'prihlaseniQrJs' => URL_WEBU . '/soubory/prihlaseni-qr.js?version=' . md5_file($skriptQr),
            ]);
            if ($zadostQr['trvale']) {
                $loginTemplate->parse('login.qr.trvale');
            }
            $loginTemplate->parse('login.qr');
        } else {
            $loginTemplate->parse('login.formular');
        }

        $loginTemplate->parse('login');
        return $loginTemplate->text('login');
    }

    private function loginTemplate(): XTemplate
    {
        $loginTemplate = new XTemplate(__DIR__ . '/templates/login.xtpl');

        $loginTemplate->assign([
            'base'              => URL_ADMIN . '/',
            'loginInputName'    => self::LOGIN_INPUT_NAME,
            'passwordInputName' => self::PASSWORD_INPUT_NAME,
            'trvaleInputName'   => self::TRVALE_INPUT_NAME,
            'qrInputName'       => self::QR_INPUT_NAME,
            'qrZrusitInputName' => self::QR_ZRUSIT_INPUT_NAME,
        ]);

        $this->pridejLokalniAssety($loginTemplate);

        return $loginTemplate;
    }

    private function pridejLokalniAssety(XTemplate $template)
    {
        static $localAssets = [
            'stylesheets' => [
                __DIR__ . '/../../admin/files/login/login.css',
                __DIR__ . '/../../admin/files/design/ribbon.css',
                __DIR__ . '/../../admin/files/design/hint.css',
            ],
            'javascripts' => [
                __DIR__ . '/../../admin/files/login/login.js',
            ],
        ];
        foreach ($localAssets['stylesheets'] as $stylesheet) {
            $template->assign('url', str_replace(__DIR__ . '/../../admin/', '', $stylesheet));
            $template->assign('version', md5_file($stylesheet));
            $template->parse('login.stylesheet');
        }
        foreach ($localAssets['javascripts'] as $javascript) {
            $template->assign('url', str_replace(__DIR__ . '/../../admin/', '', $javascript));
            $template->assign('version', md5_file($javascript));
            $template->parse('login.javascript');
        }
    }
}
