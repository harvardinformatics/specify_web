FROM php:8.3-apache

# mysqli is required by every page; exif is used by the batch imaging scripts.
# The batch imaging scripts (php_includes/add_image_batches.php) also use Imagick;
# uncomment the imagick lines if those scripts will be run inside this container.
RUN apt-get update \
    && docker-php-ext-install mysqli exif \
    # && apt-get install -y --no-install-recommends libmagickwand-dev \
    # && pecl install imagick && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    sed -i -e 's?ErrorLog.*?ErrorLog /dev/stderr?' /etc/apache2/apache2.conf && \
    printf "display_errors = stderr\nlog_errors = On\nerror_log = /dev/stderr\n" > "$PHP_INI_DIR/conf.d/20-logging.ini" && \
    printf 'include_path = ".:/var/php/includes:/var/php/includes/specify_web:/usr/local/lib/php"\n' > "$PHP_INI_DIR/conf.d/30-include-path.ini"

EXPOSE 80

# Apache in the official image only loads sites-enabled/*.conf, so the name needs the .conf suffix.
COPY etc/000-default /etc/apache2/sites-enabled/000-default.conf

CMD ["apache2-foreground"]
