FROM php:8.3-apache-bookworm

# Install required system conversion utilities & dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    poppler-utils \
    webp \
    optipng \
    jpegoptim \
    qpdf \
    ghostscript \
    imagemagick \
    libzip-dev \
    cron \
    timeout \
    file \
    && docker-php-ext-install zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Harden ImageMagick policy
RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
        sed -i 's/<policy domain="coder" rights="none" pattern="MVG" \/>//' /etc/ImageMagick-6/policy.xml && \
        sed -i 's/<\/policymap>/  <policy domain="coder" rights="none" pattern="MSL" \/>\n  <policy domain="coder" rights="none" pattern="MVG" \/>\n  <policy domain="delegate" rights="none" pattern="URL" \/>\n  <policy domain="delegate" rights="none" pattern="HTTP" \/>\n  <policy domain="delegate" rights="none" pattern="HTTPS" \/>\n  <policy domain="delegate" rights="none" pattern="FTP" \/>\n  <policy domain="delegate" rights="none" pattern="FTPS" \/>\n  <policy domain="path" rights="none" pattern="@*" \/>\n<\/policymap>/' /etc/ImageMagick-6/policy.xml; \
    fi

# Configure Apache DocumentRoot to point to /var/www/html/public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers

# Create isolated private storage outside web root
RUN mkdir -p /var/www/private/lib /var/www/private/storage/sessions /var/www/html/public \
    && chown -R www-data:www-data /var/www

# Copy Application files
COPY src/index.php src/api.php src/config.php /var/www/html/public/
COPY src/assets /var/www/html/public/assets
COPY src/lib /var/www/private/lib

# Setup 5-minute cleanup cron
RUN echo "* * * * * root find /var/www/private/storage/sessions/ -mindepth 1 -maxdepth 1 -mmin +5 -exec rm -rf {} + >/dev/null 2>&1" > /etc/cron.d/converter-cleanup \
    && chmod 0644 /etc/cron.d/converter-cleanup

# Ensure permissions
RUN chown -R www-data:www-data /var/www/html /var/www/private

EXPOSE 80

# Start cron and apache in foreground
CMD cron && apache2-foreground
