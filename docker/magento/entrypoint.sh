#!/bin/bash
# Magento container entrypoint
# Runs installation on first start, then starts php-fpm

set -e

MAGENTO_ROOT="/var/www/html"

# Check if Magento needs to be installed
if [ ! -f "${MAGENTO_ROOT}/app/etc/env.php" ]; then
    echo "Magento not installed. Running installation..."
    /usr/local/bin/install-magento.sh
else
    echo "Magento already installed."
fi

# Start php-fpm
exec php-fpm
