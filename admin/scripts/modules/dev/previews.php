<?php

use Gamecon\Dev\DeploymentsReader;

/**
 * Seznam aktivních preview prostředí.
 *
 * nazev: Previews
 * pravo: 113
 * submenu_group: 1
 * submenu_order: 2
 */

$reader = new DeploymentsReader();
$unavailableReason = $reader->unavailableReason();
$previews = $unavailableReason === null ? $reader->readPreviews() : [];

// Caddy před preview prostředími vyžaduje basic auth. Údaje NEvkládáme do
// odkazu jako foo:bar@host — Chrome takové přihlašovací údaje při kliknutí
// na <a> zahazuje (anti-phishing), takže by proklik končil na 401. Odkazy
// proto vedou na čisté URL a údaje ukazujeme jako kopírovatelný text;
// prohlížeč se zeptá na heslo jen při prvním otevření a dál si ho pamatuje.
$mailpitUrl = 'https://webmail.preview.gamecon.cz/';

// Preview slug = git branch name (viz .github/workflows/deploy-preview.yml).
// Linkujeme do filtru PR listu — funguje pro open i closed PR a nerozbije
// se, pokud větev neexistuje / PR ještě nevznikl.
$prListUrl = static fn(string $slug): string => 'https://github.com/gamecon-cz/gamecon/pulls?q='
    . rawurlencode('is:pr head:' . $slug);
?>
<h2>Preview prostředí</h2>

<div style="margin: 12px 0; padding: 14px 18px; border: 2px solid #2b7cb3; border-radius: 6px; background: #eaf4fb; font-size: 1.05em;">
    📬 Sdílený Mailpit pro všechna preview:
    <a href="<?= htmlspecialchars($mailpitUrl) ?>" target="_blank" rel="noopener" style="font-weight: bold;">
        webmail.preview.gamecon.cz
    </a>
    <div style="margin-top: 6px; font-size: 0.85em; color: #555;">
        Přihlášení k bráně:
        <code style="user-select: all;"><?= htmlspecialchars(PREVIEW_BASIC_AUTH_USER) ?></code>
        /
        <code style="user-select: all;"><?= htmlspecialchars(PREVIEW_BASIC_AUTH_PASSWORD) ?></code>
    </div>
</div>

<?php if ($unavailableReason !== null): ?>
    <div class="varovani">
        <strong>Data nelze načíst:</strong>
        <?= nl2br(htmlspecialchars($unavailableReason)) ?>
    </div>
<?php elseif (count($previews) === 0): ?>
    <p><em>Žádné aktivní preview prostředí.</em></p>
<?php else: ?>
    <table class="zvyraznovana" style="width: 100%">
        <thead>
            <tr>
                <th>URL</th>
                <th>PR</th>
                <th>Deployed</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($previews as $preview): ?>
            <tr>
                <td>
                    <a href="<?= htmlspecialchars($preview->url) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars(preg_replace('/^https?:\/\/|\/$/', '', $preview->url)) ?>
                    </a>
                </td>
                <td>
                    <a href="<?= htmlspecialchars($prListUrl($preview->slug)) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($preview->slug) ?>
                    </a>
                </td>
                <td>
                    <?= $preview->deployedAt !== null
                        ? htmlspecialchars($preview->deployedAt->format('Y-m-d H:i'))
                        : '—' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
