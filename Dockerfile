FROM php:7.2-apache

ARG USER_ID=1000
ARG GROUP_ID=1000

# Install other missed extensions
RUN apt-get update && apt-get install -y \
      zlib1g-dev \
      libaio-dev \
      libxml2-dev \
      librabbitmq-dev \
      libyaml-0-2 libyaml-dev \
      libfreetype6-dev libjpeg62-turbo-dev \
      libgd-dev

# Install tools
RUN apt-get install -y \
      mc \
      vim \
      curl \
      gnupg \
      git \
      sudo \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# images support, like imagecreatefromjpeg()
RUN docker-php-ext-configure gd \
      --with-freetype-dir=/usr/include/ \
      --with-jpeg-dir=/usr/include/
RUN docker-php-ext-install gd

RUN a2enmod rewrite expires && \
	# avoid warning on start
    echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Install additional PHP extensions
RUN yes | pecl install xdebug-2.6.1 \
    && docker-php-ext-install intl

# Fix debconf warnings upon build
ARG DEBIAN_FRONTEND=noninteractive

RUN docker-php-ext-install mysqli pdo_mysql intl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

RUN echo 'alias ll="ls -al"' >> ~/.bashrc \
    && mkdir -p /var/log

# re-build www-data user with same user ID and group ID as a current host user (you)
RUN userdel -f www-data && \
        if getent group www-data ; then groupdel www-data; fi && \
        groupadd --gid ${GROUP_ID} www-data && \
		useradd www-data --no-log-init --gid ${USER_ID} --groups www-data --home-dir /home/www-data && \
		chown -R www-data:www-data /var/www && \
		mkdir -p /home/www-data && \
		chown www-data:www-data /home/www-data

COPY ./.docker/ /.docker

RUN chmod +x /.docker/*.sh

ENV ENV=local

ENTRYPOINT /.docker/gamecon-run.sh