<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LegacySessionService;
use Gamecon\Antibot\Altcha;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Kanaly\EmailovaSablona\EmailoveSablony;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Kanaly\GcMail;
use Gamecon\Kanaly\GcMailSablona;
use Gamecon\Uzivatel\ObnovaHeslaLog;
use Gamecon\Uzivatel\ResetHeslaToken;
use Gamecon\Web\MenuWebu;
use Gamecon\XTemplate\XTemplate;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Obnova zapomenutého hesla přes „magic link".
 *
 * Nahrazuje dřívější tok, který generoval nové heslo a posílal ho v plaintextu
 * mailem (plaintextové heslo v mailu = bezpečnostní vada — viz mail logy).
 * Nově uživatel dostane podepsaný expirující odkaz a heslo si nastaví sám na
 * stránce „Zadej nové heslo".
 *
 * Záměrně se po obnově NEpřihlašuje — přihlášení (zápis do $_SESSION /
 * trvalého cookie) zůstává jen na legacy vrstvě ({@see \Uzivatel::prihlas()}).
 * Tenhle controller se tak nedotýká auth/session internals a po úspěšné změně
 * jen přesměruje na přihlašovací stránku.
 */
class ObnovaHeslaController extends AbstractController
{
    private const int MIN_DELKA_HESLA = 8;
    private const int MAX_DELKA_HESLA = 72; // bcrypt limit, viz Uzivatel::heslo()

    public function __construct(
        private readonly LegacySessionService $legacySession,
    ) {
    }

    #[Route('/zapomenute-heslo', name: 'zapomenute_heslo', methods: ['GET', 'POST'])]
    public function zadost(Request $request): Response
    {
        $this->legacySession->initializeLegacyEnvironment();
        $h = htmlspecialchars(...);

        if ($request->isMethod('POST')) {
            \omezCsrf();

            if (! Altcha::zGlobals()->overReseni((string) $request->request->get('altcha', ''))) {
                \Chyba::nastav(htmlspecialchars(\t('ucet.nejsi_robot')), \Chyba::CHYBA);

                return new RedirectResponse(\urlWebu('zapomenute-heslo'));
            }

            $email = trim((string) $request->request->get('mail', ''));
            if (! $this->vyridZadost($email, (string) $request->getClientIp())) {
                \Chyba::nastav(htmlspecialchars(\t('obnova_hesla.moc_zadosti')), \Chyba::CHYBA);

                return new RedirectResponse(\urlWebu('zapomenute-heslo'));
            }

            // Neutrální hláška bez ohledu na existenci účtu a na limit účtu —
            // neprozrazujeme, jestli e-mail v systému je.
            return $this->strankaResponse(
                \t('obnova_hesla.zapomenute_heslo'),
                <<<HTML
                <div class="formular formular_stranka formular_stranka-login">
                    <div class="bg"></div>

                    <div class="formular_strankaNadpis">{$h(\t('obnova_hesla.zkontroluj_email'))}</div>

                    <p>{$h(\t('obnova_hesla.odkaz_poslan'))}</p>
                    <p><a href="prihlaseni">{$h(\t('obnova_hesla.zpet_na_prihlaseni'))}</a></p>
                </div>
                HTML,
            );
        }

        $widget = \altchaWidget();

        return $this->strankaResponse(
            \t('obnova_hesla.zapomenute_heslo'),
            <<<HTML
            <form method="post" class="formular formular_stranka formular_stranka-login">
                <div class="bg"></div>

                <div class="formular_strankaNadpis">{$h(\t('obnova_hesla.zapomenute_heslo'))}</div>

                <p>{$h(\t('obnova_hesla.zadej_email'))}</p>
                <label class="formular_polozka">
                    {$h(\t('obnova_hesla.muj_email'))}
                    <input type="email" name="mail" id="emailProObnovuHesla" autocomplete="username" required>
                </label>
                {$widget}
                <input type="submit" value="{$h(\t('obnova_hesla.odeslat_odkaz'))}" class="formular_primarni formular_primarni-sipka">
            </form>
            HTML,
        );
    }

    #[Route('/obnova-hesla', name: 'obnova_hesla', methods: ['GET', 'POST'])]
    public function obnova(Request $request): Response
    {
        $this->legacySession->initializeLegacyEnvironment();

        $token = (string) ($request->isMethod('POST')
            ? $request->request->get('token', '')
            : $request->query->get('token', ''));

        $uzivatel = $this->uzivatelZTokenu($token);
        if (! $uzivatel) {
            $h = htmlspecialchars(...);

            return $this->strankaResponse(
                \t('obnova_hesla.neplatny_odkaz'),
                <<<HTML
                <div class="formular formular_stranka formular_stranka-login">
                    <div class="bg"></div>

                    <div class="formular_strankaNadpis">{$h(\t('obnova_hesla.neplatny_odkaz'))}</div>

                    <p>{$h(\t('obnova_hesla.neplatny_odkaz_popis'))}</p>
                    <p><a href="zapomenute-heslo">{$h(\t('obnova_hesla.poslat_novy_odkaz'))}</a></p>
                </div>
                HTML,
                Response::HTTP_BAD_REQUEST,
            );
        }

        if ($request->isMethod('POST')) {
            \omezCsrf();
            $heslo = (string) $request->request->get('heslo', '');

            $chyba = $this->zkontrolujHeslo($heslo);
            if ($chyba !== null) {
                // Post/Redirect/Get: po chybě přesměrujeme zpět na GET formulář
                // (chybu přenese cookie přes Chyba). Bez redirectu Firefox bere
                // POST odpověď jako dokončené odeslání a nabízí uložit i
                // zamítnuté (neshodující se) heslo.
                \Chyba::nastav(htmlspecialchars($chyba), \Chyba::CHYBA);

                return new RedirectResponse(\urlWebu('obnova-hesla') . '?token=' . urlencode($token));
            }

            $uzivatel->heslo($heslo, 'obnova-hesla');

            // Cookie hláška (TTL pár sekund) přežije redirect a zobrazí se na
            // přihlašovací stránce přes Chyba::vyzvedniHtml().
            \Chyba::nastav(htmlspecialchars(\t('obnova_hesla.heslo_zmeneno')), \Chyba::OZNAMENI);

            return new RedirectResponse(\urlWebu('prihlaseni'));
        }

        return $this->formularNovehoHesla($token, $uzivatel->mail());
    }

    private function uzivatelZTokenu(string $token): ?\Uzivatel
    {
        if ($token === '') {
            return null;
        }
        // ID z tokenu zatím neznáme; získáme ho až po ověření. Token ale váže
        // sám sebe na heslo_md5, takže musíme nejdřív rozluštit ID kandidáta.
        // Řešení: payload nese ID — vytáhneme ho bezpečně až přes over(), kde
        // potřebujeme aktuální heslo_md5. Proto ID získáme dvoufázově:
        //   1) předběžně z payloadu (bez důvěry),
        //   2) over() ověří podpis + expiraci + vazbu na aktuální heslo_md5.
        $idKandidat = self::idZPayloadu($token);
        if ($idKandidat === null) {
            return null;
        }
        $hesloMd5 = self::hesloMd5($idKandidat);
        if ($hesloMd5 === null) {
            return null;
        }
        $idOvereny = ResetHeslaToken::over($token, $hesloMd5, self::secret());
        if ($idOvereny === null || $idOvereny !== $idKandidat) {
            return null;
        }

        return \Uzivatel::zId($idOvereny);
    }

    private function formularNovehoHesla(string $token, string $email): Response
    {
        $tokenHtml = htmlspecialchars($token, ENT_QUOTES);
        $emailHtml = htmlspecialchars($email, ENT_QUOTES);
        $h = htmlspecialchars(...);

        // Skryté pole s e-mailem (autocomplete=username) napojí formulář na
        // konkrétní uložené přihlášení — bez něj správce hesel neumí spárovat
        // změnu s účtem a nenabídne aktualizaci uloženého hesla po úspěchu.
        return $this->strankaResponse(
            \t('obnova_hesla.zadej_nove_heslo'),
            <<<HTML
            <form method="post" action="obnova-hesla" class="formular formular_stranka formular_stranka-login">
                <div class="bg"></div>

                <div class="formular_strankaNadpis">{$h(\t('obnova_hesla.nove_heslo'))}</div>

                <input type="hidden" name="token" value="{$tokenHtml}">
                <input type="email" name="email" value="{$emailHtml}" autocomplete="username" readonly hidden>
                <label class="formular_polozka">
                    {$h(\t('obnova_hesla.nove_heslo'))}
                    <input type="password" name="heslo" autocomplete="new-password" required minlength="8" autofocus>
                </label>
                <input type="submit" value="{$h(\t('obnova_hesla.nastavit_nove_heslo'))}" class="formular_primarni formular_primarni-sipka">
            </form>
            HTML,
        );
    }

    /**
     * @return string|null proč heslo nejde, prostý text
     */
    private function zkontrolujHeslo(string $heslo): ?string
    {
        if (mb_strlen($heslo) < self::MIN_DELKA_HESLA) {
            return \t('obnova_hesla.heslo_kratke', [
                'pocet' => self::MIN_DELKA_HESLA,
            ]);
        }
        if (strlen($heslo) > self::MAX_DELKA_HESLA) {
            return \t('obnova_hesla.heslo_dlouhe', [
                'pocet' => self::MAX_DELKA_HESLA,
            ]);
        }

        return null;
    }

    /**
     * Pošle odkaz, pokud to dovolí limity, a žádost zapíše do {@see ObnovaHeslaLog}.
     * Vrací false, jen když IP překročila limit: to se říct dá, limit účtu ne,
     * prozradil by, že k e-mailu účet je.
     */
    private function vyridZadost(string $email, string $ip): bool
    {
        $uzivatel = $email === ''
            ? null
            : \Uzivatel::zEmailu($email);
        $obnovaHeslaLog = new ObnovaHeslaLog();
        // Zapisuje se před počítáním limitů, jinak je souběžné žádosti přeskočí (viz ObnovaHeslaLog).
        $idZadosti = $obnovaHeslaLog->zaznamenej($ip, $uzivatel?->id(), $uzivatel !== null);
        if ($obnovaHeslaLog->jePrekrocenLimitIp($idZadosti)) {
            // Odmítnutá žádost v logu nezůstane, jinak by vytrvalý bot plnil tabulku bez omezení.
            $obnovaHeslaLog->smaz($idZadosti);

            return false;
        }
        if ($uzivatel === null) {
            return true;
        }

        $odeslano = false;
        try {
            $odeslano = ! $obnovaHeslaLog->jePrekrocenLimitUctu((int) $uzivatel->id())
                && $this->posliResetMail($uzivatel);
        } finally {
            // Kdy finally neproběhne a žádost zůstane započtená jako odeslaný mail: „Pád uprostřed odesílání“ v docs/generated/obnova-hesla.md.
            if (! $odeslano) {
                $obnovaHeslaLog->oznacNeodeslano($idZadosti);
            }
        }

        return true;
    }

    private function posliResetMail(\Uzivatel $uzivatel): bool
    {
        $hesloMd5 = self::hesloMd5((int) $uzivatel->id());
        if ($hesloMd5 === null) {
            return false;
        }
        $token = ResetHeslaToken::podepis((int) $uzivatel->id(), $hesloMd5, self::secret());
        $odkaz = URL_WEBU . '/obnova-hesla?token=' . $token;

        $vykresleno = EmailoveSablony::zGlobals()->vykresli(KodEmailoveSablonyEnum::OBNOVA_HESLA, [
            'odkaz' => $odkaz,
        ]);
        $telo = GcMailSablona::obal($vykresleno->text, 'Obnova hesla');

        $mail = new GcMail(\Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(), $telo);
        $mail->adresat($uzivatel->mail());
        $mail->predmet($vykresleno->predmet);
        $mail->sablona(KodEmailoveSablonyEnum::OBNOVA_HESLA);
        // Kdo vidí log odeslaných mailů, nesmí odkazem nastavit cizí heslo.
        $mail->skrytVLogu($token);
        $mail->obrazekInline(GcMailSablona::logoSoubor(), GcMailSablona::LOGO_CID);
        $mail->format($vykresleno->format);

        return (bool) $mail->odeslat();
    }

    /**
     * Aktuální hash hesla uživatele (sloupec heslo_md5). Slouží jako otisk,
     * na který se token váže — po změně hesla starý token přestane platit.
     */
    private static function hesloMd5(int $idUzivatele): ?string
    {
        $hash = \dbOneCol(
            'SELECT heslo_md5 FROM uzivatele_hodnoty WHERE id_uzivatele = $1',
            [$idUzivatele],
        );

        return $hash === null || $hash === ''
            ? null
            : (string) $hash;
    }

    /**
     * Vytáhne ID uživatele z payloadu tokenu BEZ ověření podpisu. Slouží jen
     * k nalezení kandidáta, jehož heslo_md5 potřebujeme pro plné ověření
     * v {@see ResetHeslaToken::over()}.
     */
    private static function idZPayloadu(string $token): ?int
    {
        if (! str_contains($token, '.')) {
            return null;
        }
        [$payloadPart] = explode('.', $token, 2);
        $payload = base64_decode(strtr($payloadPart, '-_', '+/'), true);
        if ($payload === false) {
            return null;
        }
        $casti = explode('|', $payload);
        if (count($casti) !== 3 || ! ctype_digit($casti[0])) {
            return null;
        }

        return (int) $casti[0];
    }

    private static function secret(): string
    {
        return (string) ($_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? (defined('APP_SECRET') ? APP_SECRET : ''));
    }

    /**
     * Vyrenderuje tělo stránky do stejné blackarrow šablony, jakou používá
     * legacy web/index.php — aby stránka vypadala nativně. Šablona i
     * perfectcache pracují s cestami relativními k web rootu (WWW), proto se
     * na dobu renderu přepneme tam a pak cwd vrátíme zpět.
     */
    private function strankaResponse(string $nazev, string $obsah, int $status = Response::HTTP_OK): Response
    {
        $puvodniCwd = getcwd();
        chdir(WWW);
        try {
            $sablona = new XTemplate(WWW . '/sablony/blackarrow/index.xtpl');
            $sablona->assign([
                'css'        => \perfectcache('soubory/blackarrow/*/*.less'),
                'chyba'      => \Chyba::vyzvedniHtml(),
                'menu'       => MenuWebu::html($this->legacySession->getCurrentUser()),
                'obsah'      => $obsah,
                'base'       => \urlWebu(),
                'jazyk'      => AktualniJazyk::jazyk()->value,
                'info'       => '<title>' . htmlspecialchars($nazev, ENT_QUOTES) . ' – GameCon</title>',
                'letosniRok' => date('Y'),
            ]);
            $sablona->parse('index.paticka');
            $sablona->parse('index');
            $html = $sablona->text('index');
        } finally {
            if ($puvodniCwd !== false) {
                chdir($puvodniCwd);
            }
        }

        return new Response($html, $status);
    }
}
