<?php

use Gamecon\Dev\CrossSiteLogin;
use Gamecon\Dev\DeploymentsReader;
use Gamecon\Dev\GateLink;
use Gamecon\Dev\SsoParovaciCookie;

/**
 * Seznam aktivních preview prostředí.
 *
 * nazev: Previews
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

// Magické přihlášení do preview adminu: k odkazu na /admin připojíme podepsaný
// ?gcsso= token vázaný na id_uzivatele přihlášeného admina + náhodný nonce, který
// zároveň uložíme do spárovací cookie (.gamecon.cz). Preview pak admina přihlásí
// podle ID — ale jen když nonce z tokenu sedí s nonce z cookie, takže pouhé sdílení
// odkazu nikoho nepřihlásí (cizí prohlížeč cookie nemá). Bez masteru / ID se token
// nepřipojí a zůstane jen basic-auth brána.
//
// Na rozdíl od archivů (ty ověřují klíčem ODVOZENÝM pro daný ročník) preview
// podepisuje i ověřuje přímo MASTEREM GAMECON_SSO_SECRET: preview není ročník v
// rámci fan-outu, je to jedno samostatné prostředí, které přihlašuje samo sebe —
// žádné odvození per-prostředí netřeba. Master se do preview dostává přes
// deploy-preview-branch.sh (-e GAMECON_SSO_SECRET); ověřovací stranu viz
// admin/scripts/prihlaseni.php (větev jsmeNaPreview()).
$ssoMaster = defined('GAMECON_SSO_SECRET') ? GAMECON_SSO_SECRET : '';
$ssoNonce = null;
$ssoIdUzivatele = $u->id() ?? 0;
if ($ssoIdUzivatele > 0 && $ssoMaster !== '') {
    // Kryptograficky náhodný nonce (128 bitů) — bezpečnostní párovací token.
    $ssoNonce = bin2hex(random_bytes(16));
    SsoParovaciCookie::nastav($ssoNonce);
}
$adminUrlSeSso = static function (string $previewUrl) use ($ssoNonce, $ssoIdUzivatele, $ssoMaster, $gateUrl): string {
    $adminUrl = rtrim($previewUrl, '/') . '/admin';
    if ($ssoNonce !== null) {
        $gcsso = CrossSiteLogin::podepis($ssoIdUzivatele, $ssoNonce, $ssoMaster);
        if ($gcsso !== '') {
            $oddelovac = str_contains($adminUrl, '?') ? '&' : '?';
            $adminUrl .= $oddelovac . 'gcsso=' . $gcsso;
        }
    }

    return $gateUrl($adminUrl);
};

$mailpitUrl = $gateUrl('https://webmail.preview.gamecon.cz/');

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
                    <a href="<?php echo htmlspecialchars($gateUrl($preview->url)); ?>" target="_blank" rel="noopener">
                        <?php echo htmlspecialchars(preg_replace('/^https?:\/\/|\/$/', '', $preview->url)); ?>
                    </a>
                </td>
                <td>
                    <a href="<?php echo htmlspecialchars($adminUrlSeSso($preview->url)); ?>" target="_blank" rel="noopener">/admin</a>
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
