<?php

use Gamecon\Dev\DeploymentsReader;

/**
 * Seznam dockerizovaných archivních ročníků (YYYY.gamecon.cz).
 * Starší ročníky, které ještě nebyly přesunuty do Dockeru, se zde
 * nezobrazí — žijí na jiné infrastruktuře (Wedos, bare-metal Apache).
 *
 * nazev: Staré ročníky
 * pravo: 113
 * submenu_group: 1
 * submenu_order: 3
 */

$reader = new DeploymentsReader();
$unavailableReason = $reader->unavailableReason();
$archives = $unavailableReason === null ? $reader->readArchives() : [];
$updatedAt = $unavailableReason === null ? $reader->updatedAt() : null;

// Setřídit od nejnovějšího ročníku.
usort($archives, static fn($a, $b) => $b->year <=> $a->year);
?>
<h2>Staré ročníky</h2>
<p>
    Zdroj dat: <code>/var/lib/gamecon/deployments/archives/*.json</code>
    (jeden soubor = jeden dockerizovaný archivní ročník; soubory píše
    deploy skript <code>deploy-year-archive.sh</code> na produkčním
    stroji).
    <?php if ($updatedAt !== null): ?>
        Naposledy aktualizováno: <?= htmlspecialchars($updatedAt->format('Y-m-d H:i:s')) ?>.
    <?php endif; ?>
</p>

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
                <th>Image (sha7)</th>
                <th>Deployed</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($archives as $archive): ?>
            <tr>
                <td><?= htmlspecialchars((string)$archive->year) ?></td>
                <td>
                    <a href="<?= htmlspecialchars($archive->url) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($archive->url) ?>
                    </a>
                </td>
                <td>
                    <?php if ($archive->sha7 !== null): ?>
                        <code><?= htmlspecialchars($archive->sha7) ?></code>
                    <?php endif; ?>
                    <?php if ($archive->image !== null): ?>
                        <small style="display:block;color:#666"><?= htmlspecialchars($archive->image) ?></small>
                    <?php endif; ?>
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
