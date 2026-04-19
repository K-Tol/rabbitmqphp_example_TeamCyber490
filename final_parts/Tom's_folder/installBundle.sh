#!/usr/bin/bash
set -e

bundlePath="$1"
targetPath="$2"
serviceName="$3"

if [ "$bundlePath" = "h" ] || [ "$bundlePath" = "-h" ] || [ "$bundlePath" = "help" ] || [ "$bundlePath" = "-help" ]; then
    echo "./installBundle.sh <bundlePath> <targetPath>"
    exit
fi

mkdir -p "$targetPath"
rm -rf "${targetPath:?}/"*
tar -xf "$bundlePath" -C "$targetPath"

systemctl restart "$serviceName"