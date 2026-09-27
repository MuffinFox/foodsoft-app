FROM php:8.4-cli

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Xdebug configuration
RUN { \
        echo "xdebug.mode=debug"; \
        echo "xdebug.start_with_request=yes"; \
        echo "xdebug.client_host=host.docker.internal"; \
        echo "xdebug.client_port=9003"; \
        echo "xdebug.discover_client_host=true"; \
    } > /usr/local/etc/php/conf.d/xdebug.ini

    # Set working directory
WORKDIR /var/www

# Expose the dev server port
EXPOSE 8083
EXPOSE 9003

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:8083", "-t", "/var/www/pickup"]
