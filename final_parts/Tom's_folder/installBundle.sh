#!/usr/bin/env bash
set -e

bundlePath="$1"
targetPath="$2"
serviceName="$3"

if [ "$bundlePath" = "h" ] || [ "$bundlePath" = "-h" ] || [ "$bundlePath" = "help" ] || [ "$bundlePath" = "-help" ]; then
    echo "./installBundle.sh <bundlePath> <targetPath> <serviceName>"
    exit 0
fi

if [ -z "$bundlePath" ] || [ -z "$targetPath" ] || [ -z "$serviceName" ]; then
    echo "Missing variable"
    echo "Use: ./installBundle.sh <bundlePath> <targetPath> <serviceName>"
    exit 1
fi

mkdir -p "$targetPath"
rm -rf "${targetPath:?}/"*
tar -xf "$bundlePath" -C "$targetPath" --strip-components=1

systemctl restart "$serviceName"