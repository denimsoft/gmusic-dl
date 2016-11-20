FROM ubuntu:16.04
MAINTAINER Andrew Mackrodt <andrew@denimsoft.com>

ENV DEBIAN_FRONTEND noninteractive
ENV GMUSIC_OUTPUT_DIR /srv

RUN apt-get -q -y update \
    && apt-get -q -y install python-pip python-openssl curl unzip php7.0 php7.0-curl php7.0-zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /var/cache/*

RUN pip --no-cache-dir install gmusicapi

RUN curl -sSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin \
    && ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

RUN curl -sSLo /tmp/gmusic-dl.zip https://github.com/denimsoft/gmusic-dl/archive/master.zip \
    && unzip /tmp/gmusic-dl -d /tmp \
    && mv /tmp/gmusic-dl-master /opt/gmusic-dl \
    && rm -rf /tmp/*

WORKDIR /opt/gmusic-dl

RUN composer install \
    && composer clear-cache

ENTRYPOINT ["php", "gmusic-dl.php"]