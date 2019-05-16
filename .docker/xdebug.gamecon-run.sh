#!/usr/bin/env bash
sudo -u www-data composer --working-dir=/var/www/html/gamecon install && \
chgrp -R www-data /var/www/html/gamecon/cache && \
chmod -R g+rw /var/www/html/gamecon/cache && \
chgrp -R www-data /var/www/html/gamecon/web/soubory/systemove/* && \
chmod -R g+rw /var/www/html/gamecon/web/soubory/systemove/* && \
docker-php-ext-enable xdebug && \
apache2-foreground