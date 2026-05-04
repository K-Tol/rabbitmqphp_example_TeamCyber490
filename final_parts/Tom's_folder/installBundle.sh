#!/usr/bin/env bash
set -e

remoteBundlePath="$1"
targetPath="$2"
serviceName="$3"

if [ "$remoteBundlePath" = "h" ] || [ "$remoteBundlePath" = "-h" ] || [ "$remoteBundlePath" = "help" ] || [ "$remoteBundlePath" = "-help" ]; then
    echo "./installBundle.sh <remoteBundlePath> <targetPath> <serviceName>"
    exit 0
fi

if [ -z "$remoteBundlePath" ] || [ -z "$targetPath" ] || [ -z "$serviceName" ]; then
    echo "Missing variable"
    echo "Use: ./installBundle.sh <remoteBundlePath> <targetPath> <serviceName>"
    exit 1
fi

localTmpBundle="/tmp/${remoteBundlePath}"

# change the private ssh key path to the key you generated
# to connect to deploy
scp -i /path/to/private/key it-490@100.78.109.70:"${remoteBundlePath}" "$localTmpBundle"
mkdir -p "$targetPath"
rm -rf "${targetPath:?}/"*
tar -xf "$localTmpBundle" -C "$targetPath" --strip-components=1

# change from www-data for environments other than web
chown -R www-data:www-data "$targetPath"

/usr/bin/systemctl daemon-reload
/usr/bin/systemctl reload-or-restart "$serviceName"
rm -f "$localTmpBundle"