#!/bin/bash
# Generate self-signed certificates for local HTTPS development
#
# Usage: ./generate-local-certs.sh [domain]
#
# Options:
#   --mkcert    Use mkcert for locally-trusted certificates (recommended)
#               Install mkcert first: https://github.com/FiloSottile/mkcert
#
# Examples:
#   ./generate-local-certs.sh                    # Self-signed for matre.local
#   ./generate-local-certs.sh myapp.local        # Self-signed for custom domain
#   ./generate-local-certs.sh --mkcert           # Trusted cert for matre.local
#   ./generate-local-certs.sh --mkcert myapp.local

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CERT_DIR="${SCRIPT_DIR}/certs"
DOMAIN="${1:-matre.local}"
USE_MKCERT=false

# Parse arguments
if [[ "$1" == "--mkcert" ]]; then
    USE_MKCERT=true
    DOMAIN="${2:-matre.local}"
elif [[ "$2" == "--mkcert" ]]; then
    USE_MKCERT=true
fi

mkdir -p "${CERT_DIR}"

echo "Generating certificates for: ${DOMAIN}"

if [[ "$USE_MKCERT" == true ]]; then
    # Check if mkcert is installed
    if ! command -v mkcert &> /dev/null; then
        echo "Error: mkcert is not installed."
        echo "Install it from: https://github.com/FiloSottile/mkcert"
        echo ""
        echo "Quick install:"
        echo "  macOS:  brew install mkcert && mkcert -install"
        echo "  Linux:  apt install mkcert && mkcert -install"
        exit 1
    fi

    echo "Using mkcert for locally-trusted certificates..."
    cd "${CERT_DIR}"
    mkcert -key-file local.key -cert-file local.crt "${DOMAIN}" "*.${DOMAIN}" localhost 127.0.0.1 ::1
    echo ""
    echo "Trusted certificates created! Your browser will trust these automatically."
else
    echo "Using OpenSSL for self-signed certificates..."

    # Generate self-signed certificate
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "${CERT_DIR}/local.key" \
        -out "${CERT_DIR}/local.crt" \
        -subj "/C=US/ST=Local/L=Local/O=MATRE Dev/CN=${DOMAIN}" \
        -addext "subjectAltName=DNS:${DOMAIN},DNS:*.${DOMAIN},DNS:localhost,IP:127.0.0.1"

    echo ""
    echo "Self-signed certificates created!"
    echo ""
    echo "Note: Your browser will show a security warning."
    echo "For trusted local certificates, use: $0 --mkcert"
fi

echo ""
echo "Certificate files:"
echo "  ${CERT_DIR}/local.crt"
echo "  ${CERT_DIR}/local.key"
echo ""
echo "To use HTTPS locally:"
echo "  1. Add '127.0.0.1 ${DOMAIN}' to /etc/hosts"
echo "  2. Run: docker-compose up -d"
echo "  3. Visit: https://${DOMAIN}"
