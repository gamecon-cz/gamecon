https://dev.mysql.com/doc/refman/8.0/en/option-files.html

| File Name               | Purpose                                                                       |
|-------------------------|-------------------------------------------------------------------------------|
| /etc/my.cnf             | Global options                                                                |
| /etc/mysql/my.cnf       | Global options                                                                |
| SYSCONFDIR/my.cnf       | Global options                                                                |
| $MYSQL_HOME/my.cnf      | Server-specific options (server only)                                         |
| defaults-extra-file     | The file specified with --defaults-extra-file, if any                         |
| ~/.my.cnf               | User-specific options                                                         |
| ~/.mylogin.cnf          | User-specific login path options (clients only)                               |
| DATADIR/mysqld-auto.cnf | System variables persisted with SET PERSIST or SET PERSIST_ONLY (server only) |

Konfigurační soubor `my.cnf` je použitý přes docker-compose, viz [docker-compose.dev.yml](..%2F..%2Fdocker-compose.dev.yml)
