<?php

/**
 * Rozcestník pro vývojářské nástroje.
 *
 * nazev: Dev
 * pravo: 113
 * submenu_group: 1
 * submenu_order: 1
 * submenu_nazev: Přehled
 */
?>
<style>
    .dev-rozcestnik {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }
    .dev-rozcestnik a {
        display: block;
        padding: 16px;
        border: 1px solid #ddd;
        border-radius: 6px;
        text-decoration: none;
        color: inherit;
        background: #fafafa;
    }
    .dev-rozcestnik a:hover {
        background: #f0f0f0;
        border-color: #888;
    }
    .dev-rozcestnik h3 {
        margin: 0 0 8px;
    }
    .dev-rozcestnik p {
        margin: 0;
        color: #555;
        font-size: 0.9em;
    }
</style>
<div class="dev-rozcestnik">
    <a href="<?= URL_ADMIN ?>/dev/previews">
        <h3>Previews</h3>
        <p>Aktivní preview prostředí (dockerizovaná nasazení feature větví).</p>
    </a>
    <a href="<?= URL_ADMIN ?>/dev/stare-rocniky">
        <h3>Staré ročníky</h3>
        <p>Seznam URL archivovaných ročníků (per-year docker kontejnery).</p>
    </a>
    <a href="<?= URL_ADMIN ?>/dev/anonymizovana-databaze">
        <h3>Anonymizovaná databáze</h3>
        <p>Export anonymizovaného dumpu pro vývojáře.</p>
    </a>
    <a href="<?= URL_ADMIN ?>/dev/update-zustatku">
        <h3>SQL update pro uzavření financí</h3>
        <p>Vygenerování SQL pro překlápění ročníku (uzavření financí).</p>
    </a>
</div>
