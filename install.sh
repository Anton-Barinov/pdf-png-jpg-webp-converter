#!/usr/bin/env bash
#===============================================================================
# Universal Offline File Converter - Bare-Metal Installer for Debian / Ubuntu
# GitHub: https://github.com/Anton-Barinov/pdf-png-jpg-webp-converter
#===============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}======================================================${NC}"
echo -e "${BLUE}   Universal Converter — Bare-Metal Installer         ${NC}"
echo -e "${BLUE}======================================================${NC}"

# Check root privileges
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] Please run this script with root privileges: sudo bash install.sh${NC}"
    exit 1
fi

# Detect PHP version
PHP_VER="8.3"
if ! command -v "php$PHP_VER" &>/dev/null; then
    PHP_VER="8.2"
    if ! command -v "php$PHP_VER" &>/dev/null; then
        PHP_VER="8.1"
    fi
fi

TARGET_DIR="/var/www/converter"
PUBLIC_DIR="$TARGET_DIR/public_html"
PRIVATE_DIR="$TARGET_DIR/private"
STORAGE_DIR="$PRIVATE_DIR/storage/sessions"

# 1. Install dependencies
echo -e "\n${YELLOW}[1/6] Installing system packages and CLI utilities...${NC}"
apt-get update -y
apt-get install -y --no-install-recommends \
    nginx \
    poppler-utils \
    webp \
    optipng \
    jpegoptim \
    qpdf \
    ghostscript \
    imagemagick \
    cron \
    curl

# Install PHP if not present
if ! command -v php &>/dev/null; then
    apt-get install -y php-fpm php-zip php-mbstring php-cli
    PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
else
    PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
    apt-get install -y "php${PHP_VER}-fpm" "php${PHP_VER}-zip" "php${PHP_VER}-mbstring" || true
fi

# 2. Hardening ImageMagick
echo -e "\n${YELLOW}[2/6] Hardening ImageMagick security policies...${NC}"
IM_POLICY="/etc/ImageMagick-6/policy.xml"
if [ ! -f "$IM_POLICY" ]; then
    IM_POLICY="/etc/ImageMagick-7/policy.xml"
fi

if [ -f "$IM_POLICY" ]; then
    if ! grep -q 'pattern="MVG"' "$IM_POLICY"; then
        sed -i 's/<\/policymap>/  <policy domain="coder" rights="none" pattern="MSL" \/>\n  <policy domain="coder" rights="none" pattern="MVG" \/>\n  <policy domain="delegate" rights="none" pattern="URL" \/>\n  <policy domain="delegate" rights="none" pattern="HTTP" \/>\n  <policy domain="delegate" rights="none" pattern="HTTPS" \/>\n  <policy domain="delegate" rights="none" pattern="FTP" \/>\n  <policy domain="delegate" rights="none" pattern="FTPS" \/>\n  <policy domain="path" rights="none" pattern="@*" \/>\n<\/policymap>/' "$IM_POLICY"
    fi
fi

# 3. Create directories & copy files
echo -e "\n${YELLOW}[3/6] Deploying application files to $TARGET_DIR...${NC}"
mkdir -p "$PUBLIC_DIR" "$PRIVATE_DIR/lib" "$STORAGE_DIR"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cp "$SCRIPT_DIR/src/index.php" "$PUBLIC_DIR/"
cp "$SCRIPT_DIR/src/api.php" "$PUBLIC_DIR/"
cp "$SCRIPT_DIR/src/config.php" "$PUBLIC_DIR/"
cp -r "$SCRIPT_DIR/src/assets" "$PUBLIC_DIR/"
cp -r "$SCRIPT_DIR/src/lib/"* "$PRIVATE_DIR/lib/"

# Permissions
chown -R www-data:www-data "$TARGET_DIR"
chmod 750 "$PRIVATE_DIR"
chmod 750 "$STORAGE_DIR"

# 4. Setup 5-minute cleanup cron
echo -e "\n${YELLOW}[4/6] Setting up automated 5-minute cleanup cron job...${NC}"
CRON_FILE="/etc/cron.d/converter-cleanup"
cat << EOF > "$CRON_FILE"
* * * * * root find $STORAGE_DIR/ -mindepth 1 -maxdepth 1 -mmin +5 -exec rm -rf {} + >/dev/null 2>&1
EOF
chmod 0644 "$CRON_FILE"

# 5. Configure Nginx virtual host
echo -e "\n${YELLOW}[5/6] Configuring Nginx virtual host...${NC}"
read -p "Enter domain name or server IP (e.g. conv.example.com or localhost) [localhost]: " DOMAIN_NAME
DOMAIN_NAME=${DOMAIN_NAME:-localhost}

NGINX_CONF="/etc/nginx/sites-available/converter"
cat << EOF > "$NGINX_CONF"
server {
    listen 80;
    server_name $DOMAIN_NAME;
    root $PUBLIC_DIR;
    index index.php index.html;

    client_max_body_size 100M;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    # Deny access to hidden files and private storage
    location ~ /\. {
        deny all;
    }
}
EOF

ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 6. Complete
echo -e "\n${GREEN}======================================================${NC}"
echo -e "${GREEN}   Installation completed successfully!               ${NC}"
echo -e "${GREEN}   Application URL: http://$DOMAIN_NAME/              ${NC}"
echo -e "${GREEN}======================================================${NC}"
