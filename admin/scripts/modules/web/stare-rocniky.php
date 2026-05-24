<?php

use Gamecon\Dev\DeploymentsReader;
use Gamecon\Dev\UrlWithBasicAuth;

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
usort($archives, static fn($a, $b) => $b->year <=> $a->year);

// Caddy před archivními ročníky vyžaduje basic auth — vkládáme přihlašovací
// údaje rovnou do odkazů (foo:bar@host), aby admin proklikl bez dialogu.
$urlWithAuth = static fn(string $url): string => UrlWithBasicAuth::inject(
    $url,
    ARCHIVE_BASIC_AUTH_USER,
    ARCHIVE_BASIC_AUTH_PASSWORD,
);
?>
<h2>Staré ročníky</h2>

<?php if ($unavailableReason !== null): ?>
    <div class="varovani">
        <strong>Data nelze načíst:</strong>
        <?= nl2br(htmlspecialchars($unavailableReason)) ?>
    </div>
<?php elseif (count($archives) === 0): ?>
    <p><em>Žádný dockerizovaný archivní ročník zatím není nasazený.</em></p>
<?php else: ?>
    <table class="zvyraznovana" style="width: 100%">
        <thead>
            <tr>
                <th>Ročník</th>
                <th>URL</th>
                <th>Deployed</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($archives as $archive): ?>
            <tr>
                <td><?= htmlspecialchars((string)$archive->year) ?></td>
                <td>
                    <a href="<?= htmlspecialchars($urlWithAuth($archive->url)) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($archive->url) ?>
                    </a>
                </td>
                <td>
                    <?= $archive->deployedAt !== null
                        ? htmlspecialchars($archive->deployedAt->format('Y-m-d H:i'))
                        : '—' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
