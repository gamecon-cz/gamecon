<?php

use Gamecon\Dev\DeploymentsReader;
use Gamecon\Dev\UrlWithBasicAuth;

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
$updatedAt = $unavailableReason === null ? $reader->updatedAt() : null;

// Caddy před preview prostředími vyžaduje basic auth — vkládáme přihlašovací
// údaje rovnou do odkazů (foo:bar@host), aby admin proklikl bez dialogu.
$urlWithAuth = static fn(string $url): string => UrlWithBasicAuth::inject(
    $url,
    PREVIEW_BASIC_AUTH_USER,
    PREVIEW_BASIC_AUTH_PASSWORD,
);

$mailpitUrl = $urlWithAuth('https://webmail.preview.gamecon.cz/');
?>
<h2>Preview prostředí</h2>
<p>
    📬 Sdílený Mailpit pro všechna preview:
    <a href="<?= htmlspecialchars($mailpitUrl) ?>" target="_blank" rel="noopener">
        https://webmail.preview.gamecon.cz/
    </a>
</p>
<p>
    Zdroj dat: <code>/var/lib/gamecon/deployments/previews/*.json</code>
    (jeden soubor = jedno běžící preview prostředí; soubory píše deploy
    skript <code>deploy-preview-branch.sh</code> na produkčním stroji).
    <?php if ($updatedAt !== null): ?>
        Naposledy aktualizováno: <?= htmlspecialchars($updatedAt->format('Y-m-d H:i:s')) ?>.
    <?php endif; ?>
</p>

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
                <th>Slug</th>
                <th>URL</th>
                <th>Image (sha7)</th>
                <th>Deployed</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($previews as $preview): ?>
            <tr>
                <td><?= htmlspecialchars($preview->slug) ?></td>
                <td>
                    <a href="<?= htmlspecialchars($urlWithAuth($preview->url)) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($preview->url) ?>
                    </a>
                </td>
                <td>
                    <?php if ($preview->sha7 !== null): ?>
                        <code><?= htmlspecialchars($preview->sha7) ?></code>
                    <?php endif; ?>
                    <?php if ($preview->image !== null): ?>
                        <small style="display:block;color:#666"><?= htmlspecialchars($preview->image) ?></small>
                    <?php endif; ?>
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
