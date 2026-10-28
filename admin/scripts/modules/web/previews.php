<?php

use Gamecon\Dev\CrossSiteLogin;
use Gamecon\Dev\DeploymentsReader;
use Gamecon\Dev\GateLink;
use Gamecon\Dev\SsoParovaciCookie;
use Gamecon\Kanaly\ZachytavacEmailu;

/**
 * Seznam aktivních preview prostředí.
 *
 * nazev: Previews
 * ikona: eye
 * pravo: 105
 * submenu_group: 9
 */
$reader = new DeploymentsReader();
$unavailableReason = $reader->unavailableReason();
$previews = $unavailableReason === null ? $reader->readPreviews() : [];

// Caddy před preview prostředími vyžaduje basic auth. Odkazy vedou na čistou
// URL (vložené foo:bar@host Chrome při kliknutí zahazuje), navíc k nim
// připojíme podepsaný ?gate= token: gate-validator za bránou ho vymění za
// session cookie, takže proklik projde bez dialogu. Když token vyprší / secret
// není nastavený, brána spadne na basic auth — proto údaje ukazujeme jako
// kopírovatelný text. Viz GateLink + ansible role gate_validator.
$gateUrl = static fn (string $url): string => GateLink::podepis($url, PREVIEW_GATE_SECRET);

// Magické přihlášení do preview (web i /admin): k odkazu připojíme podepsaný ?gcsso= token a nastavíme spárovací
// cookie, takže pouhé sdílení odkazu nikoho nepřihlásí. Preview podepisuje i ověřuje přímo MASTEREM
// GAMECON_SSO_SECRET (z deploy-preview-branch.sh), ne klíčem ročníku jako archivy. Bez masteru / ID se token nepřipojí.
// Ověřuje SsoPrihlaseniZUrl, viz docs/generated/odkazy-do-archivnich-rocniku.md.
$ssoMaster = defined('GAMECON_SSO_SECRET') ? GAMECON_SSO_SECRET : '';
$ssoIdUzivatele = $u->id() ?? 0;
$urlSeSso = static function (string $url, ?string $ssoNonce) use ($ssoIdUzivatele, $ssoMaster, $gateUrl): string {
    if ($ssoNonce !== null) {
        $gcsso = CrossSiteLogin::podepis($ssoIdUzivatele, $ssoNonce, $ssoMaster);
        if ($gcsso !== '') {
            $oddelovac = str_contains($url, '?') ? '&' : '?';
            $url .= $oddelovac . 'gcsso=' . $gcsso;
        }
    }

    return $gateUrl($url);
};

// Token se podepíše až po kliknutí (odkaz vede sem s ?admin=<slug>, resp. ?web=<slug>).
// Podepsaný při vykreslení by do kliknutí vypršel (platí 5 minut).
$slugDoAdminu = is_string($_GET['admin'] ?? null) ? $_GET['admin'] : '';
$slugNaWeb = is_string($_GET['web'] ?? null) ? $_GET['web'] : '';
foreach ($previews as $preview) {
    $cilovaUrl = null;
    if ($slugDoAdminu !== '' && $preview->slug === $slugDoAdminu) {
        $cilovaUrl = rtrim($preview->url, '/') . '/admin';
    } elseif ($slugNaWeb !== '' && $preview->slug === $slugNaWeb) {
        $cilovaUrl = $preview->url;
    }
    if ($cilovaUrl === null) {
        continue;
    }

    $ssoNonce = null;
    if ($ssoIdUzivatele > 0 && $ssoMaster !== '') {
        $ssoNonce = SsoParovaciCookie::nastavNonceProKliknuti();
    }
    header('Location: ' . $urlSeSso($cilovaUrl, $ssoNonce));
    exit;
}

$mailpitUrl = $gateUrl(ZachytavacEmailu::URL_PREVIEW);

?>
<h2>Preview prostředí</h2>

<div style="margin: 12px 0; padding: 14px 18px; border: 2px solid #2b7cb3; border-radius: 6px; background: #eaf4fb; font-size: 1.05em;">
    📬 Sdílený Mailpit pro všechna preview:
    <a href="<?php echo htmlspecialchars($mailpitUrl); ?>" target="_blank" rel="noopener" style="font-weight: bold;">
        webmail.preview.gamecon.cz
    </a>
    <div style="margin-top: 6px; font-size: 0.85em; color: #555;">
        Přihlášení k bráně (basic auth):
        <code style="user-select: all;"><?php echo htmlspecialchars(PREVIEW_BASIC_AUTH_USER); ?></code>
        /
        <code style="user-select: all;"><?php echo htmlspecialchars(PREVIEW_BASIC_AUTH_PASSWORD); ?></code>
    </div>
</div>

<?php if ($unavailableReason !== null) { ?>
    <div class="varovani">
        <strong>Data nelze načíst:</strong>
        <?php echo nl2br(htmlspecialchars($unavailableReason)); ?>
    </div>
<?php } elseif (count($previews) === 0) { ?>
    <p><em>Žádné aktivní preview prostředí.</em></p>
<?php } else { ?>
    <table class="zvyraznovana" style="width: 100%">
        <thead>
            <tr>
                <th>Web</th>
                <th>Admin</th>
                <th>Poslední změna</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($previews as $preview) { ?>
            <tr id="<?php echo htmlspecialchars($preview->slug); ?>">
                <td>
                    <a href="web/previews?web=<?php echo htmlspecialchars(rawurlencode($preview->slug)); ?>" target="_blank" rel="noopener">
                        <?php echo htmlspecialchars(preg_replace('/^https?:\/\/|\/$/', '', $preview->url)); ?>
                    </a>
                </td>
                <td>
                    <a href="web/previews?admin=<?php echo htmlspecialchars(rawurlencode($preview->slug)); ?>" target="_blank" rel="noopener">/admin</a>
                </td>
                <td>
                    <?php echo $preview->deployedAt !== null
                        ? htmlspecialchars($preview->deployedAt->format('Y-m-d H:i'))
                        : '—'; ?>
                </td>
                <td style="width: 1%">
                    <a href="web/previews#<?php echo htmlspecialchars($preview->slug); ?>" class="lokalni-odkaz">#</a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
<?php } ?>
