FROM hosteeka/whmcs-base:8.8-apache-php8.1

# SYSTEM SETUP

# Set environment variables
#
# SERVER_ROOT is the root directory of the server
#
ENV SERVER_ROOT /var/www/html

WORKDIR $SERVER_ROOT

# Install dependencies
#
# libxml2-dev is required by soap extension
# libzip-dev is required by zip extension
#
RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends \
  libxml2-dev \
  libzip-dev; \
  rm -rf /var/lib/apt/lists/*

# Install PHP extensions
#
# soap and zip are recommended by whmcs
#
RUN docker-php-ext-install soap zip

# WHMCS SETUP

# Install ioncube loader
RUN set -eux; \
  curl -fsSL 'https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64_13.0.2.tar.gz' -o ioncube.tar.gz; \
  mkdir -p /tmp/ioncube; \
  tar -xf ioncube.tar.gz -C /tmp/ioncube --strip-components=1; \
  rm ioncube.tar.gz; \
  mv /tmp/ioncube/ioncube_loader_lin_8.1.so $(php -r "echo ini_get('extension_dir');")/ioncube_loader.so; \
  rm -r /tmp/ioncube; \
  docker-php-ext-enable ioncube_loader

# Copy WHMCS files
COPY --chown=www-data:www-data ./whmcs .
COPY --chown=www-data:www-data ./includes ./includes
COPY --chown=www-data:www-data ./modules ./modules
COPY --chown=www-data:www-data ./templates ./templates

# Expose ports
EXPOSE 80
