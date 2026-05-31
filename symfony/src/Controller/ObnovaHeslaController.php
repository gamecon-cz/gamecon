<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LegacySessionService;
use Gamecon\Kanaly\GcMail;
use Gamecon\Uzivatel\ResetHeslaToken;
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
    private const MIN_DELKA_HESLA = 8;
    private const MAX_DELKA_HESLA = 72; // bcrypt limit, viz Uzivatel::heslo()

    public function __construct(
        private readonly LegacySessionService $legacySession,
    ) {
    }

    #[Route('/zapomenute-heslo', name: 'zapomenute_heslo', methods: ['GET', 'POST'])]
    public function zadost(Request $request): Response
    {
        $this->legacySession->initializeLegacyEnvironment();

        if ($request->isMethod('POST')) {
            \omezCsrf();
            $email = trim((string) $request->request->get('mail', ''));
            $uzivatel = $email === ''
                ? null
                : \Uzivatel::zEmailu($email);

            if ($uzivatel) {
                $this->posliResetMail($uzivatel);
            }

            // Neutrální hláška bez ohledu na existenci účtu — neprozrazujeme,
            // jestli e-mail v systému je.
            return $this->strankaResponse(
                'Zapomenuté heslo',
                <<<HTML
                <h1>Zkontroluj e-mail</h1>
                <p>Pokud k zadané e-mailové adrese existuje účet, poslali jsme na něj
                odkaz pro nastavení nového hesla. Odkaz platí 1&nbsp;hodinu.</p>
                <p><a href="prihlaseni">Zpět na přihlášení</a></p>
                HTML,
            );
        }

        return $this->strankaResponse(
            'Zapomenuté heslo',
            <<<HTML
            <div class="stranka">
                <h1>Zapomenuté heslo</h1>
                <p>Zadej svůj e-mail. Pošleme ti odkaz, na kterém si nastavíš nové heslo.</p>
                <form method="post">
                    <label for="emailProObnovuHesla"><strong>Můj e-mail:</strong></label>
                    <input type="email" name="mail" id="emailProObnovuHesla" required>
                    <input type="submit" value="Odeslat odkaz">
                </form>
            </div>
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
            return $this->strankaResponse(
                'Neplatný odkaz',
                <<<HTML
                <div class="stranka">
                    <h1>Odkaz je neplatný nebo vypršel</h1>
                    <p>Odkaz pro obnovu hesla platí jen omezenou dobu a po nastavení
                    hesla přestane fungovat. Nech si prosím poslat nový.</p>
                    <p><a href="zapomenute-heslo">Poslat nový odkaz</a></p>
                </div>
                HTML,
                Response::HTTP_BAD_REQUEST,
            );
        }

        if ($request->isMethod('POST')) {
            \omezCsrf();
            $heslo = (string) $request->request->get('heslo', '');
            $heslo2 = (string) $request->request->get('heslo_kontrola', '');

            $chyba = $this->zkontrolujHeslo($heslo, $heslo2);
            if ($chyba !== null) {
                return $this->formularNovehoHesla($token, $chyba);
            }

            $uzivatel->heslo($heslo);

            // Cookie hláška (TTL pár sekund) přežije redirect a zobrazí se na
            // přihlašovací stránce přes Chyba::vyzvedniHtml().
            \Chyba::nastav('Heslo bylo změněno. Můžeš se přihlásit.', \Chyba::OZNAMENI);

            return new RedirectResponse(URL_WEBU . '/prihlaseni');
        }

        return $this->formularNovehoHesla($token);
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

    private function formularNovehoHesla(string $token, ?string $chyba = null): Response
    {
        $tokenHtml = htmlspecialchars($token, ENT_QUOTES);
        $chybaHtml = $chyba === null
            ? ''
            : '<p class="errorHlaska">' . htmlspecialchars($chyba, ENT_QUOTES) . '</p>';

        return $this->strankaResponse(
            'Zadej nové heslo',
            <<<HTML
            <div class="stranka">
                <h1>Zadej nové heslo</h1>
                {$chybaHtml}
                <form method="post" action="obnova-hesla">
                    <input type="hidden" name="token" value="{$tokenHtml}">
                    <label><strong>Nové heslo:</strong>
                        <input type="password" name="heslo" required minlength="8" autofocus>
                    </label>
                    <label><strong>Heslo pro kontrolu:</strong>
                        <input type="password" name="heslo_kontrola" required minlength="8">
                    </label>
                    <input type="submit" value="Nastavit nové heslo">
                </form>
            </div>
            HTML,
        );
    }

    private function zkontrolujHeslo(string $heslo, string $heslo2): ?string
    {
        if ($heslo !== $heslo2) {
            return 'Zadaná hesla se neshodují.';
        }
        if (mb_strlen($heslo) < self::MIN_DELKA_HESLA) {
            return 'Heslo musí mít aspoň ' . self::MIN_DELKA_HESLA . ' znaků.';
        }
        if (strlen($heslo) > self::MAX_DELKA_HESLA) {
            return 'Heslo může mít nejvýše ' . self::MAX_DELKA_HESLA . ' znaků.';
        }

        return null;
    }

    private function posliResetMail(\Uzivatel $uzivatel): void
    {
        $hesloMd5 = self::hesloMd5((int) $uzivatel->id());
        if ($hesloMd5 === null) {
            return;
        }
        $token = ResetHeslaToken::podepis((int) $uzivatel->id(), $hesloMd5, self::secret());
        $odkaz = URL_WEBU . '/obnova-hesla?token=' . $token;

        $telo = \hlaskaMail('zapomenuteHeslo', $uzivatel, $odkaz);

        $mail = new GcMail(\Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(), $telo);
        $mail->adresat($uzivatel->mail());
        $mail->predmet('Obnova hesla na GameConu');
        $mail->odeslat();
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
                'menu'       => '',
                'obsah'      => $obsah,
                'base'       => URL_WEBU . '/',
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
