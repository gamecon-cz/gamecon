# update image by ./docker-build.sh

FROM php:8.2-apache AS gamecon-server-step1

RUN a2enmod rewrite expires

# Install Linux libraries
RUN apt-get update && apt-get install -y --no-install-recommends \
      zlib1g-dev \
      libaio-dev \
      libxml2-dev \
      librabbitmq-dev \
      libyaml-0-2 libyaml-dev \
      libfreetype6-dev libjpeg62-turbo-dev \
      libgd-dev \
      libmagickwand-dev \
      mysql-common \
      libzip-dev \
      iproute2 # to get 'ss' command for list of used ports via 'ss -tulw'

FROM gamecon-server-step1 AS gamecon-tools-step2

# Install Linux tools
RUN apt-get update && apt-get install -y \
      mc \
      vim \
      procps \
      curl \
      gnupg \
      git \
      sudo \
      iputils-ping \
      wget \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

FROM gamecon-tools-step2 AS gamecon-php-step3

RUN docker-php-ext-install mysqli pdo_mysql intl exif bcmath xml zip

# images support, like imagecreatefromjpeg()
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && php -r 'exit(function_exists("imagecreatefromjpeg") ? 0 : 1);'

# XDebug - to start it use docker compose
RUN pecl channel-update pecl.php.net \
    && yes | pecl install xdebug imagick \
    && docker-php-ext-enable imagick \
    && sed 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read" pattern="PDF" \/>/' --in-place /etc/ImageMagick*/policy.xml

# Fix debconf warnings upon build
ARG DEBIAN_FRONTEND=noninteractive

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

RUN usermod --home /home/www-data --shell /bin/bash www-data \
    && mkdir -p /home/www-data \
    && chown -R www-data:www-data /home/www-data

USER www-data

RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && SYMFONY_BIN_DIR_PATH=$(ls -d /home/www-data/.symfony*/bin | tail -n 1) \
    && export PATH="$SYMFONY_BIN_DIR_PATH:$PATH"

RUN mkdir -p /home/www-data/bin \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/home/www-data/bin --filename=composer \
    && chmod +x /home/www-data/bin/composer \
  	&& echo 'export PATH="/home/www-data/bin:$PATH"' >> /home/www-data/.bashrc \
    && echo 'if [ -f ~/.bash_aliases ]; then . ~/.bash_aliases; fi' >> /home/www-data/.bashrc

USER root

RUN mkdir -p /var/log/php && chown -R www-data /var/log/php && chmod +w /var/log/php

FROM gamecon-php-step3 AS gamecon-php-step4

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

RUN corepack enable && yarn --version && sudo -u www-data corepack enable && sudo -u www-data yarn --version

ENV NODE_PATH=/usr/lib/nodejs:/usr/share/nodejs

FROM gamecon-php-step4 AS gamecon-project-step-5

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

FROM gamecon-project-step-5 AS gamecon-project-step-final

# dot . reflects current dir, which is .docker
COPY . /.docker
COPY ./.bash_aliases /home/www-data/.bash_aliases

RUN chmod +x /.docker/*.sh

ENV ENV=local

RUN export NODE_PATH=/usr/lib/nodejs:/usr/share/nodejs

WORKDIR $APACHE_DOCUMENT_ROOT

CMD bash /.docker/gamecon-run.sh $(pwd)
