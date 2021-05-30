FROM php:7.3-apache

# Install other missed extensions
RUN apt-get update && apt-get install -y \
      zlib1g-dev \
      libaio-dev \
      libxml2-dev \
      librabbitmq-dev \
      libyaml-0-2 libyaml-dev \
      libfreetype6-dev libjpeg62-turbo-dev \
      libgd-dev \
      mysql-common

# Install tools
RUN apt-get update && apt-get install -y \
      mc \
      vim \
      # procps to get ps, top
      procps \
      curl \
      gnupg \
      git \
      sudo \
      iputils-ping \
      wget \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

RUN docker-php-ext-install mysqli pdo_mysql intl exif

# images support, like imagecreatefromjpeg()
RUN docker-php-ext-configure gd \
      --with-freetype-dir=/usr/include/ \
      --with-jpeg-dir=/usr/include/

RUN docker-php-ext-install gd \
    && php -r 'exit(function_exists("imagecreatefromjpeg") ? 0 : 1);'

RUN a2enmod rewrite expires && \
	# avoid warning on start
    echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# XDebug - to start it use docker compose
RUN pecl channel-update pecl.php.net \
    && yes | pecl install xdebug

# Fix debconf warnings upon build
ARG DEBIAN_FRONTEND=noninteractive

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

RUN echo 'alias ll="ls -al"' >> ~/.bashrc \
    && mkdir -p /var/log

RUN usermod --home /home/www-data --shell /bin/bash www-data \
    && mkdir -p /home/www-data \
    && chown -R www-data:www-data /home/www-data

USER www-data

RUN mkdir -p /home/www-data/bin \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/home/www-data/bin --filename=composer \
    && chmod +x /home/www-data/bin/composer \
  	&& echo 'export PATH="/home/www-data/bin:$PATH"' >> ~/.profile

RUN echo 'alias ll="ls -al"' >> /home/www-data/.bashrc

USER root

RUN echo 'alias ll="ls -al"' >> /root/.bashrc \
    && mkdir -p /var/log/php && chown -R www-data /var/log/php && chmod +w /var/log/php

COPY ./.docker/ /.docker

RUN chmod +x /.docker/*.sh

ENV APACHE_DOCUMENT_ROOT=/var/www/html/gamecon
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf
RUN sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

ENV ENV=local

WORKDIR $APACHE_DOCUMENT_ROOT

CMD bash /.docker/gamecon-run.sh $(pwd)
