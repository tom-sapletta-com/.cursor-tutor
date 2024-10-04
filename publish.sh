#!/bin/bash
CONFIG=".env.prod"

# Load environment variables
if [ -f $CONFIG ]; then
    export $(cat $CONFIG | grep -v '#' | awk '/=/ {print $1}')
else
    echo "$CONFIG file not found"
    exit 1
fi

# Check if required variables are set
if [ -z "$SSH_HOST" ] || [ -z "$SSH_USER" ] || [ -z "$SSH_KEY" ] || [ -z "$REMOTE_PATH" ]; then
    echo "Missing required environment variables. Please check your $CONFIG file."
    exit 1
fi

# Create a temporary directory for the files to be transferred
TEMP_DIR=$(mktemp -d)

# Copy necessary files to the temporary directory
cp -r src vendor composer.json composer.lock db.sqlite config.php index.php edit.php meta.php login.php logout.php .env "$TEMP_DIR"
cp *.php "$TEMP_DIR"

# Sync the files to the remote server
rsync -avz -e "ssh -i $SSH_KEY" --delete \
    "$TEMP_DIR/" \
    "$SSH_USER@$SSH_HOST:$REMOTE_PATH"

# Remove the temporary directory
rm -rf "$TEMP_DIR"

# Run composer install on the remote server
ssh -i "$SSH_KEY" "$SSH_USER@$SSH_HOST" "cd $REMOTE_PATH && composer install --no-dev --optimize-autoloader"

# Set correct permissions on the remote server
ssh -i "$SSH_KEY" "$SSH_USER@$SSH_HOST" "cd $REMOTE_PATH && \
    chmod ug+rwx ../db.sqlite .env && \
    find . -type d -exec chmod 755 {} \; && \
    find . -type f -exec chmod 644 {} \; && \
    chmod 755 index.php edit.php meta.php login.php logout.php && \
    chown -R $FS_USER:$FS_GROUP ."

# Move Sqlite
ssh -i "$SSH_KEY" "$SSH_USER@$SSH_HOST" "mv $REMOTE_PATH/db.sqlite ../"

echo "Deployment completed successfully!"