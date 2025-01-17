ARG APCU_VERSION=5.1.17
ARG COMPOSER_VERSION=1.8.6
ARG ICU_VERSION=64.2
ARG PHP_VERSION=7.3.6
ARG XDEBUG_VERSION=2.7.2


#####################################
##               APP               ##
#####################################
FROM php:${PHP_VERSION}-fpm as app

ARG ICU_VERSION
ARG APCU_VERSION

ENV APP_VERSION=0.0.0

WORKDIR /app

EXPOSE 80

# Install paquet requirements
RUN export PHP_CPPFLAGS="${PHP_CPPFLAGS} -std=c++11"; \
    set -ex; \
    # Install required system packages
    apt-get update; \
    apt-get install -qy --no-install-recommends \
            nginx \
            supervisor \
            libzip-dev \
    ; \
    # Compile ICU (required by intl php extension)
    curl -L -o /tmp/icu.tar.gz http://download.icu-project.org/files/icu4c/${ICU_VERSION}/icu4c-$(echo ${ICU_VERSION} | sed s/\\./_/g)-src.tgz; \
    tar -zxf /tmp/icu.tar.gz -C /tmp; \
    cd /tmp/icu/source; \
    ./configure --prefix=/usr/local; \
    make clean; \
    make; \
    make install; \
    #Install the PHP extensions
    docker-php-ext-configure intl --with-icu-dir=/usr/local; \
    docker-php-ext-install -j "$(nproc)" \
            intl \
            zip \
            bcmath \
    ; \
    pecl install \
            apcu-${APCU_VERSION} \
    ; \
    docker-php-ext-enable \
            opcache \
            apcu \
    ; \
    docker-php-source delete; \
    # Clean aptitude cache and tmp directory
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*;

## set recommended PHP.ini settings
RUN { \
        echo 'date.timezone = Europe/Paris'; \
        echo 'short_open_tag = off'; \
        echo 'expose_php = off'; \
        echo 'error_log = /proc/self/fd/2'; \
        echo 'memory_limit = 128m'; \
        echo 'post_max_size = 110m'; \
        echo 'upload_max_filesize = 100m'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.enable_cli = 1'; \
        echo 'opcache.memory_consumption = 256'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 20011'; \
        echo 'opcache.fast_shutdown = 1'; \
        echo 'realpath_cache_size = 4096K'; \
        echo 'realpath_cache_ttl = 600'; \
    } > /usr/local/etc/php/php.ini

RUN { \
        echo 'date.timezone = Europe/Paris'; \
        echo 'short_open_tag = off'; \
        echo 'memory_limit = -1'; \
    } > /usr/local/etc/php/php-cli.ini

# copy the Nginx config
COPY docker/nginx.conf /etc/nginx/

# copy the Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/


#####################################
##             APP DEV             ##
#####################################
FROM app as app-dev

ARG COMPOSER_VERSION
ARG XDEBUG_VERSION

ENV COMPOSER_ALLOW_SUPERUSER=1

# Install paquet requirements
RUN set -ex; \
    # Install required system packages
    apt-get update; \
    apt-get install -qy --no-install-recommends \
            unzip \
            git \
    ; \
    # Clean aptitude cache and tmp directory
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*;

# Install Composer
RUN set -ex; \
    EXPECTED_SIGNATURE="$(curl -L https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar.sha256sum)"; \
    curl -L -o composer.phar https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar; \
    ACTUAL_SIGNATURE="$(sha256sum composer.phar)"; \
    if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then >&2 echo 'ERROR: Invalid installer signature' && rm /usr/local/bin/composer && exit 1 ; fi; \
    chmod +x composer.phar && mv composer.phar /usr/local/bin/composer; \
    RESULT=$?; \
    exit $RESULT;

# Edit OPCache configuration
RUN set -ex; \
    { \
        echo 'opcache.validate_timestamps = 1'; \
        echo 'opcache.revalidate_freq = 0'; \
    } >> /usr/local/etc/php/php.ini

# Install Xdebug
RUN set -ex; \
    if [ "${XDEBUG_VERSION}" != 0 ]; \
    then \
        pecl install xdebug-${XDEBUG_VERSION}; \
        docker-php-ext-enable xdebug; \
        { \
            echo 'xdebug.remote_enable = on'; \
            echo 'xdebug.remote_connect_back = on'; \
        } >> /usr/local/etc/php/php.ini \
    ; fi

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]


#####################################
##       PROD VENDOR BUILDER       ##
#####################################
FROM composer:${COMPOSER_VERSION} as vendor-builder

COPY . /app
WORKDIR /app

RUN APP_ENV=prod composer install -o -n --no-ansi --no-dev


#####################################
##             APP PROD            ##
#####################################
FROM app as app-prod

ENV APP_ENV=prod

COPY --chown=www-data --from=vendor-builder /app /app
WORKDIR /app

# Edit OPCache configuration
RUN set -ex; \
    { \
        echo 'opcache.validate_timestamps = 0'; \
    } >> /usr/local/etc/php/php.ini

# copy the Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN ["chmod", "+x", "/usr/local/bin/entrypoint.sh"]

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
