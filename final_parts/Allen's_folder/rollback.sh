#!/usr/bin/env bash
set -e

# this rollback file was made with our sendDeploy.php file

previousBundlePath="${1:-}"
targetPath="${2:-}"
serviceName="${3:-}"

# DEPLOY SERVER DETAILS
DEPLOY_USER="it-490"
DEPLOY_HOST="100.78.109.70"
SSH_KEY="/home/cyber/.ssh/id_ed25519"

if [ "$previousBundlePath" = "h" ] || [ "$previousBundlePath" = "-h" ] || [ "$previousBundlePath" = "help" ] || [ "$previousBundlePath" = "-help" ]; then
    echo "Usage: ./rollbackBundle.sh <previousBundlePath> <targetPath> <serviceName>"
    echo "Example:"
    echo "./rollbackBundle.sh /home/it-490/deployments/api_listener/1.0.1/bundle.tar.gz /var/www/html apache2"
    exit 0
fi

if [ -z "$previousBundlePath" ] || [ -z "$targetPath" ] || [ -z "$serviceName" ]; then
    echo "Error: Missing arguments."
    echo "Usage: ./rollbackBundle.sh <previousBundlePath> <targetPath> <serviceName>"
    exit 1
fi

case "$targetPath" in
    "/"|"/home"|"/var"|"/etc"|"/usr"|"/opt"|"/tmp"|"/root")
        echo "Error: Unsafe target path: $targetPath"
        exit 1
        ;;
esac

# Create a unique temp directory for this rollback run.
# This avoids permission conflicts with old /tmp/rollback_bundle.tar.gz files.
tmpDir="$(mktemp -d /tmp/teamcyber490_rollback.XXXXXX)"
localTmpBundle="${tmpDir}/$(basename "$previousBundlePath")"

cleanup() {
    rm -rf "$tmpDir"
}
trap cleanup EXIT

echo "Rollback started."
echo "Previous bundle path: $previousBundlePath"
echo "Target path: $targetPath"
echo "Service name: $serviceName"
echo "Temporary bundle path: $localTmpBundle"

echo "Pulling previous bundle from Deploy Server via SCP..."
scp -i "$SSH_KEY" "${DEPLOY_USER}@${DEPLOY_HOST}:${previousBundlePath}" "$localTmpBundle"

echo "Preparing target directory for rollback..."
mkdir -p "$targetPath"
rm -rf "${targetPath:?}/"*

echo "Extracting previous bundle..."
tar -xf "$localTmpBundle" -C "$targetPath" --strip-components=1

echo "Setting permissions..."
chown -R www-data:www-data "$targetPath"

echo "Restarting service after rollback..."
/usr/bin/systemctl daemon-reload
/usr/bin/systemctl reload-or-restart "$serviceName"

echo "Rollback successful! Previous version restored."