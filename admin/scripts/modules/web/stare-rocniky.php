<?php

use Gamecon\Dev\DeploymentsReader;
use Gamecon\Dev\GateLink;

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

// Caddy před archivními ročníky vyžaduje basic auth. Odkaz vede na čistou URL
// (vložené foo:bar@host Chrome při kliknutí zahazuje), navíc k němu připojíme
// podepsaný ?gate= token: gate-validator za bránou ho vymění za session cookie,
// takže proklik projde bez dialogu. Když token vyprší / secret není nastavený,
// brána spadne na basic auth — proto vedle ukazujeme údaje jako kopírovatelný
// text (prohlížeč se zeptá při prvním otevření). Viz GateLink + ansible
// role gate_validator.
$gateUrl = static fn(string $url): string => GateLink::podepis($url, ARCHIVE_GATE_SECRET);
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
    <p style="margin: 8px 0; color: #555;">
        Přihlášení k bráně (zeptá se prohlížeč při prvním otevření):
        <code style="user-select: all;"><?= htmlspecialchars(ARCHIVE_BASIC_AUTH_USER) ?></code>
        /
        <code style="user-select: all;"><?= htmlspecialchars(ARCHIVE_BASIC_AUTH_PASSWORD) ?></code>
    </p>
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
                    <a href="<?= htmlspecialchars($gateUrl($archive->url)) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars(preg_replace('/^https?:\/\/|\/$/', '', $archive->url)) ?>
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
