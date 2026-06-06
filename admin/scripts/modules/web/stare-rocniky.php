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
usort($archives, static fn ($a, $b) => $b->year <=> $a->year);

// Caddy před archivními ročníky vyžaduje basic auth. Odkaz vede na čistou URL
// (vložené foo:bar@host Chrome při kliknutí zahazuje), navíc k němu připojíme
// podepsaný ?gate= token: gate-validator za bránou ho vymění za session cookie,
// takže proklik projde bez dialogu. Když token vyprší / secret není nastavený,
// brána spadne na basic auth — proto vedle ukazujeme údaje jako kopírovatelný
// text (prohlížeč se zeptá při prvním otevření). Viz GateLink + ansible
// role gate_validator.
$gateUrl = static fn (string $url): string => GateLink::podepis($url, ARCHIVE_GATE_SECRET);

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
                <th>URL</th>
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
                    <th colspan="3" style="text-align: left; padding-top: 18px; border-bottom: 2px solid #888;">
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
                    <?php echo $archive->deployedAt !== null
                        ? htmlspecialchars($archive->deployedAt->format('Y-m-d H:i'))
                        : '—'; ?>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
<?php } ?>
