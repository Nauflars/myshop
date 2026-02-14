#!/bin/bash
# Script to fix permissions for Docker containers

echo "Fixing file permissions for Docker..."

# Get the current user
CURRENT_USER=$(whoami)

# Directories that need write access
DIRS=(
    "var"
    "config"
)

for dir in "${DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo "Setting permissions for $dir..."
        sudo chown -R $CURRENT_USER:$CURRENT_USER "$dir"
        chmod -R 775 "$dir"
    fi
done

# Specific files
FILES=(
    "config/reference.php"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "Setting permissions for $file..."
        sudo chown $CURRENT_USER:$CURRENT_USER "$file"
        chmod 664 "$file"
    fi
done

echo "Permissions fixed successfully!"
echo "You can now run: docker-compose exec php php bin/console c:c"
