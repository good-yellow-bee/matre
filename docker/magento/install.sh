#!/bin/bash
# Magento 2 Minimal Installation Script for ATR
# Installs only what's needed for MFTF and Allure test execution
# No sample data, minimal modules, developer mode only

set -e

MAGENTO_VERSION="${MAGENTO_VERSION:-2.4.6}"
MAGENTO_ROOT="/var/www/html"

echo "=== Magento 2 Minimal Installation for MFTF ==="
echo "Version: ${MAGENTO_VERSION}"
echo "Root: ${MAGENTO_ROOT}"

# Check if already installed
if [ -f "${MAGENTO_ROOT}/app/etc/env.php" ]; then
    echo "Magento already installed."
    echo "To reinstall, remove the volume: docker volume rm atr_atr_magento_code"
    exit 0
fi

# Check for Magento Marketplace credentials
if [ -z "$MAGENTO_PUBLIC_KEY" ] || [ -z "$MAGENTO_PRIVATE_KEY" ]; then
    echo "ERROR: MAGENTO_PUBLIC_KEY and MAGENTO_PRIVATE_KEY are required."
    echo "Get them from: https://marketplace.magento.com/customer/accessKeys/"
    echo "Add to .env file:"
    echo "  MAGENTO_PUBLIC_KEY=your_public_key"
    echo "  MAGENTO_PRIVATE_KEY=your_private_key"
    exit 1
fi

cd ${MAGENTO_ROOT}

# Configure Composer auth for Magento repo
echo "Configuring Composer authentication..."
mkdir -p ~/.composer
cat > ~/.composer/auth.json << EOF
{
    "http-basic": {
        "repo.magento.com": {
            "username": "${MAGENTO_PUBLIC_KEY}",
            "password": "${MAGENTO_PRIVATE_KEY}"
        }
    }
}
EOF

# Wait for database
echo "Waiting for database..."
for i in {1..30}; do
    if mariadb --skip-ssl -h"${DB_HOST:-magento-db}" -u"${DB_USER:-magento}" -p"${DB_PASS:-magento}" -e "SELECT 1" > /dev/null 2>&1; then
        echo "Database ready."
        break
    fi
    echo "Database not ready, waiting... ($i/30)"
    sleep 5
done

# Wait for Elasticsearch
echo "Waiting for Elasticsearch..."
for i in {1..30}; do
    if curl -s "http://${ES_HOST:-magento-elasticsearch}:9200" > /dev/null 2>&1; then
        echo "Elasticsearch ready."
        break
    fi
    echo "Elasticsearch not ready, waiting... ($i/30)"
    sleep 5
done

# Create Magento project in temp directory then move
echo "Creating Magento ${MAGENTO_VERSION} project..."
TEMP_DIR="/tmp/magento-install"
rm -rf ${TEMP_DIR}
composer create-project --repository-url=https://repo.magento.com/ \
    magento/project-community-edition=${MAGENTO_VERSION} ${TEMP_DIR} \
    --no-install --no-interaction

# Move files to Magento root (preserving mounted directories)
echo "Moving Magento files to ${MAGENTO_ROOT}..."
cd ${TEMP_DIR}
for item in *; do
    if [ "$item" != "." ] && [ "$item" != ".." ]; then
        rm -rf "${MAGENTO_ROOT}/${item}" 2>/dev/null || true
        mv "${item}" "${MAGENTO_ROOT}/"
    fi
done
# Move hidden files
for item in .[!.]*; do
    if [ -e "$item" ]; then
        rm -rf "${MAGENTO_ROOT}/${item}" 2>/dev/null || true
        mv "${item}" "${MAGENTO_ROOT}/"
    fi
done
rm -rf ${TEMP_DIR}
cd ${MAGENTO_ROOT}

# Copy auth.json to project
cp ~/.composer/auth.json ${MAGENTO_ROOT}/auth.json

# Disable security advisory blocking (Magento has known advisories)
echo "Configuring composer to ignore security advisories..."
composer config audit.ignore "PKSA-db8d-773v-rd1n"
composer config audit.block-insecure false

# Install dependencies (no dev dependencies for minimal footprint, but we need MFTF)
echo "Installing dependencies (this may take several minutes)..."
composer install --no-interaction

# Patch MFTF schema to fix non-deterministic content model bug
# This bug exists in MFTF 4.3.12 and earlier - causes schema validation to fail
echo "Patching MFTF schema..."
MFTF_SCHEMA_DIR="${MAGENTO_ROOT}/vendor/magento/magento2-functional-testing-framework/src/Magento/FunctionalTestingFramework/Test/etc"
if [ -f "/usr/local/share/mftf-patches/mergedActionGroupSchema.xsd" ]; then
    cp /usr/local/share/mftf-patches/mergedActionGroupSchema.xsd "${MFTF_SCHEMA_DIR}/mergedActionGroupSchema.xsd"
    echo "MFTF schema patched"
fi

# Install Magento with minimal configuration
echo "Installing Magento (minimal for MFTF)..."
bin/magento setup:install \
    --base-url="http://magento.local/" \
    --db-host="${DB_HOST:-magento-db}" \
    --db-name="${DB_NAME:-magento}" \
    --db-user="${DB_USER:-magento}" \
    --db-password="${DB_PASS:-magento}" \
    --admin-firstname="Admin" \
    --admin-lastname="User" \
    --admin-email="admin@example.com" \
    --admin-user="admin" \
    --admin-password="Admin123!" \
    --language="en_US" \
    --currency="USD" \
    --timezone="America/New_York" \
    --use-rewrites="1" \
    --search-engine="elasticsearch7" \
    --elasticsearch-host="${ES_HOST:-magento-elasticsearch}" \
    --elasticsearch-port="9200" \
    --session-save="redis" \
    --session-save-redis-host="${REDIS_HOST:-magento-redis}" \
    --cache-backend="redis" \
    --cache-backend-redis-server="${REDIS_HOST:-magento-redis}" \
    --page-cache="redis" \
    --page-cache-redis-server="${REDIS_HOST:-magento-redis}" \
    --backend-frontname="admin"

# Set developer mode (required for MFTF, no static content deploy needed)
echo "Setting developer mode..."
bin/magento deploy:mode:set developer

# Disable modules not needed for MFTF execution
echo "Disabling non-essential modules for minimal footprint..."

# Modules to disable - grouped by category
DISABLE_MODULES=(
    # Two-Factor Auth (blocks admin login in tests)
    "Magento_AdminAdobeImsTwoFactorAuth"
    "Magento_TwoFactorAuth"

    # Adobe IMS (not needed for tests)
    "Magento_AdobeIms"
    "Magento_AdobeImsApi"
    "Magento_AdminAdobeIms"

    # Analytics & Tracking (external services)
    "Magento_Analytics"
    "Magento_GoogleAnalytics"
    "Magento_GoogleOptimizer"
    "Magento_GoogleGtag"
    "Magento_NewRelicReporting"

    # ReCAPTCHA (blocks forms in tests)
    "Magento_ReCaptchaAdminUi"
    "Magento_ReCaptchaCheckout"
    "Magento_ReCaptchaContact"
    "Magento_ReCaptchaCustomer"
    "Magento_ReCaptchaFrontendUi"
    "Magento_ReCaptchaGiftCard"
    "Magento_ReCaptchaMigration"
    "Magento_ReCaptchaNewsletter"
    "Magento_ReCaptchaPaypal"
    "Magento_ReCaptchaReview"
    "Magento_ReCaptchaSendFriend"
    "Magento_ReCaptchaStorePickup"
    "Magento_ReCaptchaUi"
    "Magento_ReCaptchaUser"
    "Magento_ReCaptchaValidation"
    "Magento_ReCaptchaValidationApi"
    "Magento_ReCaptchaVersion2Checkbox"
    "Magento_ReCaptchaVersion2Invisible"
    "Magento_ReCaptchaVersion3Invisible"
    "Magento_ReCaptchaWebapiApi"
    "Magento_ReCaptchaWebapiGraphQl"
    "Magento_ReCaptchaWebapiRest"
    "Magento_ReCaptchaWebapiUi"

    # Payment Gateways (external services, not needed for MFTF)
    "Magento_CardinalCommerce"
    "Magento_Signifyd"

    # Adobe Stock (external service)
    "Magento_AdobeStockAdminUi"
    "Magento_AdobeStockAsset"
    "Magento_AdobeStockAssetApi"
    "Magento_AdobeStockClient"
    "Magento_AdobeStockClientApi"
    "Magento_AdobeStockImage"
    "Magento_AdobeStockImageAdminUi"
    "Magento_AdobeStockImageApi"

    # CSP Reporting (not needed for tests)
    "Magento_CspRule"

    # Login as Customer (admin feature not needed)
    "Magento_LoginAsCustomer"
    "Magento_LoginAsCustomerAdminUi"
    "Magento_LoginAsCustomerApi"
    "Magento_LoginAsCustomerAssistance"
    "Magento_LoginAsCustomerFrontendUi"
    "Magento_LoginAsCustomerGraphQl"
    "Magento_LoginAsCustomerLog"
    "Magento_LoginAsCustomerPageCache"
    "Magento_LoginAsCustomerQuote"
    "Magento_LoginAsCustomerSales"
    "Magento_LoginAsCustomerWebsiteRestriction"
)

# Disable modules (ignore errors for modules that don't exist in this version)
for module in "${DISABLE_MODULES[@]}"; do
    bin/magento module:disable "$module" 2>/dev/null || true
done

# Run setup upgrade after disabling modules
echo "Running setup:upgrade..."
bin/magento setup:upgrade

# Clear caches
echo "Flushing caches..."
bin/magento cache:flush

# Setup MFTF
echo "Setting up MFTF..."
cd dev/tests/acceptance

# Create MFTF .env file
cat > .env << 'EOF'
MAGENTO_BASE_URL=http://magento.local/
MAGENTO_BACKEND_NAME=admin
MAGENTO_ADMIN_USERNAME=admin
MAGENTO_ADMIN_PASSWORD=Admin123!
BROWSER=chrome
MODULE_ALLOWLIST=Magento_Framework,Magento_ConfigurableProductWishlist,Magento_ConfigurableProductCatalogSearch
SELENIUM_HOST=selenium-hub
SELENIUM_PORT=4444
EOF

# Generate MFTF config
echo "Building MFTF project..."
../../../vendor/bin/mftf build:project || true

echo ""
echo "=== Magento ${MAGENTO_VERSION} Minimal Installation Complete ==="
echo ""
echo "Admin URL: http://magento.local/admin"
echo "Admin credentials: admin / Admin123!"
echo ""
echo "MFTF ready at: ${MAGENTO_ROOT}/dev/tests/acceptance"
echo ""
echo "Disabled modules:"
echo "  - Two-Factor Auth (2FA)"
echo "  - ReCAPTCHA"
echo "  - Analytics & Tracking"
echo "  - Adobe IMS/Stock"
echo "  - External Payment Services"
echo ""
