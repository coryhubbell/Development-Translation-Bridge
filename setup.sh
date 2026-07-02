#!/bin/bash
# =============================================================================
# DevelopmentTranslation Bridge - Setup Script
# =============================================================================
# One-command setup for new contributors
# Supports: macOS, Linux, Windows (Git Bash/WSL)
# =============================================================================

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  DevelopmentTranslation Bridge Setup${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# -----------------------------------------------------------------------------
# Check PHP Version
# -----------------------------------------------------------------------------
echo -e "${YELLOW}Checking PHP version...${NC}"

if ! command -v php &> /dev/null; then
    echo -e "${RED}ERROR: PHP is not installed!${NC}"
    echo "Please install PHP 8.1 or higher."
    echo ""
    echo "Installation:"
    echo "  macOS:   brew install php"
    echo "  Ubuntu:  sudo apt install php php-cli php-mbstring php-xml php-curl"
    echo "  Windows: Download from https://windows.php.net/download/"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
PHP_MAJOR=$(php -r "echo PHP_MAJOR_VERSION;")
PHP_MINOR=$(php -r "echo PHP_MINOR_VERSION;")

if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 1 ]); then
    echo -e "${RED}ERROR: PHP 8.1+ required, found PHP $PHP_VERSION${NC}"
    exit 1
fi
echo -e "${GREEN}PHP $PHP_VERSION detected${NC}"

# -----------------------------------------------------------------------------
# Check Composer
# -----------------------------------------------------------------------------
echo -e "${YELLOW}Checking Composer...${NC}"

if [ -f "composer.phar" ]; then
    COMPOSER_CMD=(php composer.phar)
elif command -v composer &> /dev/null; then
    COMPOSER_CMD=(composer)
else
    echo -e "${RED}ERROR: Composer is not installed!${NC}"
    echo "Please install Composer 2.0 or higher."
    echo ""
    echo "Installation:"
    echo "  macOS:   brew install composer"
    echo "  Linux:   https://getcomposer.org/download/"
    echo "  Windows: https://getcomposer.org/doc/00-intro.md#installation-windows"
    exit 1
fi
echo -e "${GREEN}Composer detected: ${COMPOSER_CMD[*]}${NC}"

# -----------------------------------------------------------------------------
# Check Python / Node Tooling
# -----------------------------------------------------------------------------
echo -e "${YELLOW}Checking Python 3.11...${NC}"

PYTHON_BIN="${PYTHON:-python3.11}"
if ! command -v "$PYTHON_BIN" &> /dev/null; then
    echo -e "${RED}ERROR: Python 3.11 is required for local verification.${NC}"
    echo "Install Python 3.11 or rerun with PYTHON=/path/to/python3.11 ./setup.sh"
    exit 1
fi
echo -e "${GREEN}$($PYTHON_BIN --version) detected${NC}"

if ! command -v node &> /dev/null; then
    echo -e "${RED}ERROR: Node.js is required for the admin UI build.${NC}"
    echo "Install Node.js 20.19.0, 22.13.0+, or 24+."
    exit 1
fi
if ! node -e "const [major, minor] = process.versions.node.split('.').map(Number); process.exit(((major === 20 && minor >= 19) || (major === 22 && minor >= 13) || major >= 24) ? 0 : 1);"; then
    echo -e "${RED}ERROR: Node.js 20.19.0, 22.13.0+, or 24+ required, found $(node --version).${NC}"
    exit 1
fi
echo -e "${GREEN}Node $(node --version) detected${NC}"

if ! command -v npm &> /dev/null; then
    echo -e "${RED}ERROR: npm is required for the admin UI build.${NC}"
    echo "Install npm with Node.js 20.19.0, 22.13.0+, or 24+."
    exit 1
fi
echo -e "${GREEN}npm $(npm --version) detected${NC}"

# -----------------------------------------------------------------------------
# Create .env from .env.example
# -----------------------------------------------------------------------------
echo -e "${YELLOW}Setting up environment...${NC}"

if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo -e "${GREEN}Created .env from .env.example${NC}"

        # Generate security keys
        echo -e "${YELLOW}Generating security keys...${NC}"

        generate_key() {
            if command -v openssl &> /dev/null; then
                openssl rand -base64 48 | tr -d '\n' | head -c 64
            elif [ -r /dev/urandom ]; then
                head -c 48 /dev/urandom 2>/dev/null | base64 | tr -d '\n' | head -c 64
            else
                # Fallback
                date +%s%N | shasum -a 256 2>/dev/null | head -c 64 || \
                date +%s | md5sum | head -c 64
            fi
        }

        # Detect platform for sed compatibility
        if [[ "$OSTYPE" == "darwin"* ]]; then
            SED_INPLACE="sed -i ''"
        else
            SED_INPLACE="sed -i"
        fi

        # Replace placeholder keys
        for KEY in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
            NEW_VALUE=$(generate_key)
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s|${KEY}='put-your-unique-phrase-here'|${KEY}='${NEW_VALUE}'|g" .env
            else
                sed -i "s|${KEY}='put-your-unique-phrase-here'|${KEY}='${NEW_VALUE}'|g" .env
            fi
        done

        echo -e "${GREEN}Security keys generated${NC}"
    else
        echo -e "${RED}ERROR: .env.example not found!${NC}"
        echo "Please ensure you have the complete repository."
        exit 1
    fi
else
    echo -e "${GREEN}.env already exists${NC}"
fi

# -----------------------------------------------------------------------------
# Install Composer Dependencies
# -----------------------------------------------------------------------------
echo -e "${YELLOW}Installing PHP dependencies...${NC}"

"${COMPOSER_CMD[@]}" install --prefer-dist --no-progress --quiet
echo -e "${GREEN}PHP dependencies installed${NC}"

# -----------------------------------------------------------------------------
# Install Python Dependencies
# -----------------------------------------------------------------------------
echo -e "${YELLOW}Installing Python package and dev extras...${NC}"

"$PYTHON_BIN" -m pip install -e ".[dev]"
echo -e "${GREEN}Python dependencies installed${NC}"

# -----------------------------------------------------------------------------
# Install Admin Dependencies
# -----------------------------------------------------------------------------
echo -e "${YELLOW}Installing admin UI dependencies...${NC}"

if [ -d "admin" ]; then
    ( cd admin && npm ci )
    echo -e "${GREEN}Admin dependencies installed${NC}"
fi

# -----------------------------------------------------------------------------
# Make CLI Executable
# -----------------------------------------------------------------------------
echo -e "${YELLOW}Setting up CLI tool...${NC}"

if [ -f "devtb" ]; then
    chmod +x devtb
    echo -e "${GREEN}CLI tool ready${NC}"
fi

# -----------------------------------------------------------------------------
# Create coverage directory
# -----------------------------------------------------------------------------
mkdir -p coverage

# -----------------------------------------------------------------------------
# Verify Installation
# -----------------------------------------------------------------------------
echo ""
echo -e "${YELLOW}Verifying installation...${NC}"

# Check if autoload exists
if [ ! -f "vendor/autoload.php" ]; then
    echo -e "${RED}ERROR: Composer autoload not found${NC}"
    exit 1
fi

# Quick PHP syntax check on key files
php -l functions.php > /dev/null 2>&1 || {
    echo -e "${RED}ERROR: PHP syntax error in functions.php${NC}"
    exit 1
}

echo -e "${GREEN}Installation verified${NC}"

# -----------------------------------------------------------------------------
# Success Message
# -----------------------------------------------------------------------------
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Setup Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Next steps:"
echo ""
echo -e "  ${BLUE}1. Run tests:${NC}"
echo -e "     make verify"
echo ""
echo -e "  ${BLUE}2. Try a translation:${NC}"
echo -e "     ./devtb translate bootstrap divi examples/hero-bootstrap.html"
echo ""
echo -e "  ${BLUE}3. Start Docker (optional):${NC}"
echo -e "     make docker-up"
echo -e "     # WordPress: http://localhost:8080"
echo ""
echo -e "  ${BLUE}4. Read the docs:${NC}"
echo -e "     cat CONTRIBUTING.md"
echo ""
echo -e "${GREEN}Happy coding!${NC}"
echo ""
