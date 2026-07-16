#!/usr/bin/env bash
# Install WordPress PHPUnit test suite
# Usage: bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host>
# Example: bash bin/install-wp-tests.sh wordpress_test root root localhost

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host]"
	exit 1
fi

DB_NAME=${1:-wordpress_test}
DB_USER=${2:-root}
DB_PASS=${3:-root}
DB_HOST=${4:-localhost}

WP_VERSION=${WP_VERSION-latest}
SKIP_DB_CREATE=${SKIP_DB_CREATE-false}

TMPDIR="${TMPDIR-/tmp}"
BASEDIR="${TMPDIR}/wordpress-tests-lib-${DB_NAME}"

set -ex

# Download WordPress test suite
download() {
    if [ -f "$2" ]; then
        return
    fi

    if command -v curl &> /dev/null; then
        curl -s "$1" > "$2"
    elif command -v wget &> /dev/null; then
        wget -q "$1" -O "$2"
    else
        echo "Neither curl nor wget found. Please install one."
        exit 1
    fi
}

# Install test suite
install_test_suite() {
    # Portable in-place sed for Linux and macOS
    if [ "$(uname -s)" = 'Darwin' ]; then
        local sed_i='sed -i.bak -e'
    else
        local sed_i='sed -i'
    fi

    # Set WordPress version
    if [ "$WP_VERSION" = 'latest' ]; then
        local SVN_URL='https://develop.svn.wordpress.org/trunk'
    else
        local SVN_URL="https://develop.svn.wordpress.org/tags/$WP_VERSION"
    fi

    # Purge existing test suite
    if [ -d "$BASEDIR" ]; then
        rm -rf "$BASEDIR"
    fi

    mkdir -p "$BASEDIR"

    # Download wp-tests-config.php
    if [ ! -f "$BASEDIR/wp-tests-config.php" ]; then
        download "${SVN_URL}/wp-tests-config-sample.php" "$BASEDIR/wp-tests-config.php"
        # Remove leading "<?php" line if present
        $sed_i 's/^<?php$//' "$BASEDIR/wp-tests-config.php"
        
        # Edit database credentials (gunakan delimiter | biar tidak conflict dengan path yang mengandung /)
        $sed_i "s|dirname( __FILE__ ) . '/src/'|'${BASEDIR}/wordpress/'|" "$BASEDIR/wp-tests-config.php"
        $sed_i "s|yourdbnamehere|${DB_NAME}|" "$BASEDIR/wp-tests-config.php"
        $sed_i "s|yourusernamehere|${DB_USER}|" "$BASEDIR/wp-tests-config.php"
        $sed_i "s|yourpasswordhere|${DB_PASS}|" "$BASEDIR/wp-tests-config.php"
        $sed_i "s|yourhosthere|${DB_HOST}|" "$BASEDIR/wp-tests-config.php"
    fi

    # Download WordPress core
    if [ ! -d "$BASEDIR/wordpress" ]; then
        download "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" "$BASEDIR/wordpress.tar.gz"
        tar --strip-components=1 -zxmf "$BASEDIR/wordpress.tar.gz" -C "$BASEDIR/wordpress" 2>/dev/null || true
        rm "$BASEDIR/wordpress.tar.gz"
    fi

    # Download test suite includes
    if [ ! -d "$BASEDIR/includes" ]; then
        download "${SVN_URL}/wp-test-runner/includes.php" "$BASEDIR/includes.php"
        # Try svn first, fallback to downloading individual files via curl/wget
        if command -v svn &> /dev/null; then
            svn export --quiet "$SVN_URL/tests/phpunit/includes/" "$BASEDIR/includes/" 2>/dev/null || true
        fi
        if [ ! -f "$BASEDIR/includes/functions.php" ]; then
            mkdir -p "$BASEDIR/includes"
            for f in bootstrap.php factory.php functions.php install.php update.php load.php; do
                download "${SVN_URL}/tests/phpunit/includes/${f}" "$BASEDIR/includes/${f}"
            done
            # Download subdirectories
            mkdir -p "$BASEDIR/includes/exceptions"
            download "${SVN_URL}/tests/phpunit/includes/exceptions/class-wp-tests-exception.php" "$BASEDIR/includes/exceptions/class-wp-tests-exception.php"
            download "${SVN_URL}/tests/phpunit/includes/exceptions/class-wp-tests-skip-exception.php" "$BASEDIR/includes/exceptions/class-wp-tests-skip-exception.php"
            mkdir -p "$BASEDIR/includes/mock"
            download "${SVN_URL}/tests/phpunit/includes/mock/actions.php" "$BASEDIR/includes/mock/actions.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/class-wp-hook.php" "$BASEDIR/includes/mock/class-wp-hook.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/class-wp-fake-block-type.php" "$BASEDIR/includes/mock/class-wp-fake-block-type.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/class-wp-fake-block-patterns.php" "$BASEDIR/includes/mock/class-wp-fake-block-patterns.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/class-wp-fake-block.php" "$BASEDIR/includes/mock/class-wp-fake-block.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/class-wp-fake-nonce.php" "$BASEDIR/includes/mock/class-wp-fake-nonce.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/class-wp-fake-request.php" "$BASEDIR/includes/mock/class-wp-fake-request.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/class-wp-polyfill-image-editor.php" "$BASEDIR/includes/mock/class-wp-polyfill-image-editor.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/class-wp-polyfill-image-editor-imagick.php" "$BASEDIR/includes/mock/class-wp-polyfill-image-editor-imagick.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/class-wp-polyfill-image-editor-gd.php" "$BASEDIR/includes/mock/class-wp-polyfill-image-editor-gd.php"
            download "${SVN_URL}/tests/phpunit/includes/mock/mail.php" "$BASEDIR/includes/mock/mail.php"
            mkdir -p "$BASEDIR/includes/phpunit6"
            download "${SVN_URL}/tests/phpunit/includes/phpunit6/compat.php" "$BASEDIR/includes/phpunit6/compat.php"
            mkdir -p "$BASEDIR/includes/phpunit7"
            download "${SVN_URL}/tests/phpunit/includes/phpunit7/compat.php" "$BASEDIR/includes/phpunit7/compat.php"
            mkdir -p "$BASEDIR/includes/phpunit8"
            download "${SVN_URL}/tests/phpunit/includes/phpunit8/compat.php" "$BASEDIR/includes/phpunit8/compat.php"
        fi
    fi
}

install_db() {
    if [ "${SKIP_DB_CREATE}" = "true" ]; then
        return 0
    fi

    # Try creating the database
    if command -v mysql &> /dev/null; then
        mysql -u "$DB_USER" -p"${DB_PASS}" -h "$DB_HOST" -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};" 2>/dev/null || true
        echo "Database ${DB_NAME} created (or already exists)."
    else
        echo "MySQL CLI not found. Please create database '${DB_NAME}' manually:"
        echo "  mysql -u ${DB_USER} -p -h ${DB_HOST} -e 'CREATE DATABASE ${DB_NAME};'"
    fi
}

install_test_suite
install_db

echo ""
echo "✅ WordPress test suite installed at: ${BASEDIR}"
echo ""
echo "To run tests:"
echo "  export WP_TESTS_DIR=${BASEDIR}"
echo "  vendor/bin/phpunit"
echo ""
