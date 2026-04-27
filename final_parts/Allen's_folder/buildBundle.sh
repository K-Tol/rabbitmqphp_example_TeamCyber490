#!/bin/bash
set -e

name="$first"
version="$second"
sourceDir="$third"
deployDir="$fourth"

#deployment vm details
deployment_user="it-490"
deployment_host="100.78.109.70"

if [ -z "$name" ] || [ -z "$version" ] || [ -z "$sourceDir" ] || [ -z "$deployDir" ]; then
    echo "Usage: ./buildBundle.sh <name> <version> <sourceDir> <deployDir>"
    exit 1
fi

if [ ! -d "$sourceDir" ]; then
    echo "The source directory is not found: $sourceDir"
    exit 1
fi

stagingDir=$(mktemp -d)
trap 'rm -rf "$stagingDir"' EXIT

mkdir -p "$stagingDir/package"
cp -r "$sourceDir"/* "$stagingDir/package"/

echo "$version" > "$stagingDir/version.txt"
echo "new" > "$stagingDir/status.txt"

localBundle="$stagingDir/bundle.tar.gz"
tar -czf "$localBundle" -C "stagingDir" .
echo "Bundle created locally. Pushing to deployment VM via SCP..."

versionDir="$deployDir/$name/$version"
ssh -i ~/.ssh/id_ed25519 "${deployment_user}@${deployment_host}" "mkdir -p $versionDir"
scp -i ~/.ssh/id_ed25519 "$localBundle" "${deployment_user}@${deployment_host}:$versionDir/bundle.tar.gz"
echo "Bundle has been successfully pushed to ${deployment_user}@${deployment_host}:$versionDir/bundle.tar.gz"