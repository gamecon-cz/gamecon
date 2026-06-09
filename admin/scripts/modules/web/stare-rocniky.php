<?php

use Gamecon\Dev\CrossSiteLogin;
use Gamecon\Dev\DeploymentsReader;
use Gamecon\Dev\GateLink;
use Gamecon\Dev\SsoParovaciCookie;

/**
 * Seznam dockerizovaných archivních ročníků (YYYY.gamecon.cz).
 * Starší ročníky, které ještě nebyly přesunuty do Dockeru, se zde
 * nezobrazí — žijí na jiné infrastruktuře (Wedos, bare-metal Apache).
 *
 * nazev: Staré ročníky
 * pravo: 105
 * submenu_group: 9
 */
$reader = new DeploymentsReader();
$unavailableReason = $reader->unavailableReason();
$archives = $unavailableReason === null ? $reader->readArchives() : [];

// Setřídit od nejnovějšího ročníku.
usort($archives, static fn ($a, $b) => $b->year <=> $a->year);

// Caddy před archivními ročníky vyžaduje basic auth. Odkaz vede na čistou URL
// (vložené foo:bar@host Chrome při kliknutí zahazuje), navíc k němu připojíme
// podepsaný ?gate= token: gate-validator za bránou ho vymění za session cookie,
// takže proklik projde bez dialogu. Když token vyprší / secret není nastavený,
// brána spadne na basic auth — proto vedle ukazujeme údaje jako kopírovatelný
// text (prohlížeč se zeptá při prvním otevření). Viz GateLink + ansible
// role gate_validator.
$gateUrl = static fn (string $url): string => GateLink::podepis($url, ARCHIVE_GATE_SECRET);

// Magické přihlášení do archivního adminu: k odkazu na /admin připojíme podepsaný
// ?gcsso= token vázaný na e-mail přihlášeného admina + náhodný nonce, který zároveň
// uložíme do spárovací cookie (.gamecon.cz). Archiv pak admina přihlásí podle e-mailu
// — ale jen když nonce z tokenu sedí s nonce z cookie, takže pouhé sdílení odkazu
// nikoho nepřihlásí (cizí prohlížeč cookie nemá). Bez secretu / e-mailu se token
// nepřipojí a chování zůstává jako dřív (jen basic-auth brána). Viz CrossSiteLogin
// + SsoParovaciCookie + admin/scripts/prihlaseni.php (ověřovací strana).
// Magické přihlášení podepisujeme klíčem ODVOZENÝM PRO DANÝ ROČNÍK z master tajemství
// GAMECON_SSO_SECRET (master žije jen na ostré). Archiv dostane jen svůj odvozený klíč
// (HMAC(rok, master)) — když ho někdo z archivu vytáhne, umí podvrhnout přihlášení jen
// do toho jednoho ročníku, ne do ostatních a hlavně NE k SECRET_CRYPTO_KEY (ten šifruje
// osobní data na ostré a do archivů nepatří). Odvození je shodné s bash stranou v
// deploy-year-archive.sh (openssl dgst -sha256 -hmac).
$ssoMaster = defined('GAMECON_SSO_SECRET') ? GAMECON_SSO_SECRET : '';
$ssoNonce = null;
$ssoEmail = $u->mail() ?? '';
if ($ssoEmail !== '' && $ssoMaster !== '') {
    // Kryptograficky náhodný nonce (128 bitů). Ne randHex() — ta stropuje na 32
    // znaků a stojí na substr(md5(mt_rand())), což má slabou entropii; tady jde
    // o bezpečnostní párovací token, tak chceme random_bytes.
    $ssoNonce = bin2hex(random_bytes(16));
    SsoParovaciCookie::nastav($ssoNonce);
}
$adminUrlSeSso = static function (string $adminUrl, int $rocnik) use ($ssoNonce, $ssoEmail, $ssoMaster, $gateUrl): string {
    if ($ssoNonce !== null) {
        $klicRocniku = hash_hmac('sha256', (string) $rocnik, $ssoMaster);
        $gcsso = CrossSiteLogin::podepis($ssoEmail, $ssoNonce, $klicRocniku);
        if ($gcsso !== '') {
            $oddelovac = str_contains($adminUrl, '?') ? '&' : '?';
            $adminUrl .= $oddelovac . 'gcsso=' . $gcsso;
        }
    }

    return $gateUrl($adminUrl);
};

// Ročníky spadají do tří epoch podle toho, co archiv reálně obsahuje (hranice
// jsou shodné s base_image / reconstruction érami v deploy-year-archive.yml):
//   - 2012+      živá PHP aplikace nad vlastní DB (plně funkční admin) — bez nadpisu
//   - 2009–2011  statická kopie z Internet Archive (gamecon.cz éra, žádná DB)
//   - ≤2008      éra Altaru (altar.cz/gamecon/ path 2003–2005, gamecon.altar.cz
//                subdoména 2006–2008) — taky statická, ale jiný web/CMS
$epochaRocniku = static function (int $year): string {
    if ($year >= 2012) {
        return 'ziva';
    }
    if ($year >= 2009) {
        return 'staticka';
    }

    return 'altar';
};
$nadpisEpochy = [
    'staticka' => 'Pouze statické kopie',
    'altar'    => 'Věk Altaru',
];
?>
<h2>Staré ročníky</h2>

<?php if ($unavailableReason !== null) { ?>
    <div class="varovani">
        <strong>Data nelze načíst:</strong>
        <?php echo nl2br(htmlspecialchars($unavailableReason)); ?>
    </div>
<?php } elseif (count($archives) === 0) { ?>
    <p><em>Žádný dockerizovaný archivní ročník zatím není nasazený.</em></p>
<?php } else { ?>
    <p style="margin: 8px 0; color: #555;">
        Přihlášení k bráně (basic auth):
        <code style="user-select: all;"><?php echo htmlspecialchars(ARCHIVE_BASIC_AUTH_USER); ?></code>
        /
        <code style="user-select: all;"><?php echo htmlspecialchars(ARCHIVE_BASIC_AUTH_PASSWORD); ?></code>
    </p>
    <table class="zvyraznovana" style="width: 100%">
        <thead>
            <tr>
                <th>Ročník</th>
                <th>Web</th>
                <th>Admin</th>
                <th>Deployed</th>
            </tr>
        </thead>
        <tbody>
        <?php $epochaPredchozi = null; ?>
        <?php foreach ($archives as $archive) { ?>
            <?php
            $epocha = $epochaRocniku($archive->year);
            if ($epocha !== $epochaPredchozi && isset($nadpisEpochy[$epocha])) { ?>
                <tr>
                    <th colspan="4" style="text-align: left; padding-top: 18px; border-bottom: 2px solid #888;">
                        <?php echo htmlspecialchars($nadpisEpochy[$epocha]); ?>
                    </th>
                </tr>
            <?php }
            $epochaPredchozi = $epocha;
            ?>
            <tr>
                <td><?php echo htmlspecialchars((string) $archive->year); ?></td>
                <td>
                    <a href="<?php echo htmlspecialchars($gateUrl($archive->url)); ?>" target="_blank" rel="noopener">
                        <?php echo htmlspecialchars(preg_replace('/^https?:\/\/|\/$/', '', $archive->url)); ?>
                    </a>
                </td>
                <td>
                    <?php
                    if ($epocha === 'ziva') {
                        $adminUrl = rtrim($archive->url, '/') . '/admin';
                        ?>
                        <a href="<?php echo htmlspecialchars($adminUrlSeSso($adminUrl, $archive->year)); ?>" target="_blank" rel="noopener"><?php echo $archive->year; ?> /admin</a>
                    <?php } else { ?>
                        —
                    <?php } ?>
                </td>
                <td>
                    <?php echo $archive->deployedAt !== null
                        ? htmlspecialchars($archive->deployedAt->format('Y-m-d H:i'))
                        : '—'; ?>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
<?php } ?>
