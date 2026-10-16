# update image by ./docker-build.sh
#
# Two images are built from this file. `runtime` is PHP, its extensions and Apache: the preview and archive images build
# on it. `dev` is `runtime` plus the development tools (xdebug, git, Node, ...): local development and CI run in it.

FROM php:8.3-apache AS gamecon-runtime-step1

RUN a2enmod rewrite expires headers && \
	# avoid warning on start
    echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Install Linux libraries used at runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
      mysql-common \
      # iproute2 to get 'ss' command for list of used ports via 'ss -tulw'
      iproute2 \
      # procps to get ps, top
      procps \
      # without it Imagick cannot decode SVG, and partner logos in SVG are thumbnailed through Imagick
      libmagickcore-7.q16-10-extra \
    && rm -rf /var/lib/apt/lists/*

FROM gamecon-runtime-step1 AS gamecon-runtime-step2

# The -dev packages are only needed to compile the extensions. They are installed and purged in this one RUN,
# because a package removed in a later layer still takes its space in the layer that installed it.
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      zlib1g-dev \
      libxml2-dev \
      libfreetype6-dev libjpeg62-turbo-dev \
      libgd-dev \
      libmagickwand-dev \
      libzip-dev; \
    docker-php-ext-install mysqli pdo_mysql intl exif bcmath xml zip; \
    # images support, like imagecreatefromjpeg()
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install gd; \
    pecl channel-update pecl.php.net; \
    yes | pecl install imagick; \
    docker-php-ext-enable imagick; \
    sed 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read" pattern="PDF" \/>/' --in-place /etc/ImageMagick*/policy.xml; \
    # Keep the runtime libraries the built extensions link against, purge the rest
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark; \
    # pecl installs its .so files without the executable bit
    find /usr/local -type f \( -executable -o -name '*.so' \) -exec ldd '{}' ';' \
      | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*/%s\n", so }' \
      | sort -u \
      | xargs -r dpkg-query --search \
      | awk 'sub(":$", "", $1) { print $1 }' \
      | sort -u \
      | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove; \
    rm -rf /var/lib/apt/lists/* /tmp/pear; \
    # PHP only warns and skips an extension whose library was purged, so check every one of them is loaded
    php -r 'foreach (["mysqli", "pdo_mysql", "intl", "exif", "bcmath", "xml", "zip", "gd", "imagick"] as $extension) { if (!extension_loaded($extension)) { exit(1); } } exit(function_exists("imagecreatefromjpeg") ? 0 : 1);'

RUN mkdir -p /var/log/php && chown -R www-data /var/log/php && chmod +w /var/log/php

# The archive branches run `composer dump-autoload` when built (.dockerignore strips the dev packages the
# committed autoloader requires). A failed download does not fail the pipe into php, hence the version check.
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer \
    && composer --version

FROM gamecon-runtime-step2 AS runtime

# avoid warning on start
RUN echo 'ServerName inside-docker' >> /etc/apache2/apache2.conf

COPY ./apache/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY ./apache/sites-available/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf
COPY ./apache/gamecon.local.crt /etc/ssl/certs/gamecon.local.crt
COPY ./apache/gamecon.local.key /etc/ssl/private/gamecon.local.key

RUN chmod 0600 /etc/ssl/certs/gamecon.local.crt
RUN chown root:root /etc/ssl/certs/gamecon.local.crt
RUN chmod 0600 /etc/ssl/private/gamecon.local.key
RUN chown root:root /etc/ssl/private/gamecon.local.key

RUN a2enmod rewrite
RUN a2enmod expires
RUN a2enmod ssl
RUN a2enmod socache_shmcb
RUN a2ensite 000-default
RUN a2ensite default-ssl

RUN apache2ctl configtest

ENV APACHE_DOCUMENT_ROOT=/var/www/html/gamecon
RUN sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

FROM runtime AS gamecon-dev-step1

# Install Linux tools
RUN apt-get update && apt-get install -y \
      mc \
      vim \
      gnupg \
      git \
      sudo \
      iputils-ping \
      wget \
      cron \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Fix debconf warnings upon build
ARG DEBIAN_FRONTEND=noninteractive

# XDebug - to start it use docker compose
RUN set -eux; \
    pecl channel-update pecl.php.net; \
    pecl install xdebug; \
    rm -rf /tmp/pear; \
    test -f "$(php-config --extension-dir)/xdebug.so"

RUN usermod --home /home/www-data --shell /bin/bash www-data \
    && mkdir -p /home/www-data \
    && chown -R www-data:www-data /home/www-data

USER www-data

RUN mkdir -p /home/www-data/bin \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/home/www-data/bin --filename=composer \
    && chmod +x /home/www-data/bin/composer \
  	&& echo 'export PATH="/home/www-data/bin:$PATH"' >> /home/www-data/.bashrc \
    && echo 'if [ -f ~/.bash_aliases ]; then . ~/.bash_aliases; fi' >> /home/www-data/.bashrc

USER root

FROM gamecon-dev-step1 AS gamecon-dev-step2

RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

RUN corepack enable && yarn --version && sudo -u www-data corepack enable && sudo -u www-data yarn --version

ENV NODE_PATH=/usr/lib/nodejs:/usr/share/nodejs

FROM gamecon-dev-step2 AS dev

# dot . reflects current dir, which is .docker
COPY . /.docker
COPY ./.bash_aliases /home/www-data/.bash_aliases

RUN chmod +x /.docker/*.sh

ENV ENV=local

RUN export NODE_PATH=/usr/lib/nodejs:/usr/share/nodejs

WORKDIR $APACHE_DOCUMENT_ROOT

CMD bash /.docker/gamecon-run.sh $(pwd)
