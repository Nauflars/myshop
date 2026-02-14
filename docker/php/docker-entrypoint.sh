#!/bin/bash
set -e

# Function to fix permissions for directories that need write access
fix_permissions() {
    echo "Fixing permissions for www-data user..."
    
    # Directories that need write access
    WRITABLE_DIRS=(
        "/var/www/html/var"
        "/var/www/html/config"
    )
    
    for dir in "${WRITABLE_DIRS[@]}"; do
        if [ -d "$dir" ]; then
            echo "Setting permissions for $dir"
            chmod -R 775 "$dir" 2>/dev/null || true
            chown -R www-data:www-data "$dir" 2>/dev/null || true
        fi
    done
    
    # Ensure specific files are writable
    if [ -f "/var/www/html/config/reference.php" ]; then
        chmod 664 /var/www/html/config/reference.php 2>/dev/null || true
        chown www-data:www-data /var/www/html/config/reference.php 2>/dev/null || true
    fi
}

# Only fix permissions if running as root
if [ "$(id -u)" = "0" ]; then
    fix_permissions
    
    # PHP-FPM needs to start as root and will change to www-data itself
    # For other commands, run as www-data
    if [ "$1" = "php-fpm" ]; then
        exec "$@"
    else
        exec gosu www-data "$@"
    fi
else
    # Already running as www-data, just execute the command
    exec "$@"
fi
