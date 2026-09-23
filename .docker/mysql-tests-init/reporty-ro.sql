-- SELECT-only account for reports, the same privilege boundary as ostra's r16779_gcostra
CREATE USER IF NOT EXISTS 'reporty_ro'@'%' IDENTIFIED BY 'reporty_ro';
GRANT SELECT ON `gamecon\_test\_%`.* TO 'reporty_ro'@'%';
