# Zálohy databáze

Externí zálohy dělá [Borg](https://www.borgbackup.org/) přes [Borgmatic](https://torsion.org/borgmatic/). Běží přímo na serveru, jednou denně z časovače systemd, a nastavení je v ansible (soukromý repozitář). Aplikace, GitHub Actions ani FTP v tom nemají žádnou roli.

Zálohuje se složka `backup/db` aplikace (exporty databáze, které aplikace dělá sama, třeba před migracemi) a databáze, které si Borgmatic vyexportuje sám. Uchovává se 7 denních, 4 týdenní, 12 měsíčních a 2 roční archivy. Repozitář je šifrovaný a přístup k němu mají jen určené SSH klíče.

Obnovu dat dělá správce serveru na serveru: archiv se rozbalí Borgmaticem a databáze se obnoví z nejnovějšího exportu, nic z toho nejde přes administraci.

Do repozitáře se dá nahlédnout i z pracovní stanice skripty ve složce [`udrzba/borg`](../udrzba/borg): `borg_list.sh` vypíše archivy, `borg_mount_backup.sh <RRRR-MM-DD>` a `borg_mount_today_backups.sh` připojí vybraný, respektive dnešní archiv. Skripty načtou ze souboru `gamecon_borgbase.env` (v gitu není) proměnné pro Borg: `BORG_REPO` (adresa repozitáře) a podle potřeby i další, třeba `BORG_PASSPHRASE` (heslo ke klíči). Připojení (`borg mount`) repozitář zamyká a zálohování se tím zablokuje, dokud se archiv neodpojí (`borg umount <složka>`).
