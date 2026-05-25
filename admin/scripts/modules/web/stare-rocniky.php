<?php

use Gamecon\Dev\DeploymentsReader;

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

// Caddy před archivními ročníky vyžaduje basic auth. Údaje NEvkládáme do
// odkazu jako foo:bar@host — Chrome takové přihlašovací údaje při kliknutí
// na <a> zahazuje (anti-phishing), takže by proklik končil na 401. Odkaz
// proto vede na čisté URL a údaje ukazujeme vedle jako kopírovatelný text;
// prohlížeč se zeptá na heslo jen při prvním otevření a dál si ho pamatuje.
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
                    <a href="<?= htmlspecialchars($archive->url) ?>" target="_blank" rel="noopener">
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
